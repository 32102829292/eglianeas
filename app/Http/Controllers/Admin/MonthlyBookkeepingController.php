<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBookkeepingWorkflow;
use App\Http\Controllers\Controller;
use App\Models\MonthlyBookkeeping;
use App\Models\MonthlyBookkeepingTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly Bookkeeping — the Weekly Bookkeeping workflow with the calendar
 * month as the planning unit.
 *
 * The methods here stay deliberately thin: they exist so Laravel's route model
 * binding can resolve `{bookkeeping}` and `{target}` with real types, and then
 * hand straight to the shared workflow in HandlesBookkeepingWorkflow.
 */
class MonthlyBookkeepingController extends Controller
{
    use HandlesBookkeepingWorkflow;

    public function index(Request $request): View
    {
        return view('admin.bookkeeping.index', $this->indexData($request));
    }

    public function report(Request $request): View
    {
        return view('admin.bookkeeping.report', $this->reportData($request));
    }

    public function create(Request $request): View
    {
        return view('admin.bookkeeping.create', $this->createData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->handleStore($request);
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        return $this->handleBulkAssign($request);
    }

    public function show(MonthlyBookkeeping $bookkeeping): View
    {
        return view('admin.bookkeeping.show', $this->showData($bookkeeping));
    }

    public function history(MonthlyBookkeeping $bookkeeping): View
    {
        return view('admin.bookkeeping.history', $this->historyData($bookkeeping));
    }

    public function updateOwner(Request $request, MonthlyBookkeeping $bookkeeping): RedirectResponse
    {
        return $this->changeOwner($request, $bookkeeping);
    }

    public function updateTarget(Request $request, MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->editTarget($request, $bookkeeping, $target);
    }

    public function startTarget(MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleStartTarget($bookkeeping, $target);
    }

    public function completeTarget(Request $request, MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleCompleteTarget($request, $bookkeeping, $target);
    }

    public function reassignTarget(Request $request, MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleReassignTarget($request, $bookkeeping, $target);
    }

    public function uploadAttachment(Request $request, MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleUploadAttachment($request, $bookkeeping, $target);
    }

    public function replaceAttachment(Request $request, MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleReplaceAttachment($request, $bookkeeping, $target);
    }

    public function downloadAttachment(MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): StreamedResponse
    {
        return $this->handleDownloadAttachment($bookkeeping, $target);
    }

    public function viewAttachment(MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleViewAttachment($bookkeeping, $target);
    }

    public function destroyTarget(MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->removeTarget($bookkeeping, $target);
    }

    public function destroy(MonthlyBookkeeping $bookkeeping): RedirectResponse
    {
        return $this->deletePlan($bookkeeping);
    }

    public function showTask(MonthlyBookkeeping $bookkeeping, MonthlyBookkeepingTarget $target): \Illuminate\Http\JsonResponse
    {
        $this->authorizeTarget($bookkeeping, $target);

        $target->loadMissing(['client', 'assignedStaff', 'performedBy', 'bookkeeping']);

        $user = auth()->user();
        $isOversight = $user->isAdmin() || $user->isSupervisor();
        $isOwnTask = $target->isAssignedTo($user);
        $canManage = $isOversight || $bookkeeping->isOwnedBy($user) || $isOwnTask;

        $history = $bookkeeping->history()
            ->with('user')
            ->latest()
            ->get()
            ->filter(function ($entry) use ($target) {
                $desc = (string) $entry->description;

                return str_contains($desc, $target->displayClientName())
                    || str_contains($desc, $target->taskLabel());
            })
            ->values()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'description' => $entry->description,
                    'user_name' => $entry->user?->name,
                    'created_at' => $entry->created_at?->format('M j, Y g:i A'),
                ];
            });

        $evidence = null;
        if ($target->attachment_path) {
            $evidence = [
                'name' => $target->attachment_name,
                'has' => true,
                'view_url' => route('admin.monthly-bookkeeping.view-attachment', [$bookkeeping, $target]),
                'download_url' => route('admin.monthly-bookkeeping.download-attachment', [$bookkeeping, $target]),
            ];
        }

        $prefix = 'admin.monthly-bookkeeping';

        return response()->json([
            'target' => [
                'id' => $target->id,
                'bookkeeping_id' => $bookkeeping->id,
                'client_name' => $target->displayClientName(),
                'task_type' => $target->task_type,
                'task_label' => $target->taskLabel(),
                'assigned_staff' => $target->assignedStaffDisplayName() ?: null,
                'target_date' => $target->target_date?->format('Y-m-d'),
                'target_date_label' => $target->target_date?->format('M j, Y'),
                'date_auto' => $target->dateIsAutomatic(),
                'status' => $target->actual_status,
                'status_label' => $target->effectiveStatusLabel(),
                'notes' => $target->notes,
                'started_at' => $target->started_at?->format('M j, Y g:i A'),
                'ended_at' => $target->ended_at?->format('M j, Y g:i A'),
                'performed_by' => $target->performedByDisplayName() ?: null,
                'performed_by_role' => $target->performed_by_role,
                'duration' => $target->durationHuman(),
                'timing_label' => $target->timingLabel(),
                'is_pending' => $target->isPending(),
                'is_in_progress' => $target->isInProgress(),
                'is_completed' => $target->isCompleted(),
                'is_payment' => $target->task_type === 'payment',
                'payment_method' => $target->payment_method,
                'payment_at' => $target->payment_at?->format('Y-m-d'),
                'payment_at_label' => $target->payment_at?->format('M j, Y'),
                'payment_label' => $target->paymentLabel(),
                'payment_detail' => $target->paymentDetail(),
                'can_manage' => $canManage,
                'can_reassign' => $isOversight,
            ],
            'evidence' => $evidence,
            'history' => $history,
            'routes' => [
                'start' => $target->isPending() && $canManage ? route($prefix.'.start-target', [$bookkeeping, $target]) : null,
                'complete' => $target->isInProgress() && $canManage ? route($prefix.'.complete-target', [$bookkeeping, $target]) : null,
                'update_target' => $canManage ? route($prefix.'.update-target', [$bookkeeping, $target]) : null,
                'reassign' => $isOversight ? route($prefix.'.reassign-target', [$bookkeeping, $target]) : null,
                'upload' => $canManage && $target->isInProgress() ? route($prefix.'.upload-attachment', [$bookkeeping, $target]) : null,
            ],
            'payment_methods' => $target::PAYMENT_METHODS ?? [],
            'assignable_staff' => $isOversight ? \App\Models\User::query()->whereIn('role', ['staff', 'admin'])->orderBy('name')->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values() : [],
        ]);
    }

    /** @return class-string<MonthlyBookkeeping> */
    protected function planClass(): string
    {
        return MonthlyBookkeeping::class;
    }

    /** @return class-string<MonthlyBookkeepingTarget> */
    protected function targetClass(): string
    {
        return MonthlyBookkeepingTarget::class;
    }
}
