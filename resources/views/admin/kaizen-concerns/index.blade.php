@extends('layouts.dashboard')

@section('title', 'Admin Concerns / Kaizen Strategy — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Admin Concerns / Kaizen Strategy</h1>
            <p>Track workplace challenges, recommended solutions, and implementation progress.</p>
        </div>
    </div>

    {{-- Add concern form (admin only) --}}
    @if (auth()->user()->isAdmin())
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Create Kaizen Concern</h2>
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
                <div class="form-group d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($q || $activeStatus || $activeAssignedStaffId)
                        <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Clear</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Concerns list --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Kaizen Concerns</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Date Identified</th>
                        <th>Challenge / Opportunity</th>
                        <th>Recommended Solution</th>
                        <th>Target Date</th>
                        <th>Impl. Date</th>
                        <th>Assigned Staff</th>
                        <th class="text-center">Status</th>
                        <th>Evidence</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($concerns as $concern)
                        <tr class="{{ $concern->status === 'overdue' ? 'table-danger' : '' }}">
                            <td data-col="Date Identified">{{ $concern->date_identified?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Challenge / Opportunity">
                                <div class="fw-semibold">{{ Str::limit($concern->challenge, 100) }}</div>
                                @if (strlen($concern->challenge) > 100)
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="tooltip" title="{{ $concern->challenge }}">Show more</button>
                                @endif
                            </td>
                            <td data-col="Recommended Solution">
                                @if ($concern->recommended_solution)
                                    {{ Str::limit($concern->recommended_solution, 80) }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-col="Target Date">{{ $concern->target_date?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Impl. Date">{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Assigned Staff">
                                @if ($concern->assignedStaff)
                                    {{ $concern->assignedStaff->name }}
                                @else
                                    <span class="badge badge-info">Unassigned</span>
                                @endif
                            </td>
                            <td data-col="Status" class="text-center">
                                <span class="badge {{ $concern->statusBadgeClass() }}">{{ $concern->statusLabel() }}</span>
                            </td>
                            <td data-col="Evidence">
                                @if ($concern->hasEvidence())
                                    <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-link btn-sm">View Evidence</a>
                                @else
                                    <span class="muted">No evidence</span>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end">
                                <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-link btn-sm">View</a>
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('admin.kaizen-concerns.edit', $concern) }}" class="btn btn-link btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.kaizen-concerns.destroy', $concern) }}" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this concern?', message: 'This concern record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-cell">No Kaizen concerns found.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($concerns as $concern)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ Str::limit($concern->challenge, 80) }}</div>
                                <div class="cv-head-sub">{{ $concern->date_identified?->format('M j, Y') ?? '—' }} &middot; <span class="badge {{ $concern->statusBadgeClass() }}">{{ $concern->statusLabel() }}</span></div>
                            </div>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair cv-full"><span class="cv-label">Challenge / Opportunity</span><span class="cv-value">{{ $concern->challenge }}</span></div>
                            <div class="cv-pair cv-full"><span class="cv-label">Recommended Solution</span><span class="cv-value">@if ($concern->recommended_solution){{ $concern->recommended_solution }}@else<span class="muted">—</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Target Date</span><span class="cv-value">{{ $concern->target_date?->format('M j, Y') ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Implementation Date</span><span class="cv-value">{{ $concern->implementation_date?->format('M j, Y') ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Assigned Staff</span><span class="cv-value">@if ($concern->assignedStaff){{ $concern->assignedStaff->name }}@else<span class="badge badge-info">Unassigned</span>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Evidence</span><span class="cv-value">@if ($concern->hasEvidence())<a href="{{ route('admin.kaizen-concerns.show', $concern) }}">View Evidence</a>@else<span class="muted">No evidence</span>@endif</span></div>
                            @if ($concern->notes)
                                <div class="cv-pair cv-full"><span class="cv-label">Notes</span><span class="cv-value">{{ $concern->notes }}</span></div>
                            @endif
                        </div>
                        <div class="cv-card-actions">
                            <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-outline btn-sm">View</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.kaizen-concerns.edit', $concern) }}" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.kaizen-concerns.destroy', $concern) }}" onsubmit="return egliane.confirm.form(this, { title: 'Delete this concern?', message: 'This concern record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No Kaizen concerns found.</p>
                @endforelse
            </div>
        </div>
        {{ $concerns->links('pagination.simple') }}
    </div>
@endsection