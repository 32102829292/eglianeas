{{--
    Shared Kaizen board: filter panel + table + mobile card list + pagination.

    Rendered by both halves of the shared kaizen_concerns table so the two
    boards cannot drift apart:

      * the Improvement Suggestions board  (type = employee_suggestion)
      * the Admin Concerns board          (type = admin_concern)

    Expected from the including view (both come from KaizenConcernController's
    shared concernBoard()):

        $concerns              paginated KaizenConcerns for ONE type only
        $staffAccounts         staff list for the assignment filter
        $statuses              KaizenConcern::STATUSES
        $q                     active search string
        $activeStatus          active status filter
        $activeAssignedStaffId active assignee filter
        $hasFilters            whether any filter is applied

    Per-board presentation:

        $boardRoute       route name the filters submit to and "Clear" returns to
        $recordLabel      column/badge heading, e.g. "Employee Suggestion"
        $boardHeading     card heading above the table
        $emptyMessage     shown when the board has no rows and no filters
        $filteredEmpty    shown when filters excluded everything
        $pluralUnit       word used for the card-head count and plural()
        $showAssigned     true to give Admin Concerns their own "Assigned To"
                          column; when false the assignee stays a sub-line
                          under "Submitted By", which is the Employee
                          Suggestions layout
--}}

@php
    // Carried through the details and edit screens so "Back" returns to this
    // exact board view with its filters and page intact.
    $boardReturnUrl = $boardReturnUrl ?? request()->fullUrl();
@endphp

{{-- Filters --}}
<div class="card">
    <div class="card-head">
        <h2 class="card-title">Filter Concerns</h2>
    </div>
    <form method="GET" action="{{ route($boardRoute) }}" class="filter-panel">
        <div class="form-grid three align-items-end gap-3">
            <div class="form-group">
                <label class="form-label" for="filter_q">Search</label>
                <input class="form-control" id="filter_q" name="q" type="text" value="{{ $q }}" placeholder="Search challenge, solution, notes...">
            </div>
            <div class="form-group">
                <label class="form-label" for="filter_status">Status</label>
                <select class="form-control" id="filter_status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @unless ($isStaffView)
            <div class="form-group">
                <label class="form-label" for="filter_assigned_staff_id">{{ $showAssigned ? 'Assigned To' : 'Assigned Staff' }}</label>
                <select class="form-control" id="filter_assigned_staff_id" name="assigned_staff_id">
                    <option value="">All</option>
                    <option value="unassigned" @selected($activeAssignedStaffId === 'unassigned')>Unassigned</option>
                    @foreach ($staffAccounts as $staff)
                        <option value="{{ $staff->id }}" @selected($activeAssignedStaffId == $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            <div class="form-group d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($hasFilters)
                    <a href="{{ route($boardRoute) }}" class="btn btn-outline">Clear</a>
                @endif
            </div>
        </div>
    </form>
</div>

{{-- Board --}}
<div class="card">
    <div class="card-head">
        <h2 class="card-title">{{ $boardHeading }}</h2>
        <span class="card-head-note">{{ $concerns->total() }} {{ Str::plural($pluralUnit, $concerns->total()) }}</span>
    </div>
    <div class="table-wrap table-card-view">
        <table class="table table-hover align-middle mb-0 kaizen-board-table">
            <thead class="thead-muted">
                <tr>
                    <th>{{ $recordLabel }}</th>
                    <th>Submitted By</th>
                    @if ($showAssigned)
                        <th>Assigned To</th>
                    @endif
                    <th>Status</th>
                    <th>Target Date</th>
                    <th>{{ $showAssigned ? 'Resolution' : 'Implementation' }}</th>
                    <th>Evidence</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($concerns as $concern)
                    <tr class="{{ $concern->isCompleted() ? 'kaizen-row-implemented' : ($concern->effectiveStatus() === \App\Models\KaizenConcern::STATUS_OVERDUE ? 'table-danger' : '') }}">
                        <td data-col="{{ $recordLabel }}" class="kaizen-cell-suggestion">
                            <span class="badge kaizen-suggestion-tag">{{ $recordLabel }}</span>
                            <div class="kaizen-challenge">{{ Str::limit($concern->challenge, 90) }}</div>
                            <div class="kaizen-cell-sub">
                                Identified {{ $concern->date_identified?->format('M j, Y') ?? '—' }}
                                @if ($concern->recommended_solution)
                                    &middot; {{ Str::limit($concern->recommended_solution, 60) }}
                                @endif
                            </div>
                        </td>
                        <td data-col="Submitted By" class="kaizen-cell-by">
                            <div class="kaizen-by-name">{{ $concern->creator->name ?? '—' }}</div>
                            @unless ($showAssigned)
                                @if ($concern->assignedStaff)
                                    <div class="kaizen-cell-sub">Assigned: {{ $concern->assignedStaff->name }}</div>
                                @else
                                    <div class="kaizen-cell-sub">Unassigned</div>
                                @endif
                            @endunless
                        </td>
                        @if ($showAssigned)
                            <td data-col="Assigned To">{{ $concern->assignedStaff?->name ?? 'Unassigned' }}</td>
                        @endif
                        <td data-col="Status">
                            @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
                        </td>
                        <td data-col="Target Date">{{ $concern->target_date?->format('M j, Y') ?? '—' }}</td>
                        <td data-col="{{ $showAssigned ? 'Resolution' : 'Implementation' }}">
                            @if ($concern->isAdminConcern())
                                @if ($concern->isResolved() || $concern->resolved_at)
                                    <div class="kaizen-impl-date">{{ $concern->isResolved() ? 'Resolved on:' : 'Closed on:' }}</div>
                                    <div class="kaizen-impl-value">{{ ($concern->resolved_at ?? $concern->implementation_date)?->format('M j, Y') ?? '—' }}</div>
                                @elseif ($concern->isClosed())
                                    <span class="badge badge-neutral">Closed</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            @elseif ($concern->isImplemented())
                                <div class="kaizen-impl-date">Implemented on:</div>
                                <div class="kaizen-impl-value">{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td data-col="Evidence">
                            @php $evidenceCount = (int) ($concern->evidences_count ?? 0); @endphp
                            @if ($evidenceCount > 0)
                                <a href="{{ route('admin.kaizen-concerns.show', ['concern' => $concern, 'back' => $boardReturnUrl]) }}#evidence" class="btn btn-outline btn-sm">
                                    View Evidence
                                </a>
                                <div class="kaizen-cell-sub">{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }} attached</div>
                            @else
                                <span class="muted">No evidence</span>
                            @endif
                        </td>
                        <td data-col="Actions" class="text-end kaizen-cell-actions">
                            <a href="{{ route('admin.kaizen-concerns.show', ['concern' => $concern, 'back' => $boardReturnUrl]) }}" class="btn btn-outline btn-sm">View</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.kaizen-concerns.edit', ['concern' => $concern, 'back' => $boardReturnUrl]) }}" class="btn btn-outline btn-sm">Edit</a>
                            @endif
                            @include('admin.kaizen-concerns.partials.assign-form', ['concern' => $concern])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showAssigned ? 8 : 7 }}" class="empty-cell">
                        @if ($hasFilters)
                            {{ $filteredEmpty }}
                        @else
                            {{ $emptyMessage }}
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>

    {{ $concerns->links('pagination.simple') }}
</div>
