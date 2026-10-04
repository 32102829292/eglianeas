<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KaizenConcern;
use App\Models\KaizenEvidence;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KaizenConcernController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');
        $assignedStaffId = $request->get('assigned_staff_id');

        $query = KaizenConcern::with(['assignedStaff', 'creator'])
            ->orderByDesc('date_identified')
            ->orderByDesc('id')
            ->visibleTo($user);

        if ($q !== '') {
            $query->where(function ($query) use ($q) {
                $query->where('challenge', 'like', "%{$q}%")
                    ->orWhere('recommended_solution', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }

        if ($assignedStaffId !== '' && $assignedStaffId !== null) {
            $query->where('assigned_staff_id', $assignedStaffId);
        }

        $concerns = $query->paginate(50)->withQueryString();

        return view('admin.kaizen-concerns.index', [
            'concerns' => $concerns,
            'staffAccounts' => $this->staffAccounts(),
            'statuses' => KaizenConcern::STATUSES,
            'q' => $q,
            'activeStatus' => $status,
            'activeAssignedStaffId' => $assignedStaffId,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create Kaizen concerns.');

        return view('admin.kaizen-concerns.create', [
            'staffAccounts' => $this->staffAccounts(),
            'statuses' => KaizenConcern::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create Kaizen concerns.');

        $validated = $request->validate([
            'date_identified' => ['required', 'date'],
            'challenge' => ['required', 'string', 'max:5000'],
            'recommended_solution' => ['required', 'string', 'max:5000'],
            'target_date' => ['nullable', 'date'],
            'implementation_date' => ['nullable', 'date'],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(KaizenConcern::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['created_by'] = auth()->id();

        $concern = KaizenConcern::create($validated);

        $this->notifyAssignment($concern);
        $this->logActivity($concern, 'admin.kaizen_concern_created', 'Created Kaizen concern');

        return redirect()->route('admin.kaizen-concerns.index')->with('status', 'Kaizen concern created.');
    }

    public function show(KaizenConcern $concern): View
    {
        abort_unless($concern->isVisibleTo(auth()->user()), 403);

        $concern->load(['assignedStaff', 'creator', 'checklistItems', 'evidences.uploader']);

        return view('admin.kaizen-concerns.show', [
            'concern' => $concern,
        ]);
    }

    public function edit(KaizenConcern $concern): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can edit Kaizen concerns.');

        /* The edit form renders its status <select> from KaizenConcern::STATUSES
           (the same authoritative list the list filter and validation use), so
           it has to be handed to the view explicitly. */
        return view('admin.kaizen-concerns.edit', [
            'concern' => $concern,
            'staffAccounts' => $this->staffAccounts(),
            'statuses' => KaizenConcern::STATUSES,
        ]);
    }

    public function update(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can update Kaizen concerns.');

        $wasAssigned = $concern->assigned_staff_id;

        $validated = $request->validate([
            'date_identified' => ['required', 'date'],
            'challenge' => ['required', 'string', 'max:5000'],
            'recommended_solution' => ['required', 'string', 'max:5000'],
            'target_date' => ['nullable', 'date'],
            'implementation_date' => ['nullable', 'date'],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(KaizenConcern::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $concern->update($validated);

        if ($concern->assigned_staff_id !== $wasAssigned) {
            $this->notifyAssignment($concern);
            ActivityLog::record(
                auth()->user(),
                'admin.kaizen_concern_reassigned',
                "Reassigned Kaizen concern #{$concern->id} from staff #{$wasAssigned} to staff #{$concern->assigned_staff_id}."
            );
        } else {
            ActivityLog::record(
                auth()->user(),
                'admin.kaizen_concern_updated',
                "Updated Kaizen concern #{$concern->id}."
            );
        }

        return back()->with('status', 'Kaizen concern updated.');
    }

    public function destroy(KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete Kaizen concerns.');

        $concern->delete();

        ActivityLog::record(auth()->user(), 'admin.kaizen_concern_deleted', 'Deleted a Kaizen concern record.');

        return back()->with('status', 'Kaizen concern deleted.');
    }

    public function toggleChecklistItem(Request $request, KaizenConcern $concern): RedirectResponse|JsonResponse
    {
        abort_unless($concern->isVisibleTo(auth()->user()), 403);

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
        ]);

        $item = $concern->checklistItems()->findOrFail($validated['checklist_item_id']);
        $item->toggleComplete(auth()->user());

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_checklist_toggled',
            $item->completed ? "Completed checklist item \"{$item->title}\" on Kaizen concern #{$concern->id}."
                             : "Reopened checklist item \"{$item->title}\" on Kaizen concern #{$concern->id}.",
        );

        $message = $item->completed ? 'Checklist item completed.' : 'Checklist item reopened.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'completed' => $item->completed,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    public function addChecklistItem(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can add checklist items.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $maxSort = $concern->checklistItems()->max('sort_order') ?? 0;

        $concern->checklistItems()->create([
            'title' => $validated['title'],
            'sort_order' => $maxSort + 1,
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_checklist_added',
            "Added checklist item \"{$validated['title']}\" to Kaizen concern #{$concern->id}."
        );

        return back()->with('status', 'Checklist item added.');
    }

    public function updateChecklistItem(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can edit checklist items.');

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
            'title' => ['required', 'string', 'max:500'],
        ]);

        $item = $concern->checklistItems()->findOrFail($validated['checklist_item_id']);
        $item->update(['title' => $validated['title']]);

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_checklist_updated',
            "Updated checklist item \"{$validated['title']}\" on Kaizen concern #{$concern->id}."
        );

        return back()->with('status', 'Checklist item updated.');
    }

    public function destroyChecklistItem(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canManageChecklist(auth()->user()), 403, 'Only admins and supervisors can delete checklist items.');

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer', 'exists:checklist_items,id'],
        ]);

        $item = $concern->checklistItems()->findOrFail($validated['checklist_item_id']);
        $title = $item->title;
        $item->delete();

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_checklist_deleted',
            "Deleted checklist item \"{$title}\" from Kaizen concern #{$concern->id}."
        );

        return back()->with('status', 'Checklist item deleted.');
    }

    public function addEvidence(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canUploadEvidence(auth()->user()), 403);

        $validated = $request->validate([
            'evidence' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $file = $validated['evidence'];

        $concern->evidences()->create([
            'uploaded_by' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('kaizen-evidence', 'local'),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->logActivity(
            $concern,
            'admin.kaizen_evidence_added',
            "Added implementation evidence to Kaizen concern #{$concern->id}."
        );

        return back()->with('status', 'Evidence uploaded.');
    }

    public function viewEvidence(KaizenConcern $concern, KaizenEvidence $evidence): StreamedResponse
    {
        $this->authorizeEvidence($concern, $evidence);

        return Storage::disk('local')->response($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadEvidence(KaizenConcern $concern, KaizenEvidence $evidence): StreamedResponse
    {
        $this->authorizeEvidence($concern, $evidence);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteEvidence(KaizenConcern $concern, KaizenEvidence $evidence): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can remove evidence.');
        $this->authorizeEvidence($concern, $evidence);

        Storage::disk('local')->delete($evidence->path);
        $evidence->delete();

        $this->logActivity(
            $concern,
            'admin.kaizen_evidence_deleted',
            "Removed implementation evidence from Kaizen concern #{$concern->id}."
        );

        return back()->with('status', 'Evidence removed.');
    }

    /**
     * Ensure the evidence belongs to this concern and the viewer may see it.
     */
    private function authorizeEvidence(KaizenConcern $concern, KaizenEvidence $evidence): void
    {
        abort_unless((int) $evidence->kaizen_concern_id === (int) $concern->id, 404);
        abort_unless($concern->isVisibleTo(auth()->user()), 403);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);
    }

    /**
     * Staff accounts offered in the assignment select. Shared by the list
     * filter, the create form and the edit form so all three stay in sync.
     */
    private function staffAccounts(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('role', User::ROLE_STAFF)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function notifyAssignment(KaizenConcern $concern): void
    {
        if (! $concern->assigned_staff_id) {
            return;
        }

        $staff = User::find($concern->assigned_staff_id);
        if (! $staff) {
            return;
        }

        $link = route('admin.kaizen-concerns.show', $concern);

        Notification::create([
            'user_id' => $staff->id,
            'title' => 'New Kaizen Concern Assigned',
            'body' => "You have been assigned to Kaizen concern: \"{$concern->challenge}\"",
            'type' => 'kaizen_assignment',
            'group_key' => "kaizen-assignment:{$concern->id}",
            'link' => $link,
            'reminder_count' => 1,
        ]);

        PushNotificationService::send(
            $staff,
            'New Kaizen Concern Assigned',
            "You have been assigned to Kaizen concern: \"{$concern->challenge}\"",
            $link
        );
    }

    private function logActivity(KaizenConcern $concern, string $action, string $description): void
    {
        ActivityLog::record(
            auth()->user(),
            $action,
            $description . " (Challenge: {$concern->challenge})"
        );
    }
}
