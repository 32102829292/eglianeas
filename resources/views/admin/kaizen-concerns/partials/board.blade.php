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
                <label class="form-label" for="filter_assigned_staff_id">Assigned Staff</label>
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
                    <th>Implementation</th>
                    <th>Evidence</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($concerns as $concern)
                    <tr class="{{ $concern->isImplemented() ? 'kaizen-row-implemented' : ($concern->effectiveStatus() === \App\Models\KaizenConcern::STATUS_OVERDUE ? 'table-danger' : '') }}">
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
                        <td data-col="Implementation">
                            @if ($concern->isImplemented())
                                <div class="kaizen-impl-date">Implemented on:</div>
                                <div class="kaizen-impl-value">{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td data-col="Evidence">
                            @php $evidenceCount = (int) ($concern->evidences_count ?? 0); @endphp
                            @if ($evidenceCount > 0)
                                <a href="{{ route('admin.kaizen-concerns.show', $concern) }}#evidence" class="btn btn-outline btn-sm">
                                    View Evidence
                                </a>
                                <div class="kaizen-cell-sub">{{ $evidenceCount }} {{ Str::plural('file', $evidenceCount) }} attached</div>
                            @else
                                <span class="muted">No evidence</span>
                            @endif
                        </td>
                        <td data-col="Actions" class="text-end kaizen-cell-actions">
                            <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-outline btn-sm">View</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.kaizen-concerns.edit', $concern) }}" class="btn btn-outline btn-sm">Edit</a>
                            @endif
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

        <div class="card-view-list">
            @forelse ($concerns as $concern)
                <div class="cv-card {{ $concern->isImplemented() ? 'cv-implemented' : '' }}">
                    <div class="cv-card-head">
                        <div class="cv-head-main">
                            <span class="badge kaizen-suggestion-tag">{{ $recordLabel }}</span>
                            <div class="cv-head-title">{{ Str::limit($concern->challenge, 90) }}</div>
                            <div class="cv-head-sub">
                                Submitted by {{ $concern->creator->name ?? '—' }}
                                &middot; Identified {{ $concern->date_identified?->format('M j, Y') ?? '—' }}
                            </div>
                            <div class="cv-head-sub">
                                @include('admin.kaizen-concerns.partials.status-pill', ['concern' => $concern])
                            </div>
                        </div>
                    </div>
                    <div class="cv-card-body">
                        <div class="cv-pair cv-full"><span class="cv-label">Suggested Solution</span><span class="cv-value">@if ($concern->recommended_solution){{ $concern->recommended_solution }}@else<span class="muted">—</span>@endif</span></div>
                        <div class="cv-pair"><span class="cv-label">Submitted By</span><span class="cv-value">{{ $concern->creator->name ?? '—' }}</span></div>
                        <div class="cv-pair"><span class="cv-label">Assigned Staff</span><span class="cv-value">{{ $concern->assignedStaff?->name ?? 'Unassigned' }}</span></div>
                        <div class="cv-pair"><span class="cv-label">Target Date</span><span class="cv-value">{{ $concern->target_date?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-pair">
                            <span class="cv-label">Implementation</span>
                            <span class="cv-value">
                                @if ($concern->isImplemented())
                                    Implemented on {{ $concern->implementation_date?->format('M j, Y') ?? '—' }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div class="cv-pair">
                            <span class="cv-label">Evidence</span>
                            <span class="cv-value">
                                @php $evidenceCount = (int) ($concern->evidences_count ?? 0); @endphp
                                @if ($evidenceCount > 0)
                                    <a href="{{ route('admin.kaizen-concerns.show', $concern) }}#evidence">View Evidence</a>
                                    <span class="muted">({{ $evidenceCount }})</span>
                                @else
                                    <span class="muted">No evidence</span>
                                @endif
                            </span>
                        </div>
                        @if ($concern->notes)
                            <div class="cv-pair cv-full"><span class="cv-label">Notes</span><span class="cv-value">{{ $concern->notes }}</span></div>
                        @endif
                    </div>
                    <div class="cv-card-actions">
                        <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-outline btn-sm">View</a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.kaizen-concerns.edit', $concern) }}" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.kaizen-concerns.destroy', $concern) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this {{ \Illuminate\Support\Str::singular($pluralUnit) }}?', message: 'This {{ \Illuminate\Support\Str::singular($pluralUnit) }} will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="cv-card cv-empty">
                    @if ($hasFilters)
                        {{ $filteredEmpty }}
                    @else
                        {{ $emptyMessage }}
                    @endif
                </p>
            @endforelse
        </div>
    </div>
    {{ $concerns->links('pagination.simple') }}
</div>
