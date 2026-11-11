<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KaizenConcern;
use App\Models\KaizenEvidence;
use App\Models\Notification;
use App\Models\StaffTaskEvaluation;
use App\Models\User;
use App\Services\AchievementAnalysisService;
use App\Services\PushNotificationService;
use App\Services\StaffScoringService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KaizenConcernController extends Controller
{
    public function __construct(
        private StaffScoringService $scoringService,
        private AchievementAnalysisService $assessmentService,
    ) {}
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
            'admin.kaizen-concerns.index',
            KaizenConcern::TYPE_EMPLOYEE_SUGGESTION
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
     * Admin Concerns are need-to-know: Admins see every record, while Staff and
     * Supervisors only see the records assigned to them. That filtering happens
     * through the shared concernBoard()/visibleTo() rules below.
     */
    public function adminConcerns(Request $request): View
    {
        return $this->concernBoard(
            $request,
            fn (Builder $query) => $query->adminConcerns(),
            'admin.kaizen-concerns.admin-concerns',
            KaizenConcern::TYPE_ADMIN_CONCERN
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
    private function concernBoard(Request $request, Closure $typeScope, string $view, ?string $type = null): View
    {
        $user = auth()->user();
        $type ??= KaizenConcern::TYPE_EMPLOYEE_SUGGESTION;
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');
        $assignedStaffId = $request->get('assigned_staff_id');

        // Type scope + per-user visibility. Both the summary cards and the
        // paginated table are built from this one query so they can never
        // disagree about which records belong to the board.
        $base = $typeScope(KaizenConcern::query())->visibleTo($user);

        // Summary statistics span the whole board (not just the current page,
        // and not just the active filter), otherwise the cards would silently
        // shrink to the first page of results.
        $statRows = (clone $base)->get(['status', 'implementation_date', 'type']);
        $statusCounts = $statRows->countBy(fn ($concern) => $concern->effectiveStatus())->all();
        $statTotal = $statRows->count();

        $query = clone $base;

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

        $concerns = $query
            ->with(['assignedStaff', 'creator', 'evidences'])
            ->withCount('evidences')
            ->orderByDesc('date_identified')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view($view, [
            'concerns' => $concerns,
            'staffAccounts' => $this->assignableAccounts($type),
            'statuses' => KaizenConcern::statusesFor($type),
            'q' => $q,
            'activeStatus' => $status,
            'activeAssignedStaffId' => $assignedStaffId,
            'hasFilters' => $q !== '' || ! empty($status) || ! empty($assignedStaffId),
            'statusCounts' => $statusCounts,
            'statTotal' => $statTotal,
            'isStaffView' => ! $user->isAdmin(),
            'canAssign' => $user->isAdmin() || $user->isSupervisor(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create Kaizen concerns.');

        return view('admin.kaizen-concerns.create', [
            'staffAccounts' => $this->assignableAccounts(KaizenConcern::TYPE_ADMIN_CONCERN),
            'statuses' => KaizenConcern::ADMIN_CONCERN_STATUSES,
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

        // Guard against an accidental double submit (e.g. a double click or a
        // page refresh) creating two identical suggestions within a minute.
        $duplicate = KaizenConcern::query()
            ->where('type', KaizenConcern::TYPE_EMPLOYEE_SUGGESTION)
            ->where('created_by', auth()->id())
            ->where('challenge', $validated['challenge'])
            ->where('recommended_solution', $validated['recommended_solution'])
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['challenge' => 'This suggestion was just submitted. Please wait a moment before submitting it again.']);
        }

        $concern = KaizenConcern::create($validated);

        $this->logActivity($concern, 'admin.kaizen_concern_submitted', 'Submitted Employee Suggestion');

        // Automatically create the performance evaluation for the submitter.
        $this->scoringService->evaluateKaizenSubmission($concern);

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
            'assigned_staff_id' => $this->assignmentRules(KaizenConcern::TYPE_ADMIN_CONCERN),
            'status' => ['required', 'string', $this->adminStatusRule()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['type'] = KaizenConcern::TYPE_ADMIN_CONCERN;
        $validated['created_by'] = auth()->id();
        $validated['status'] = $this->normaliseAdminStatus($validated['status']);

        if ($validated['status'] === KaizenConcern::STATUS_RESOLVED) {
            $validated['implementation_date'] = $validated['implementation_date'] ?? now()->format('Y-m-d');
            $validated['resolved_at'] = now();
            $validated['resolved_by'] = auth()->id();
        } else {
            unset($validated['resolution_notes']);
        }

        $concern = KaizenConcern::create($validated);

        $this->notifyAssignment($concern);
        $this->logActivity($concern, 'admin.kaizen_concern_created', 'Created Kaizen concern');

        // If this Admin Concern is assigned to a staff member, create the
        // evaluation record so the assigned employee can track their score.
        if ($concern->isAdminConcern() && $concern->assigned_staff_id) {
            if ($concern->isResolved()) {
                $this->scoringService->evaluateAdminConcernCompletion($concern);
            } else {
                $this->scoringService->evaluateAdminConcernCreated($concern);
            }
        }

        return redirect()->route('admin.kaizen-concerns.admin-concerns.index')->with('status', 'Kaizen concern created.');
    }

    public function show(Request $request, KaizenConcern $concern): View
    {
        abort_unless($concern->isVisibleTo(auth()->user()), 403);

        $relations = [
            'assignedStaff',
            'creator',
            'resolvedBy',
            'evidences.uploader',
            'progressUpdates.submitter',
            'progressUpdates.evidences.uploader',
        ];

        // The checklist only belongs to the Employee Suggestion workflow.
        if (! $concern->isAdminConcern()) {
            $relations[] = 'checklistItems';
        }

        $concern->load($relations);

        // Load the evaluation for this source. Visibility has already been
        // checked above, so an administrator inspecting the task sees the same
        // Score Sheet integration state the evaluated staff member sees.
        $user = auth()->user();
        $sourceType = $concern->isAdminConcern()
            ? StaffTaskEvaluation::SOURCE_ADMIN_CONCERN
            : StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN;

        $evaluation = StaffTaskEvaluation::forSource($sourceType, $concern->id)
            ->with(['evaluationPeriod', 'evaluatedBy'])
            ->first();

        $isEvaluationOwner = $evaluation !== null
            && (int) $evaluation->staff_id === (int) $user->id;

        $backUrl = $this->resolveBoardBackUrl($request);
        // When no explicit back parameter, default to the correct board
        // based on the concern type
        if (! $request->has('back')) {
            $backUrl = $concern->isAdminConcern()
                ? route('admin.kaizen-concerns.admin-concerns.index')
                : route('admin.kaizen-concerns.index');
        }
        $adminBoardPath = rtrim(parse_url(route('admin.kaizen-concerns.admin-concerns.index'), PHP_URL_PATH) ?? '', '/');

        return view('admin.kaizen-concerns.show', [
            'concern' => $concern,
            'staffAccounts' => $this->assignableAccounts($concern->type ?? KaizenConcern::TYPE_EMPLOYEE_SUGGESTION),
            'backUrl' => $backUrl,
            'backLabel' => rtrim(parse_url($backUrl, PHP_URL_PATH) ?? '', '/') === $adminBoardPath
                ? 'Back to Admin Concerns Board'
                : 'Back to Employee Improvement Board',
            'evaluation' => $evaluation,
            'isEvaluationOwner' => $isEvaluationOwner,
            'statuses' => KaizenConcern::statusesFor($concern->type),
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
            'staffAccounts' => $this->assignableAccounts($concern->type ?? KaizenConcern::TYPE_EMPLOYEE_SUGGESTION),
            'statuses' => KaizenConcern::statusesFor($concern->type ?? KaizenConcern::TYPE_EMPLOYEE_SUGGESTION),
        ]);
    }

    public function update(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can update Kaizen concerns.');

        $wasAssigned = $concern->assigned_staff_id;
        $isAdminConcern = $concern->isAdminConcern();
        $previousStatus = $concern->effectiveStatus();

        $validated = $request->validate([
            'date_identified' => ['required', 'date'],
            'challenge' => ['required', 'string', 'max:5000'],
            'recommended_solution' => ['required', 'string', 'max:5000'],
            'target_date' => ['nullable', 'date'],
            'implementation_date' => ['nullable', 'date'],
            'assigned_staff_id' => $this->assignmentRules($concern->type ?? KaizenConcern::TYPE_EMPLOYEE_SUGGESTION),
            'status' => ['nullable', 'string', $isAdminConcern
                ? $this->adminStatusRule()
                : Rule::in(array_keys(KaizenConcern::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        /* The status is not something that has to be picked by hand. Leaving it
           out keeps the record on whatever the implementation workflow already
           decided, and the displayed status is derived from the implementation
           state (see KaizenConcern::effectiveStatus) rather than from this
           field alone. */
        $status = $validated['status'] ?? $concern->status;
        unset($validated['status']);

        $resolutionNotesProvided = $request->exists('resolution_notes');
        $resolutionNotes = $validated['resolution_notes'] ?? null;
        unset($validated['resolution_notes']);

        if ($isAdminConcern) {
            $status = $this->normaliseAdminStatus($status);

            if ($resolutionNotesProvided) {
                $validated['resolution_notes'] = $resolutionNotes;
            }

            if ($status === KaizenConcern::STATUS_RESOLVED) {
                $validated['implementation_date'] = $validated['implementation_date']
                    ?? $concern->implementation_date?->format('Y-m-d')
                    ?? now()->format('Y-m-d');
                $validated['resolved_at'] = $concern->resolved_at ?? now();
                $validated['resolved_by'] = $concern->resolved_by ?? auth()->id();
            } elseif ($previousStatus === KaizenConcern::STATUS_RESOLVED
                && $status !== KaizenConcern::STATUS_CLOSED) {
                // Reopening removes the resolution stamp but keeps the audit
                // trail and any resolution notes already written.
                $validated['resolved_at'] = null;
                $validated['resolved_by'] = null;
            }
        } else {
            // Finalising as Implemented is what fills in the implementation date
            // when one is not supplied, so the board can always show
            // "Implemented on: <date>". This stays behind the admin-only guard.
            if ($status === KaizenConcern::STATUS_IMPLEMENTED && empty($validated['implementation_date'])) {
                $validated['implementation_date'] = now()->format('Y-m-d');
            }
        }

        $concern->update($validated + ['status' => $status]);

        $sourceType = $isAdminConcern
            ? StaffTaskEvaluation::SOURCE_ADMIN_CONCERN
            : StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN;
        $evaluation = StaffTaskEvaluation::forSource($sourceType, $concern->id)->first();

        if ($isAdminConcern) {
            if ($status === KaizenConcern::STATUS_RESOLVED
                && $previousStatus !== KaizenConcern::STATUS_RESOLVED) {
                $this->scoringService->evaluateAdminConcernCompletion($concern);
            } elseif ($previousStatus === KaizenConcern::STATUS_RESOLVED
                && ! in_array($status, [KaizenConcern::STATUS_RESOLVED, KaizenConcern::STATUS_CLOSED], true)
                && $evaluation !== null
                && in_array($evaluation->evaluation_status, [
                    StaffTaskEvaluation::STATUS_FINALIZED,
                    StaffTaskEvaluation::STATUS_ADJUSTED,
                ], true)) {
                // Reopened: the score leaves the Score Sheet while the
                // evaluation and its history stay on record.
                $this->scoringService->reverseEvaluation(
                    $evaluation,
                    'Admin Concern reopened by an administrator.',
                    auth()->id()
                );
            }
        } elseif ($status === KaizenConcern::STATUS_IMPLEMENTED) {
            $this->scoringService->evaluateKaizenImplementation($concern);
        } elseif ($evaluation !== null
            && in_array($evaluation->evaluation_status, [
                StaffTaskEvaluation::STATUS_FINALIZED,
                StaffTaskEvaluation::STATUS_ADJUSTED,
            ], true)) {
            // Reopened / no longer implemented: the score must leave the Score
            // Sheet, but the evaluation and its history stay on record.
            $this->scoringService->reverseEvaluation(
                $evaluation,
                'Task reopened or implementation removed by an administrator.',
                auth()->id()
            );
        }

        if ($concern->isAdminConcern() && $evaluation !== null && (int) $concern->assigned_staff_id !== (int) $wasAssigned) {
            $this->scoringService->reassignEvaluation(
                $evaluation->refresh(),
                $concern->assigned_staff_id,
                auth()->id(),
                'Admin Concern assignment changed.'
            );
        }

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

        return redirect()->route($concern->isAdminConcern()
            ? 'admin.kaizen-concerns.admin-concerns.index'
            : 'admin.kaizen-concerns.index')
            ->with('status', 'Kaizen concern updated.');
    }

    public function assignStaff(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canAssignStaff(auth()->user()), 403, 'Only admins and supervisors can assign staff.');

        $validated = $request->validate([
            'assigned_staff_id' => $this->assignmentRules($concern->type ?? KaizenConcern::TYPE_EMPLOYEE_SUGGESTION),
        ]);

        $previous = $concern->assigned_staff_id;
        $assignedId = $validated['assigned_staff_id'] ?? null;
        $concern->update(['assigned_staff_id' => $assignedId]);

        // Keep the evaluation linked to whoever owns the Admin Concern. If the
        // assignment is cleared, the evaluation is excluded from the Score
        // Sheet instead of staying with the wrong staff member.
        if ($concern->isAdminConcern() && (int) $previous !== (int) $assignedId) {
            $evaluation = StaffTaskEvaluation::forSource(
                StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
                $concern->id
            )->first();

            if ($evaluation !== null) {
                $this->scoringService->reassignEvaluation(
                    $evaluation,
                    $assignedId,
                    auth()->id(),
                    'Admin Concern assignment changed.'
                );
            } elseif ($assignedId !== null) {
                $this->scoringService->evaluateAdminConcernCreated($concern);
            }
        }

        if ((int) $previous !== (int) $assignedId) {
            if ($assignedId) {
                $this->notifyAssignment($concern);
            }

            $action = ! $assignedId
                ? 'admin.kaizen_staff_unassigned'
                : ($previous ? 'admin.kaizen_staff_reassigned' : 'admin.kaizen_staff_assigned');
            $description = ! $assignedId
                ? "Cleared the staff assignment for Kaizen concern #{$concern->id}."
                : ($previous
                    ? "Reassigned Kaizen concern #{$concern->id} from staff #{$previous} to staff #{$assignedId}."
                    : "Assigned Kaizen concern #{$concern->id} to staff #{$assignedId}.");

            ActivityLog::record(auth()->user(), $action, $description);
        }

        $message = $assignedId ? 'Staff assignment saved.' : 'Staff assignment cleared.';
        $response = $concern->isVisibleTo(auth()->user())
            ? back()
            : redirect()->route($concern->isAdminConcern()
                ? 'admin.kaizen-concerns.admin-concerns.index'
                : 'admin.kaizen-concerns.index');

        return $response->with('status', $message);
    }

    public function destroy(KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can delete Kaizen concerns.');

        $concern->delete();

        ActivityLog::record(auth()->user(), 'admin.kaizen_concern_deleted', 'Deleted a Kaizen concern record.');

        return redirect()->route($concern->isAdminConcern()
            ? 'admin.kaizen-concerns.admin-concerns.index'
            : 'admin.kaizen-concerns.index')
            ->with('status', 'Kaizen concern deleted.');
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

        abort_if($concern->isAdminConcern(), 422, 'Admin Concerns are resolved, not implemented.');

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

        // Automatically evaluate the implementation for performance scoring.
        $this->scoringService->evaluateKaizenImplementation($concern);

        return redirect()->route($concern->isAdminConcern()
            ? 'admin.kaizen-concerns.admin-concerns.index'
            : 'admin.kaizen-concerns.index')
            ->with('status', 'Improvement confirmed as implemented.');
    }

    /**
     * Record progress on an Admin Concern. This replaces the checklist workflow
     * for Admin Concerns: the assigned employee (or an Admin) describes what was
     * done, the current situation, any remaining issues, and may attach optional
     * evidence. Employee Suggestions keep using checklist items.
     */
    public function storeProgressUpdate(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->isAdminConcern(), 404);
        abort_unless($concern->canSubmitProgressUpdate(auth()->user()), 403, 'Only the assigned staff member or an admin can add progress updates.');

        $validated = $request->validate([
            'actions_taken' => ['required', 'string', 'max:5000'],
            'current_situation' => ['required', 'string', 'max:5000'],
            'remaining_issues' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $update = $concern->progressUpdates()->create([
            'submitted_by' => auth()->id(),
            'actions_taken' => $validated['actions_taken'],
            'current_situation' => $validated['current_situation'],
            'remaining_issues' => $validated['remaining_issues'] ?? null,
        ]);

        if ($request->hasFile('evidence')) {
            $file = $validated['evidence'];

            $update->evidences()->create([
                'kaizen_concern_id' => $concern->id,
                'uploaded_by' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('kaizen-evidence', 'local'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($concern->status === KaizenConcern::STATUS_PENDING) {
            $concern->update(['status' => KaizenConcern::STATUS_IN_PROGRESS]);
        }

        // Trigger AI-assisted assessment for the Admin Concern so the employee's
        // progress update is evaluated immediately rather than waiting for
        // resolution. Stored as provisional pending admin review.
        $concern->refresh();
        $assessment = $this->assessmentService->assessAdminConcern($concern, true);

        $evaluation = StaffTaskEvaluation::forSource(
            StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
            $concern->id
        )->first();

        if ($evaluation === null) {
            $staff = $concern->assignedStaff ?? $concern->creator;
            $evaluation = StaffTaskEvaluation::create([
                'staff_id' => $staff->id,
                'source_type' => StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
                'source_id' => $concern->id,
                'evaluation_period_id' => optional($this->scoringService->currentPeriod())->id,
                'evaluation_status' => StaffTaskEvaluation::STATUS_PROVISIONAL,
                'submitted_at' => $concern->created_at,
                'deadline_at' => $concern->target_date,
            ]);
        }

        $evaluation->update([
            'accomplishment_quality_score' => $assessment['score'],
            'timeliness_score' => $assessment['score'],
            'immediate_responsiveness_score' => $assessment['score'],
            'client_feedback_score' => null,
            'overall_score' => $assessment['score'],
            'performance_grade' => StaffTaskEvaluation::gradeForScore($assessment['score']),
            'evaluation_status' => StaffTaskEvaluation::STATUS_PROVISIONAL,
            'assessment_source' => $assessment['source'],
            'original_ai_score' => $evaluation->original_ai_score ?? $assessment['score'],
            'assessment_explanation' => $assessment['explanation'],
            'assessed_at' => now(),
            'evaluated_by' => auth()->id(),
            'adjustment_reason' => 'AI-assisted assessment generated after progress update.',
        ]);

        $this->logActivity(
            $concern,
            'admin.kaizen_progress_update_added',
            "Added a progress update to Admin Concern #{$concern->id} with AI assessment (score: {$assessment['score']})."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Progress update submitted with AI assessment (provisional, awaiting admin review).');
    }

    /**
     * Mark an Admin Concern as Resolved. Resolution notes are required so the
     * decision is always auditable, and the resolution date/person are recorded.
     * The existing Admin Concern scoring workflow is triggered on resolution so
     * the staff Score Sheet stays in sync. The concern update and evaluation
     * are wrapped in a transaction so a scoring failure never leaves the
     * concern marked Resolved without a completed evaluation.
     */
    public function resolve(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canResolve(auth()->user()), 403, 'Only admins can resolve an Admin Concern.');

        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'resolution_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = trim((string) ($validated['resolution_notes'] ?? $validated['resolution_summary'] ?? ''));

        if ($notes === '') {
            return back()
                ->withInput()
                ->withErrors(['resolution_notes' => 'Resolution notes are required when marking an Admin Concern as resolved.']);
        }

        $wasResolved = $concern->isResolved();

        // Wrap the concern update and AI assessment in a transaction so that
        // either both succeed or neither leaves partial state behind.
        \Illuminate\Support\Facades\DB::transaction(function () use ($concern, $notes, $wasResolved) {
            $concern->update([
                'status' => KaizenConcern::STATUS_RESOLVED,
                'resolution_notes' => $notes,
                'resolved_at' => $concern->resolved_at ?? now(),
                'resolved_by' => $concern->resolved_by ?? auth()->id(),
                'implementation_date' => $concern->implementation_date ?? now()->format('Y-m-d'),
            ]);

            if (! $wasResolved) {
                // Generate AI-assisted assessment including resolution info.
                $concern->refresh();
                $assessment = $this->assessmentService->assessAdminConcern($concern, true);

                $evaluation = StaffTaskEvaluation::forSource(
                    StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
                    $concern->id
                )->first();

                if ($evaluation === null) {
                    $staff = $concern->assignedStaff ?? $concern->creator;
                    $evaluation = StaffTaskEvaluation::create([
                        'staff_id' => $staff->id,
                        'source_type' => StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
                        'source_id' => $concern->id,
                        'evaluation_period_id' => optional($this->scoringService->currentPeriod())->id,
                        'evaluation_status' => StaffTaskEvaluation::STATUS_FINALIZED,
                        'submitted_at' => $concern->created_at,
                        'deadline_at' => $concern->target_date,
                    ]);
                }

                $evaluation->update([
                    'accomplishment_quality_score' => $assessment['score'],
                    'timeliness_score' => $assessment['score'],
                    'immediate_responsiveness_score' => $assessment['score'],
                    'client_feedback_score' => null,
                    'overall_score' => $assessment['score'],
                    'performance_grade' => StaffTaskEvaluation::gradeForScore($assessment['score']),
                    'evaluation_status' => StaffTaskEvaluation::STATUS_FINALIZED,
                    'assessment_source' => $assessment['source'],
                    'original_ai_score' => $evaluation->original_ai_score ?? $assessment['score'],
                    'assessment_explanation' => $assessment['explanation'],
                    'assessed_at' => now(),
                    'finalized_at' => now(),
                    'evaluated_by' => auth()->id(),
                    'completed_at' => $concern->implementation_date ? \Carbon\Carbon::parse($concern->implementation_date) : null,
                    'adjustment_reason' => 'AI-assisted assessment on Admin Concern resolution.',
                ]);

                $this->scoringService->synchroniseScoreSheet(
                    $evaluation,
                    auth()->id(),
                    'AI-assisted assessment finalized on Admin Concern resolution.'
                );
            }
        });

        $this->logActivity(
            $concern,
            'admin.kaizen_concern_resolved',
            "Marked Admin Concern #{$concern->id} as resolved with AI assessment."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Admin Concern marked as resolved with AI assessment.');
    }

    /**
     * Close an Admin Concern without treating it as a successful resolution.
     * Closing an already-resolved concern preserves its resolution details.
     * Closure notes are required; the implementation date is deliberately not
     * set because closing is not a successful resolution.
     */
    public function close(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless($concern->canClose(auth()->user()), 403, 'Only admins can close an Admin Concern.');

        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'resolution_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $notes = trim((string) ($validated['resolution_notes'] ?? $validated['resolution_summary'] ?? ''));

        if ($notes === '') {
            return back()
                ->withInput()
                ->withErrors(['resolution_notes' => 'Closure notes are required when closing an Admin Concern without resolution.']);
        }

        $concern->update([
            'status' => KaizenConcern::STATUS_CLOSED,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $this->logActivity(
            $concern,
            'admin.kaizen_concern_closed',
            "Closed Admin Concern #{$concern->id}."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Admin Concern closed without resolution.');
    }

    /**
     * Trigger an AI-assisted assessment for this Admin Concern and store the
     * preliminary results as a StaffTaskEvaluation. Used when an employee
     * submits a progress update or when an admin requests reassessment.
     *
     * The assessment is stored with STATUS_PROVISIONAL so it is clearly
     * distinguishable from a finalized, admin-approved score.
     */
    public function storeAssessment(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can trigger assessments.');
        abort_unless($concern->isAdminConcern(), 404);

        $assessment = $this->assessmentService->assessAdminConcern($concern, true);

        $evaluation = StaffTaskEvaluation::forSource(
            StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
            $concern->id
        )->first();

        $isNew = $evaluation === null;
        if ($isNew) {
            $staff = $concern->assignedStaff ?? $concern->creator;
            $evaluation = StaffTaskEvaluation::create([
                'staff_id' => $staff->id,
                'source_type' => StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
                'source_id' => $concern->id,
                'evaluation_period_id' => $this->scoringService instanceof \App\Services\StaffScoringService
                    ? $this->scoringService->currentPeriod()?->id
                    : null,
                'evaluation_status' => StaffTaskEvaluation::STATUS_PROVISIONAL,
                'submitted_at' => $concern->created_at,
                'deadline_at' => $concern->target_date,
            ]);
        }

        $evaluation->update([
            'accomplishment_quality_score' => $assessment['score'],
            'timeliness_score' => $assessment['score'],
            'immediate_responsiveness_score' => $assessment['score'],
            'client_feedback_score' => null,
            'overall_score' => $assessment['score'],
            'performance_grade' => StaffTaskEvaluation::gradeForScore($assessment['score']),
            'evaluation_status' => StaffTaskEvaluation::STATUS_PROVISIONAL,
            'assessment_source' => $assessment['source'],
            'original_ai_score' => $evaluation->original_ai_score ?? $assessment['score'],
            'assessment_explanation' => $assessment['explanation'],
            'assessed_at' => now(),
            'evaluated_by' => auth()->id(),
            'adjustment_reason' => 'AI-assisted assessment generated on '.(
                $isNew ? 'initial assessment' : 'reassessment'
            ),
        ]);

        $this->logActivity(
            $concern,
            'admin.kaizen_assessment_generated',
            "AI assessment generated for Admin Concern #{$concern->id} (score: {$assessment['score']})."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'AI assessment generated (provisional, awaiting admin review).');
    }

    /**
     * Admin adjusts criterion scores with a required reason. Sets the
     * evaluation to STATUS_ADJUSTED and re-synchronizes the Score Sheet.
     */
    public function adjustAssessment(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can adjust assessments.');

        $evaluation = StaffTaskEvaluation::forSource(
            StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
            $concern->id
        )->first();

        if ($evaluation === null) {
            return back()->withErrors(['evaluation' => 'No evaluation exists for this concern.']);
        }

        $validated = $request->validate([
            'overall_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'adjustment_reason' => ['required', 'string', 'max:1000'],
        ]);

        $score = (float) $validated['overall_score'];

        $evaluation->update([
            'accomplishment_quality_score' => $score,
            'timeliness_score' => $score,
            'immediate_responsiveness_score' => $score,
            'client_feedback_score' => null,
            'overall_score' => $score,
            'performance_grade' => StaffTaskEvaluation::gradeForScore($score),
            'evaluation_status' => StaffTaskEvaluation::STATUS_ADJUSTED,
            'evaluated_by' => auth()->id(),
            'finalized_at' => now(),
            'adjustment_reason' => $validated['adjustment_reason'],
        ]);

        $this->scoringService->synchroniseScoreSheet(
            $evaluation,
            auth()->id(),
            'Admin adjusted Admin Concern assessment scores.'
        );

        $this->logActivity(
            $concern,
            'admin.kaizen_assessment_adjusted',
            "Adjusted assessment for Admin Concern #{$concern->id} to score {$score}."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Assessment adjusted and synchronized to Score Sheet.');
    }

    /**
     * Admin finalizes the evaluation and synchronizes it to the staff Score
     * Sheet. Only provisional or adjusted evaluations can be finalized.
     */
    public function finalizeAssessment(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can finalize assessments.');

        $evaluation = StaffTaskEvaluation::forSource(
            StaffTaskEvaluation::SOURCE_ADMIN_CONCERN,
            $concern->id
        )->first();

        if ($evaluation === null) {
            return back()->withErrors(['evaluation' => 'No evaluation exists for this concern.']);
        }

        if (! in_array($evaluation->evaluation_status, [
            StaffTaskEvaluation::STATUS_PROVISIONAL,
            StaffTaskEvaluation::STATUS_ADJUSTED,
        ], true)) {
            return back()->withErrors(['evaluation' => 'Only provisional or adjusted assessments can be finalized.']);
        }

        if ($evaluation->overall_score === null) {
            return back()->withErrors(['evaluation' => 'Cannot finalize an assessment with no score.']);
        }

        $evaluation->update([
            'evaluation_status' => StaffTaskEvaluation::STATUS_FINALIZED,
            'finalized_at' => now(),
            'evaluated_by' => auth()->id(),
        ]);

        $this->scoringService->synchroniseScoreSheet(
            $evaluation,
            auth()->id(),
            'Admin finalized Admin Concern assessment for Score Sheet inclusion.'
        );

        $this->logActivity(
            $concern,
            'admin.kaizen_assessment_finalized',
            "Finalized assessment for Admin Concern #{$concern->id} (score: {$evaluation->overall_score})."
        );

        return redirect()->route('admin.kaizen-concerns.show', $concern)
            ->with('status', 'Assessment finalized and added to Score Sheet.');
    }

    /**
     * Reverse a Kaizen/Admin Concern evaluation. The score is removed from the
     * staff Score Sheet immediately, the badge becomes REVERSED / EXCLUDED,
     * and the evaluation plus its audit trail are preserved.
     */
    public function reverseEvaluation(Request $request, KaizenConcern $concern): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can reverse an evaluation.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $sourceType = $concern->isAdminConcern()
            ? StaffTaskEvaluation::SOURCE_ADMIN_CONCERN
            : StaffTaskEvaluation::SOURCE_KAIZEN_CONCERN;

        $evaluation = StaffTaskEvaluation::forSource($sourceType, $concern->id)->first();

        if ($evaluation === null) {
            return back()->withErrors(['evaluation' => 'This task does not have an evaluation to reverse.']);
        }

        if ($evaluation->evaluation_status === StaffTaskEvaluation::STATUS_REVERSED) {
            return back()->with('status', 'This evaluation is already excluded from the Score Sheet.');
        }

        $this->scoringService->reverseEvaluation($evaluation, $validated['reason'], auth()->id());

        ActivityLog::record(
            auth()->user(),
            'admin.kaizen_evaluation_reversed',
            "Reversed the performance evaluation for Kaizen concern #{$concern->id}. Reason: {$validated['reason']}"
        );

        return back()->with('status', 'Evaluation reversed and removed from the Score Sheet.');
    }

    public function toggleChecklistItem(Request $request, KaizenConcern $concern): RedirectResponse|JsonResponse
    {
        abort_unless($concern->isVisibleTo(auth()->user()), 403);
        abort_if($concern->isAdminConcern(), 403, 'Checklist is not used for Admin Concerns.');

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
        abort_if($concern->isAdminConcern(), 403, 'Checklist is not used for Admin Concerns.');

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
        abort_if($concern->isAdminConcern(), 403, 'Checklist is not used for Admin Concerns.');

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
        abort_if($concern->isAdminConcern(), 403, 'Checklist is not used for Admin Concerns.');

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
     * Admin Concerns accept the four new states plus the old stored values so
     * existing records can still be edited without breaking validation.
     */
    private function adminStatusRule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in(array_values(array_unique(array_merge(
            array_keys(KaizenConcern::ADMIN_CONCERN_STATUSES),
            array_keys(KaizenConcern::STATUSES)
        ))));
    }

    /**
     * Translate legacy Kaizen statuses into the Admin Concern workflow.
     */
    private function normaliseAdminStatus(string $status): string
    {
        return match ($status) {
            KaizenConcern::STATUS_RESOLVED, KaizenConcern::STATUS_IMPLEMENTED => KaizenConcern::STATUS_RESOLVED,
            KaizenConcern::STATUS_CLOSED, KaizenConcern::STATUS_NOT_IMPLEMENTED => KaizenConcern::STATUS_CLOSED,
            KaizenConcern::STATUS_IN_PROGRESS, KaizenConcern::STATUS_OVERDUE => KaizenConcern::STATUS_IN_PROGRESS,
            default => KaizenConcern::STATUS_PENDING,
        };
    }

    /**
     * Admin Concerns may be owned by a Staff member or a Supervisor; Employee
     * Suggestions stay on the original staff-only list.
     */
    private function assignmentRules(string $type): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('users', 'id')->where(function ($query) use ($type) {
                $query->whereIn('role', $this->assignableRoles($type))
                    ->whereNull('deleted_at');
            }),
        ];
    }

    /**
     * Accounts offered in the assignment select. Shared by the list filter, the
     * create form and the detail form so all three stay in sync.
     */
    private function assignableAccounts(string $type): Collection
    {
        return User::query()
            ->whereIn('role', $this->assignableRoles($type))
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /**
     * Roles that may appear in an assignment dropdown.
     *
     * Employees are not all `staff`: supervisors (and admins) are operational
     * employee accounts too. Restricting Employee Suggestions to the `staff`
     * role was what left supervisors out of the dropdown, so every operational
     * role is eligible here. Clients and soft-deleted accounts are still
     * excluded by the query/validation rules.
     *
     * @return list<string>
     */
    private function assignableRoles(string $type): array
    {
        if ($type === KaizenConcern::TYPE_ADMIN_CONCERN) {
            return [User::ROLE_STAFF, User::ROLE_SUPERVISOR];
        }

        return [User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN];
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

    /**
     * Resolve the "back to board" target for the standalone details page.
     *
     * The boards append their current URL (filters + page) as `back`. Only a
     * same-origin URL that points at one of the two Kaizen boards is accepted,
     * so a crafted link can never turn this into an open redirect or a loop
     * back to the details page itself. Anything else falls back to the
     * Employee Improvement Board.
     */
    private function resolveBoardBackUrl(Request $request): string
    {
        $fallback = route('admin.kaizen-concerns.index');
        $candidate = $request->query('back');

        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return $fallback;
        }

        $scheme = $parts['scheme'] ?? $request->getScheme();
        $host = $parts['host'] ?? $request->getHost();

        if (! in_array($scheme, ['http', 'https'], true) || ! hash_equals($request->getHost(), $host)) {
            return $fallback;
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $allowedPaths = [
            rtrim(parse_url(route('admin.kaizen-concerns.index'), PHP_URL_PATH) ?? '', '/'),
            rtrim(parse_url(route('admin.kaizen-concerns.admin-concerns.index'), PHP_URL_PATH) ?? '', '/'),
        ];

        if (! in_array($path, $allowedPaths, true)) {
            return $fallback;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    private function logActivity(KaizenConcern $concern, string $action, string $description): void
    {
        ActivityLog::record(
            auth()->user(),
            $action,
            $description." (Challenge: {$concern->challenge})"
        );
    }
}
