@extends('layouts.dashboard')

@section('title', 'Daily Accomplishment')

@section('content')
@php
    $weekLabel = $weekStart->format('M j') . ' — ' . $weekEnd->format('M j, Y');
    $isAdminUser = auth()->user()->role === \App\Models\User::ROLE_ADMIN;
    $statusOptions = [
        'pending' => 'Not Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'on_hold' => 'On Hold',
    ];
@endphp

<div class="page-head page-head-row">
    <div>
        <nav class="wp-crumbs" aria-label="Breadcrumb">
            <span>Work Plan</span>
            <svg class="w-4 h-4" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="is-current">Daily Accomplishment</span>
        </nav>
        <h1>Daily Accomplishment &mdash; {{ $weekLabel }}</h1>
        <p>Record the actual result for each planned task, including problems and proposed solutions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('work-plan.reference-board') }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Legend Board</a>
        <a href="{{ route('daily-journal.create') }}" class="btn btn-outline btn-sm">Daily Journal</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="wp-week-nav mb-3">
    <a href="{{ route('work-plan.accomplishment', ['week_start' => $previousWeek->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">Previous Week</a>
    <span class="wp-week-label">{{ $weekLabel }}</span>
    <a href="{{ route('work-plan.accomplishment', ['week_start' => $nextWeek->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">Next Week</a>
</div>

@if (! $editingEnabled)
    <div class="alert alert-warning py-2">
        Work plan planning is locked, but accomplishments can still be recorded.
    </div>
@endif

