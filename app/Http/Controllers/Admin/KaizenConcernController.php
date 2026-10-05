<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KaizenConcern;
use App\Models\KaizenEvidence;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KaizenConcernController extends Controller
{
    /**
     * The Improvement Suggestions board.
     *
     * Admin Concerns live in the same table, so the query is narrowed to
     * employee suggestions here rather than in the view. `withCount` is what the
     * Evidence column reads, so the board can say how many files are attached
     * without opening each record.
     */
    public function index(Request $request): View
    {
        return $this->concernBoard(
            $request,
            fn (Builder $query) => $query->employeeSuggestions(),
            'admin.kaizen-concerns.index'
        );
    }

    /**
     * The Admin Concerns board.
     *
     * This is the same board pointed at the other half of the shared table, so
     * the filters, columns, evidence counts, status logic and actions cannot
     * drift apart from the Improvement Suggestions board. Admin Concerns are
     * selected purely by `type` — who created a record (an admin can submit an
     * Employee Suggestion too) never decides where it appears.
     *
     * Admin only: this is the administrative view of admin-raised records, and
     * nothing here widens what any other role can reach. Per-record access is
     * still decided by the unchanged isVisibleTo()/visibleTo() rules below.
     */
    public function adminConcerns(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can view the Admin Concerns list.');

        return $this->concernBoard(
            $request,
            fn (Builder $query) => $query->adminConcerns(),
            'admin.kaizen-concerns.admin-concerns'
        );
    }

    /**
     * Shared board for both halves of kaizen_concerns.
     *
     * Only the type scope and the view differ; the filter handling, eager
     * loading, ordering and per-user visibility are built once so the two
     * boards cannot drift.
     *
     * @param  Closure(Builder): Builder  $typeScope
     */
    private function concernBoard(Request $request, Closure $typeScope, string $view): View
    {
        $user = auth()->user();
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');
        $assignedStaffId = $request->get('assigned_staff_id');

        $query = $typeScope(
            KaizenConcern::with(['assignedStaff', 'creator'])
                ->withCount('evidences')
        )
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

        if ($assignedStaffId === 'unassigned') {
            $query->whereNull('assigned_staff_id');
        } elseif ($assignedStaffId !== '' && $assignedStaffId !== null) {
            $query->where('assigned_staff_id', $assignedStaffId);
        }

        $concerns = $query->paginate(50)->withQueryString();

        return view($view, [
            'concerns' => $concerns,
            'staffAccounts' => $this->staffAccounts(),
            'statuses' => KaizenConcern::STATUSES,
            'q' => $q,
            'activeStatus' => $status,
            'activeAssignedStaffId' => $assignedStaffId,
            'hasFilters' => $q !== '' || ! empty($status) || ! empty($assignedStaffId),
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

    public function submit(): View
    {
        abort_unless(auth()->user()->isOperational(), 403);

        return view('admin.kaizen-concerns.submit');
    }

    /**
     * Any operational employee (staff, supervisor, admin) can submit their own
     * Employee Suggestion. The submitter is taken from the authenticated user
     * and is never accepted from the request, so nobody can submit an idea in
     * someone else's name. The record is always created as Pending and
     * unassigned so the existing admin assignment / status workflow stays the
     * only way to move it forward.
     *
     * The record is also typed as an employee suggestion here, which is what keeps
     * it on the Improvement Suggestions board and keeps Admin Concerns off it.
     */
    public function storeSubmission(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isOperational(), 403);

        $validated = $request->validate([
            'date_identified' => ['required', 'date'],
            'challenge' => ['required', 'string', 'max:5000'],
            'recommended_solution' => ['required', 'string', 'max:5000'],
            'target_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['type'] = KaizenConcern::TYPE_EMPLOYEE_SUGGESTION;
        $validated['created_by'] = auth()->id();
        $validated['status'] = KaizenConcern::STATUS_PENDING;
        $validated['assigned_staff_id'] = null;

        $concern = KaizenConcern::create($validated);

        $this->logActivity($concern, 'admin.kaizen_concern_submitted', 'Submitted Employee Suggestion');

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Thank you! Your improvement suggestion has been submitted.');
    }

    /**
     * The Admin Concern create path. It records the row as an Admin Concern so it
     * is kept out of the Improvement Suggestions board, where every row is meant
     * to be an employee suggestion.
     */
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

        $validated['type'] = KaizenConcern::TYPE_ADMIN_CONCERN;
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
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(KaizenConcern::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /* The status is not something that has to be picked by hand. Leaving it
           out keeps the record on whatever the implementation workflow already
           decided, and the displayed status is derived from the implementation
           state (see KaizenConcern::effectiveStatus) rather than from this
           field alone. */
        $status = $validated['status'] ?? $concern->status;
        unset($validated['status']);

        // Finalising as Implemented is what fills in the implementation date when
        // one is not supplied, so the board can always show "Implemented on:
        // <date>". This stays behind the admin-only guard above.
        if ($status === KaizenConcern::STATUS_IMPLEMENTED && empty($validated['implementation_date'])) {
            $validated['implementation_date'] = now()->format('Y-m-d');
        }

        $concern->update($validated + ['status' => $status]);

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

    /**
     * Implementation confirmation from the detail page.
     *
     * This is a shortcut for the existing admin-only edit workflow, not a new
     * permission: it carries the exact same `isAdmin()` guard as update(), so
     * employees and supervisors still cannot finalise a suggestion. It applies
     * the same rule as update() too — an implementation date is filled in when
     * the record is finalised as Implemented and none was supplied.
     *
     * Note that checklist completion deliberately does NOT call this. Finishing
     * the implementation tasks only means the work is ready to be confirmed;
     * an Admin still has to confirm it here.
     */
    public function implement(KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can confirm a Kaizen concern as implemented.');

        abort_if($concern->isImplemented(), 422, 'This suggestion is already implemented.');

        $concern->update([
            'status' => KaizenConcern::STATUS_IMPLEMENTED,
            'implementation_date' => $concern->implementation_date ?? now()->format('Y-m-d'),
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_concern_implemented',
            "Confirmed Kaizen concern #{$concern->id} as implemented."
        );

        return back()->with('status', 'Improvement confirmed as implemented.');
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
