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
