<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\PriorityEvidence;
use App\Models\PriorityItem;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriorityItemController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $q = trim((string) $request->get('q'));
        $type = $request->get('type');
        $status = $request->get('status');
        $priority = $request->get('priority');
        $assignedStaffId = $request->get('assigned_staff_id');
        $urgency = $request->get('urgency');

        if (! array_key_exists((string) $urgency, PriorityItem::URGENCIES)) {
            $urgency = null;
        }

        $query = PriorityItem::with(['assignedStaff', 'creator'])
            ->withCount('evidences')
            ->visibleTo($user)
            ->orderByUrgency();

        if ($q !== '') {
            $query->where(function ($query) use ($q) {
                $query->where('task_lesson', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        if ($type !== '' && $type !== null) {
            $query->where('type', $type);
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }

        if ($priority !== '' && $priority !== null) {
            $query->where('priority', $priority);
        }

        if ($assignedStaffId !== '' && $assignedStaffId !== null) {
            $query->where('assigned_staff_id', $assignedStaffId);
        }

        if ($urgency !== null) {
            $query->whereUrgency($urgency);
        }

        $items = $query->paginate(50)->withQueryString();

        return view('admin.priority-items.index', [
            'items' => $items,
            'staffAccounts' => $this->staffAccounts(),
            'types' => PriorityItem::TYPES,
            'priorities' => PriorityItem::PRIORITIES,
            'statuses' => PriorityItem::STATUSES,
            'urgencies' => PriorityItem::URGENCIES,
            'q' => $q,
            'activeType' => $type,
            'activeStatus' => $status,
            'activePriority' => $priority,
            'activeUrgency' => $urgency,
            'activeAssignedStaffId' => $assignedStaffId,
            'summary' => $this->urgencySummary($user),
            'hasFilters' => $q !== '' || $type || $priority || $status || $assignedStaffId || $urgency,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create Priority items.');

        return view('admin.priority-items.create', [
            'staffAccounts' => $this->staffAccounts(),
            'types' => PriorityItem::TYPES,
            'priorities' => PriorityItem::PRIORITIES,
            'statuses' => PriorityItem::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create Priority items.');

        $validated = $request->validate([
            'task_lesson' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::TYPES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::PRIORITIES))],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['created_by'] = auth()->id();

        /* The server owns the default deadline. A custom due date chosen on the
           form is respected; otherwise it is derived from the priority. */
        if (empty($validated['due_date'] ?? null)) {
            $validated['due_date'] = PriorityItem::defaultDueDateForPriority($validated['priority']);
        }

        $item = PriorityItem::create($validated);

        $this->notifyAssignment($item);
        $this->logActivity($item, 'admin.priority_item_created', 'Created Priority item');

        return redirect()->route('admin.priority-items.index')->with('status', 'Priority item created.');
    }

    public function show(PriorityItem $item): View
    {
        abort_unless($item->isVisibleTo(auth()->user()), 403);

        $item->load(['assignedStaff', 'creator', 'checklistItems.completer', 'evidences.uploader']);

        return view('admin.priority-items.show', [
            'item' => $item,
        ]);
    }

    public function edit(PriorityItem $item): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can edit Priority items.');

        /* The edit form renders its Type / Priority / Status selects from
           PriorityItem::TYPES, ::PRIORITIES and ::STATUSES (the same
           authoritative lists the list filters and validation use), so they
           have to be handed to the view explicitly. */
        return view('admin.priority-items.edit', [
            'item' => $item,
            'staffAccounts' => $this->staffAccounts(),
            'types' => PriorityItem::TYPES,
            'priorities' => PriorityItem::PRIORITIES,
            'statuses' => PriorityItem::STATUSES,
        ]);
    }

    public function update(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can update Priority items.');

        $wasAssigned = $item->assigned_staff_id;

        $validated = $request->validate([
            'task_lesson' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::TYPES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::PRIORITIES))],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(PriorityItem::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /* Historical deadlines are never overwritten implicitly. The due date
           only moves when the form submits a new value; a blank field keeps the
           stored deadline intact. */
        if (! $request->filled('due_date')) {
            $validated['due_date'] = $item->due_date?->format('Y-m-d');
        }

        $item->update($validated);

        if ($item->assigned_staff_id !== $wasAssigned) {
            $this->notifyAssignment($item);
            ActivityLog::record(
                auth()->user(),
                'admin.priority_item_reassigned',
                "Reassigned Priority item #{$item->id} from staff #{$wasAssigned} to staff #{$item->assigned_staff_id}."
            );
        } else {
            ActivityLog::record(
                auth()->user(),
                'admin.priority_item_updated',
                "Updated Priority item #{$item->id}."
            );
        }

        return back()->with('status', 'Priority item updated.');
    }

    public function destroy(PriorityItem $item): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete Priority items.');

        $item->delete();

        ActivityLog::record(auth()->user(), 'admin.priority_item_deleted', 'Deleted a Priority item record.');

        return back()->with('status', 'Priority item deleted.');
    }

    public function toggleChecklistItem(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless($item->isVisibleTo(auth()->user()), 403);

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
        ]);

        $checklistItem = $item->checklistItems()->findOrFail($validated['checklist_item_id']);
        $checklistItem->toggleComplete(auth()->user());

        ActivityLog::record(
            auth()->user(),
            'admin.priority_checklist_toggled',
            $checklistItem->completed
                ? "Completed checklist item \"{$checklistItem->title}\" on Priority item #{$item->id}."
                : "Reopened checklist item \"{$checklistItem->title}\" on Priority item #{$item->id}.",
        );

        return back()->with('status', $checklistItem->completed ? 'Checklist item completed.' : 'Checklist item reopened.');
    }

    public function addChecklistItem(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless($item->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can add checklist items.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $maxSort = $item->checklistItems()->max('sort_order') ?? 0;

        $item->checklistItems()->create([
            'title' => $validated['title'],
            'sort_order' => $maxSort + 1,
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.priority_checklist_added',
            "Added checklist item \"{$validated['title']}\" to Priority item #{$item->id}."
        );

        return back()->with('status', 'Checklist item added.');
    }

    public function updateChecklistItem(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless($item->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can edit checklist items.');

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
            'title' => ['required', 'string', 'max:500'],
        ]);

        $checklistItem = $item->checklistItems()->findOrFail($validated['checklist_item_id']);
        $checklistItem->update(['title' => $validated['title']]);

        ActivityLog::record(
            auth()->user(),
            'admin.priority_checklist_updated',
            "Updated checklist item \"{$validated['title']}\" on Priority item #{$item->id}."
        );

        return back()->with('status', 'Checklist item updated.');
    }

    public function destroyChecklistItem(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless($item->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can delete checklist items.');

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
        ]);

        $checklistItem = $item->checklistItems()->findOrFail($validated['checklist_item_id']);
        $title = $checklistItem->title;
        $checklistItem->delete();

        ActivityLog::record(
            auth()->user(),
            'admin.priority_checklist_deleted',
            "Deleted checklist item \"{$title}\" from Priority item #{$item->id}."
        );

        return back()->with('status', 'Checklist item deleted.');
    }

    public function addEvidence(Request $request, PriorityItem $item): RedirectResponse
    {
        abort_unless($item->isVisibleTo(auth()->user()), 403);

        $validated = $request->validate([
            'evidence' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv'],
        ]);

        $file = $validated['evidence'];

        $item->evidences()->create([
            'uploaded_by' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('priority-evidence', 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->logActivity(
            $item,
            'admin.priority_evidence_added',
            "Added implementation evidence to Priority item #{$item->id}."
        );

        return back()->with('status', 'Evidence uploaded.');
    }

    public function viewEvidence(PriorityItem $item, PriorityEvidence $evidence): StreamedResponse
    {
        $this->authorizeEvidence($item, $evidence);

        return Storage::disk('local')->response($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadEvidence(PriorityItem $item, PriorityEvidence $evidence): StreamedResponse
    {
        $this->authorizeEvidence($item, $evidence);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteEvidence(PriorityItem $item, PriorityEvidence $evidence): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSupervisor(), 403, 'Only admins and supervisors can remove evidence.');
        $this->authorizeEvidence($item, $evidence);

        Storage::disk('local')->delete($evidence->path);
        $evidence->delete();

        $this->logActivity(
            $item,
            'admin.priority_evidence_deleted',
            "Removed implementation evidence from Priority item #{$item->id}."
        );

        return back()->with('status', 'Evidence removed.');
    }

    /**
     * Ensure the evidence belongs to this item and the viewer may see it.
     */
    private function authorizeEvidence(PriorityItem $item, PriorityEvidence $evidence): void
    {
        abort_unless((int) $evidence->priority_item_id === (int) $item->id, 404);
        abort_unless($item->isVisibleTo(auth()->user()), 403);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);
    }

    /**
     * Counts backing the "attention summary" shown above the list. Always
     * computed from the records the viewer may see, independent of the active
     * filters, so the overview stays stable while filtering.
     */
    private function urgencySummary(User $user): array
    {
        $count = function (string $urgency) use ($user): int {
            return PriorityItem::query()
                ->visibleTo($user)
                ->whereUrgency($urgency)
                ->count();
        };

        return [
            'action_required' => $count(PriorityItem::URGENCY_OVERDUE)
                + $count(PriorityItem::URGENCY_ACTION)
                + $count(PriorityItem::URGENCY_DUE_TODAY),
            'overdue' => $count(PriorityItem::URGENCY_OVERDUE),
            'due_today' => $count(PriorityItem::URGENCY_DUE_TODAY),
            'due_tomorrow' => $count(PriorityItem::URGENCY_DUE_TOMORROW),
            'completed' => $count(PriorityItem::URGENCY_COMPLETED),
        ];
    }

    /**
     * Staff accounts offered in the assignment select. Shared by the list
     * filter, the create form and the edit form so all three stay in sync.
     */
    private function staffAccounts(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_STAFF)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function notifyAssignment(PriorityItem $item): void
    {
        if (! $item->assigned_staff_id) {
            return;
        }

        $staff = User::find($item->assigned_staff_id);
        if (! $staff) {
            return;
        }

        $link = route('admin.priority-items.show', $item);

        Notification::create([
            'user_id' => $staff->id,
            'title' => 'New Priority Item Assigned',
            'body' => "You have been assigned to: \"{$item->task_lesson}\" ({$item->typeLabel()})",
            'type' => 'priority_assignment',
            'group_key' => "priority-assignment:{$item->id}",
            'link' => $link,
            'reminder_count' => 1,
        ]);

        PushNotificationService::send(
            $staff,
            'New Priority Item Assigned',
            "You have been assigned to: \"{$item->task_lesson}\" ({$item->typeLabel()})",
            $link
        );
    }

    private function logActivity(PriorityItem $item, string $action, string $description): void
    {
        ActivityLog::record(
            auth()->user(),
            $action,
            $description." (Task: {$item->task_lesson})"
        );
    }
}
