@extends('layouts.dashboard')

@section('title', 'Kaizen Strategy — Employee Improvement Board — Egliane Accounting Services')

@section('content')
    @php
        $isStaffView = auth()->user()->isStaff();
        $isOperational = auth()->user()->isOperational();
    @endphp
    <div class="page-head page-head-row">
        <div>
            <h1>Kaizen Strategy</h1>
            <p>Employee Improvement Board — Track suggestions, progress, and implemented improvements.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($isOperational)
                <a href="{{ route('admin.kaizen-concerns.submit') }}" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 6px; vertical-align: -3px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Submit Improvement
                </a>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="#create-concern-form" class="btn btn-outline" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="create-concern-form">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right: 6px; vertical-align: -2px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Admin: Create Concern
                </a>
            @endif
        </div>
    </div>

    {{-- Admin create concern form (collapsible) --}}
    @if (auth()->user()->isAdmin())
    <div class="card collapse" id="create-concern-form">
        <div class="card-head">
            <h2 class="card-title">Create Kaizen Concern (Admin)</h2>
        </div>
        <form method="POST" action="{{ route('admin.kaizen-concerns.store') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="date_identified">Date Identified</label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date" value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                    <select class="form-control" id="assigned_staff_id" name="assigned_staff_id">
                        <option value="">— Unassigned (visible to all staff & supervisors) —</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected(old('assigned_staff_id') == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_staff_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="target_date">Target Date</label>
                    <input class="form-control" id="target_date" name="target_date" type="date" value="{{ old('target_date') }}">
                    @error('target_date')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="challenge">Challenge / Opportunity for Improvement</label>
                <textarea class="form-control" id="challenge" name="challenge" rows="3" maxlength="5000" required placeholder="Describe the challenge or opportunity...">{{ old('challenge') }}</textarea>
                @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="recommended_solution">Recommended Solution</label>
                <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="3" maxlength="5000" required placeholder="Describe the recommended solution...">{{ old('recommended_solution') }}</textarea>
                @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000" placeholder="Additional notes...">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Create Concern</button>
        </form>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Filter Concerns</h2>
        </div>
        <form method="GET" action="{{ route('admin.kaizen-concerns.index') }}" class="filter-panel">
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
                    @if ($q || $activeStatus || $activeAssignedStaffId)
                        <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Clear</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Improvement board --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Improvement Suggestions</h2>
            <span class="card-head-note">{{ $concerns->total() }} {{ Str::plural('suggestion', $concerns->total()) }}</span>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 kaizen-board-table">
                <thead class="thead-muted">
                    <tr>
                        <th>Employee Suggestion</th>
                        <th>Submitted By</th>
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
                            <td data-col="Employee Suggestion" class="kaizen-cell-suggestion">
                                <span class="badge kaizen-suggestion-tag">Employee Suggestion</span>
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
                                @if ($concern->assignedStaff)
                                    <div class="kaizen-cell-sub">Assigned: {{ $concern->assignedStaff->name }}</div>
                                @else
                                    <div class="kaizen-cell-sub">Unassigned</div>
                                @endif
                            </td>
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
                        <tr><td colspan="7" class="empty-cell">
                            @if ($q || $activeStatus || $activeAssignedStaffId)
                                No suggestions match your current filters.
                            @else
                                No improvement suggestions yet. Use "Submit Improvement" to add the first one.
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
                                <span class="badge kaizen-suggestion-tag">Employee Suggestion</span>
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
                                <form method="POST" action="{{ route('admin.kaizen-concerns.destroy', $concern) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this suggestion?', message: 'This improvement suggestion will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">
                        @if ($q || $activeStatus || $activeAssignedStaffId)
                            No suggestions match your current filters.
                        @else
                            No improvement suggestions yet. Use "Submit Improvement" to add the first one.
                        @endif
                    </p>
                @endforelse
            </div>
        </div>
        {{ $concerns->links('pagination.simple') }}
    </div>
@endsection