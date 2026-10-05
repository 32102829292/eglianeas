<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBookkeepingWorkflow;
use App\Http\Controllers\Controller;
use App\Models\QuarterlyBookkeeping;
use App\Models\QuarterlyBookkeepingTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quarterly Bookkeeping — the Weekly Bookkeeping workflow with the calendar
 * quarter as the planning unit.
 *
 * The methods here stay deliberately thin: they exist so Laravel's route model
 * binding can resolve `{bookkeeping}` and `{target}` with real types, and then
 * hand straight to the shared workflow in HandlesBookkeepingWorkflow.
 */
class QuarterlyBookkeepingController extends Controller
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

    public function show(QuarterlyBookkeeping $bookkeeping): View
    {
        return view('admin.bookkeeping.show', $this->showData($bookkeeping));
    }

    public function history(QuarterlyBookkeeping $bookkeeping): View
    {
        return view('admin.bookkeeping.history', $this->historyData($bookkeeping));
    }

    public function updateOwner(Request $request, QuarterlyBookkeeping $bookkeeping): RedirectResponse
    {
        return $this->changeOwner($request, $bookkeeping);
    }

    public function updateTarget(Request $request, QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->editTarget($request, $bookkeeping, $target);
    }

    public function startTarget(QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleStartTarget($bookkeeping, $target);
    }

    public function completeTarget(Request $request, QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleCompleteTarget($request, $bookkeeping, $target);
    }

    public function reassignTarget(Request $request, QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleReassignTarget($request, $bookkeeping, $target);
    }

    public function uploadAttachment(Request $request, QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleUploadAttachment($request, $bookkeeping, $target);
    }

    public function replaceAttachment(Request $request, QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleReplaceAttachment($request, $bookkeeping, $target);
    }

    public function downloadAttachment(QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): StreamedResponse
    {
        return $this->handleDownloadAttachment($bookkeeping, $target);
    }

    public function viewAttachment(QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->handleViewAttachment($bookkeeping, $target);
    }

    public function destroyTarget(QuarterlyBookkeeping $bookkeeping, QuarterlyBookkeepingTarget $target): RedirectResponse
    {
        return $this->removeTarget($bookkeeping, $target);
    }

    public function destroy(QuarterlyBookkeeping $bookkeeping): RedirectResponse
    {
        return $this->deletePlan($bookkeeping);
    }

    /** @return class-string<QuarterlyBookkeeping> */
    protected function planClass(): string
    {
        return QuarterlyBookkeeping::class;
    }

    /** @return class-string<QuarterlyBookkeepingTarget> */
    protected function targetClass(): string
    {
        return QuarterlyBookkeepingTarget::class;
    }
}