@foreach ($weekDates as $day)
    @php
        $dateStr = $day->toDateString();
        $dayTasks = $tasksByDate->get($dateStr, collect());
        $dayAdditional = $additionalByDate->get($dateStr, collect());
        $completed = $dayTasks->where('status', \App\Models\WorkPlanTask::STATUS_COMPLETED)->count();
    @endphp

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>{{ $day->format('l, M j, Y') }}</strong>
            <span class="text-muted small">{{ $completed }} / {{ $dayTasks->count() }} planned completed &middot; {{ $dayAdditional->count() }} additional/urgent</span>
        </div>
        <div class="card-body d-flex flex-column gap-3">
            <h6 class="text-uppercase text-muted small mb-0">Planned tasks</h6>

            @forelse ($dayTasks as $task)
                <div class="border rounded-3 p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            @if ($isAdminUser && $task->user)
                                <div class="small text-muted mb-1">{{ $task->user->name }}</div>
                            @endif
                            <div class="d-flex align-items-center gap-2">
                                @if ($task->legend_code)
                                    <span class="badge bg-primary">{{ $task->legend_code }}</span>
                                @endif
                                <strong>{{ $task->custom_title ?: $task->getLegendLabel() }}</strong>
                            </div>
                            @if ($task->description)
                                <p class="small text-muted mb-1 mt-1">{{ $task->description }}</p>
                            @endif
                            @if ($task->success_criteria)
                                <p class="small mb-0"><span class="text-muted">Success criteria:</span> {{ $task->success_criteria }}</p>
                            @endif
                            @if ($task->client_name || $task->client_code)
                                <p class="small mb-0"><span class="text-muted">Client:</span> {{ $task->client_name }} @if($task->client_code) ({{ $task->client_code }}) @endif</p>
                            @endif
                            @if ($task->original_work_date && $task->original_work_date->toDateString() !== $task->work_date->toDateString())
                                <p class="small text-warning-emphasis mb-0">
                                    Carried over from {{ $task->original_work_date->format('M j, Y') }}
                                    @if ($task->carried_over_at)
                                        on {{ $task->carried_over_at->format('M j, Y g:i A') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <span class="badge bg-{{ $task->status === \App\Models\WorkPlanTask::STATUS_COMPLETED ? 'success' : ($task->status === \App\Models\WorkPlanTask::STATUS_CANCELLED ? 'secondary' : ($task->status === \App\Models\WorkPlanTask::STATUS_RETURNED_FOR_REVISION ? 'danger' : 'warning text-dark')) }}">
                            {{ $task->statusLabel() }}
                        </span>
                    </div>

                    @if ($task->returned_reason)
                        <div class="alert alert-danger py-2 small">Returned: {{ $task->returned_reason }}</div>
                    @endif

                    <form method="POST" action="{{ route('work-plan.accomplishment.update', $task) }}" class="row g-2 align-items-end">
                        @csrf
                        @method('PUT')
                        <div class="col-12 col-md-3">
                            <label class="form-label small mb-1">Actual status</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                @endforeach
                                @if ($canEvaluate)
                                    <option value="returned_for_revision" @selected($task->status === 'returned_for_revision')>Returned for revision</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label small mb-1">Accomplishment notes</label>
                            <input type="text" name="completion_notes" value="{{ $task->completion_notes }}" class="form-control form-control-sm" maxlength="2000">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small mb-1">Evidence path / reference</label>
                            <input type="text" name="evidence_path" value="{{ $task->evidence_path }}" class="form-control form-control-sm" maxlength="255">
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label small mb-1">
                                Details @if ($task->requiresDetails())<span class="text-danger">*</span>@endif
                            </label>
                            <input type="text" name="details" value="{{ $task->details }}" class="form-control form-control-sm" maxlength="2000" placeholder="Required for T / T8 before completion">
                        </div>
                        @if ($canEvaluate)
                            <div class="col-12 col-md-8">
                                <label class="form-label small mb-1">Returned reason</label>
                                <input type="text" name="returned_reason" value="{{ $task->returned_reason }}" class="form-control form-control-sm" maxlength="1000">
                            </div>
                        @endif
                        <div class="col-12 col-md-4 ms-auto">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Save accomplishment</button>
                        </div>
                    </form>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="small">Problems &amp; solutions</strong>
                            <span class="text-muted small">{{ $task->problems->count() }} logged</span>
                        </div>

                        @if ($task->problems->isNotEmpty())
                            <ul class="list-unstyled mt-2 mb-2">
                                @foreach ($task->problems as $problem)
                                    <li class="border rounded-2 p-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div>{{ $problem->description }}</div>
                                                @if ($problem->proposed_solution)
                                                    <div class="small text-muted">Solution: {{ $problem->proposed_solution }}</div>
                                                @endif
                                                <div class="small text-muted">Logged by {{ $problem->reporter?->name ?? '—' }} &middot; {{ $problem->created_at?->format('M j, g:i A') }}</div>
                                            </div>
                                            @if ($problem->isResolved())
                                                <span class="badge bg-success">Resolved</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Open</span>
                                            @endif
                                        </div>
                                        @if (! $problem->isResolved())
                                            <form method="POST" action="{{ route('work-plan.accomplishment.problems.resolve', [$task, $problem]) }}" class="row g-2 mt-1">
                                                @csrf
                                                @method('PATCH')
                                                <div class="col-12 col-md-9">
                                                    <input type="text" name="proposed_solution" class="form-control form-control-sm" maxlength="2000" placeholder="Proposed solution (optional)">
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <button type="submit" class="btn btn-outline btn-sm w-100">Mark resolved</button>
                                                </div>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <details>
                            <summary class="small text-primary">Log a problem</summary>
                            <form method="POST" action="{{ route('work-plan.accomplishment.problems.store', $task) }}" class="row g-2 mt-1">
                                @csrf
                                <div class="col-12 col-md-6">
                                    <input type="text" name="description" class="form-control form-control-sm" maxlength="2000" placeholder="What problem was encountered?" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <input type="text" name="proposed_solution" class="form-control form-control-sm" maxlength="2000" placeholder="Proposed solution (optional)">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-outline btn-sm w-100">Log</button>
                                </div>
                            </form>
                        </details>
                    </div>
                </div>
            @empty
                <div class="alert alert-light border mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>No planned tasks were assigned for this date.</span>
                    <a href="{{ route('admin.daily-work-plan.index', ['week_start' => $weekStart->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">Set work plan</a>
                </div>
            @endforelse

            <hr class="my-1">

            <h6 class="text-uppercase text-muted small mb-0">Additional / urgent activities</h6>

            @foreach ($dayAdditional as $task)
                <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            @if ($isAdminUser && $task->user)
                                <div class="small text-muted mb-1">{{ $task->user->name }}</div>
                            @endif
                            <div class="d-flex align-items-center gap-2">
                                @if ($task->legend_code)
                                    <span class="badge bg-warning text-dark">{{ $task->legend_code }}</span>
                                @endif
                                <strong>{{ $task->custom_title ?: $task->getLegendLabel() }}</strong>
                            </div>
                            @if ($task->description)
                                <p class="small text-muted mb-0 mt-1">{{ $task->description }}</p>
                            @endif
                        </div>
                        <span class="badge bg-{{ $task->additional_rating !== null ? 'dark' : 'secondary' }}">
                            {{ $task->additional_rating !== null ? $task->additional_rating.'/10' : 'Not rated' }}
                        </span>
                    </div>

                    @if ($task->additional_rated_at)
                        <div class="small text-muted mt-1">
                            Rated by {{ $task->additionalRater?->name ?? '—' }} on {{ $task->additional_rated_at->format('M j, Y g:i A') }}
                        </div>
                    @endif

                    @if ($canEvaluate)
                        <form method="POST" action="{{ route('work-plan.additional.rate', $task) }}" class="row g-2 align-items-end mt-2">
                            @csrf
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-1">Rating (out of 10)</label>
                                <input type="number" name="additional_rating" class="form-control form-control-sm" min="0" max="10" step="1"
                                       value="{{ old('additional_rating', $task->additional_rating ?? 9) }}" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <button type="submit" class="btn btn-outline btn-sm w-100">Save rating</button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach

            <details class="mt-1">
                <summary class="small text-primary">Log additional / urgent activity</summary>
                <form method="POST" action="{{ route('work-plan.additional.store') }}" class="row g-2 mt-2">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $dateStr }}">
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Activity code</label>
                        <select name="legend_code" class="form-select form-select-sm">
                            <option value="">None / custom</option>
                            @foreach ($legends as $legend)
                                <option value="{{ $legend->code }}">{{ $legend->code }} - {{ $legend->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Title (if no code)</label>
                        <input type="text" name="custom_title" class="form-control form-control-sm" maxlength="255" placeholder="Urgent activity title">
                    </div>
                    @if ($canSelectStaff)
                        <div class="col-12 col-md-3">
                            <label class="form-label small mb-1">Employee</label>
                            <select name="user_id" class="form-select form-select-sm">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-outline btn-sm w-100">Log activity</button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="description" class="form-control form-control-sm" maxlength="1000" placeholder="Description (optional)">
                    </div>
                </form>
            </details>
        </div>
    </div>
@endforeach
@endsection
