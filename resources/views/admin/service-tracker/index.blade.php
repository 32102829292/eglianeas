@extends('layouts.dashboard')

@section('title', 'Service Tracker — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Service Tracker</h1>
            <p>Track service completion across all clients and staff.</p>
        </div>
        <div class="page-head-actions">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.service-tracker.summary') }}" class="btn btn-outline btn-sm">Summary</a>
                <a href="{{ route('admin.service-tracker.create') }}" class="btn btn-primary">New instance</a>
            @endif
        </div>
    </div>

    <div class="stat-grid cols-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total Services</span>
            <b class="stat-value">{{ $stats['total'] }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-9-9"/><path d="M21 3v6h-6"/></svg>
            </div>
            <span class="stat-label">In Progress</span>
            <b class="stat-value">{{ $stats['inProgress'] }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">On Hold</span>
            <b class="stat-value">{{ $stats['onHold'] }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Completed</span>
            <b class="stat-value">{{ $stats['done'] }}</b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.service-tracker.index') }}" class="filter-bar-form">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search client or service&hellip;">
            <select name="service_id" onchange="this.form.submit()">
                <option value="">All services</option>
                @foreach ($services as $st)
                    <option value="{{ $st->id }}" @selected($activeServiceId == $st->id)>{{ $st->name }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="todo" @selected($activeStatus === 'todo')>To Do</option>
                <option value="in_progress" @selected($activeStatus === 'in_progress')>In Progress</option>
                <option value="on_hold" @selected($activeStatus === 'on_hold')>On Hold</option>
                <option value="done" @selected($activeStatus === 'done')>Done</option>
            </select>
            <select name="staff" onchange="this.form.submit()">
                <option value="">All staff</option>
                @foreach ($allStaff as $s)
                    <option value="{{ $s }}" @selected($activeStaff === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        @error('action')<div class="form-error" style="margin-bottom:12px;">{{ $message }}</div>@enderror
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 tracker-table">
                <colgroup>
                    <col style="width:15%">
                    <col style="width:12%">
                    <col style="width:7%">
                    <col style="width:12%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:30%">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Service</th>
                        <th>Client</th>
                        <th class="text-center">Status</th>
                        <th>Assigned Staff</th>
                        <th>Started</th>
                        <th>Due</th>
                        <th>Completed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($instances as $instance)
                        <tr>
                            <td data-col="Service">
                                <div class="fw-semibold">{{ $instance->service?->name }}</div>
                                @if ($instance->notes)
                                    <div class="cell-note" title="{{ $instance->notes }}">{{ $instance->notes }}</div>
                                @endif
                            </td>
                            <td data-col="Client">
                                <div class="fw-semibold">{{ $instance->client?->business_name ?: $instance->client?->name }}</div>
                                @if ($instance->client?->business_name && $instance->client?->name)
                                    <small class="muted">{{ $instance->client?->name }}</small>
                                @endif
                            </td>
                            <td data-col="Status" class="text-center">
                                @php($s = $instance->status)
                                <span class="badge {{ $badgeClasses[$s] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
                                @if ($instance->assignments->count())
                                    <div><small class="muted">{{ $instance->completionPercent() }}%</small></div>
                                @endif
                            </td>
                            <td data-col="Assigned Staff">
                                @forelse ($instance->assignments as $assignment)
                                    <form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="staff-chip {{ $assignment->completed ? 'is-done' : '' }}" title="Toggle assignment status">
                                            {{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}
                                        </button>
                                    </form>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </td>
                            <td data-col="Started" class="td-date">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Due" class="td-date">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Completed" class="td-date">{{ $instance->date_completed?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Actions" class="text-end">
                                <div class="tracker-actions">
                                    <div class="ta-row">
                                        @if ($instance->status === 'todo')
                                            <form method="POST" action="{{ route('admin.service-tracker.start', $instance) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline">Start</button>
                                            </form>
                                        @elseif ($instance->status === 'in_progress')
                                            <form method="POST" action="{{ route('admin.service-tracker.hold', $instance) }}" class="hold-form">
                                                @csrf
                                                <input type="text" name="reason" class="form-control form-control-sm hold-input" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason">
                                                <button type="submit" class="btn btn-sm btn-outline">Hold</button>
                                            </form>
                                        @elseif ($instance->status === 'on_hold')
                                            <form method="POST" action="{{ route('admin.service-tracker.resume', $instance) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline">Resume</button>
                                            </form>
                                        @endif
                                    </div>
                                    <div class="ta-row">
                                        @if ($instance->status === 'in_progress')
                                            <form method="POST" action="{{ route('admin.service-tracker.complete', $instance) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Complete</button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->isAdmin())
                                            <button type="button" class="btn btn-sm btn-outline"
                                                    data-reassign-open="{{ $instance->id }}"
                                                    data-service="{{ $instance->service?->name }}"
                                                    data-client="{{ $instance->client?->business_name ?: $instance->client?->name }}"
                                                    data-current="{{ $instance->assignments->first()?->displayName() }}"
                                                    data-current-id="{{ $instance->assignments->first()?->staff_id }}"
                                                    data-action="{{ route('admin.service-tracker.update-assignment', $instance) }}">
                                                Change Staff
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.service-tracker.show', $instance) }}" class="btn btn-sm btn-link">History</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-cell">No service instances yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($instances as $instance)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $instance->service?->name }}</div>
                                <div class="cv-head-sub">{{ $instance->client?->business_name ?: $instance->client?->name }}</div>
                            </div>
                            @php($s = $instance->status)
                            <span class="badge {{ $badgeClasses[$s] ?? 'badge-neutral' }}">{{ $instance->statusLabel() }}</span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair cv-full"><span class="cv-label">Assigned Staff</span><span class="cv-value">@forelse ($instance->assignments as $assignment)<form method="POST" action="{{ route('admin.service-tracker.toggle-assignment', $assignment) }}" class="d-inline">@csrf<button type="submit" class="staff-chip {{ $assignment->completed ? 'is-done' : '' }}" title="Toggle assignment status">{{ $assignment->displayName() }} {{ $assignment->completed ? '✓' : '○' }}</button></form>@empty<span class="muted">—</span>@endforelse @if ($instance->assignments->count())<small class="muted">&middot; {{ $instance->completionPercent() }}%</small>@endif</span></div>
                            <div class="cv-pair"><span class="cv-label">Started</span><span class="cv-value">{{ $instance->date_started?->format('M j, Y') ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Due</span><span class="cv-value">{{ $instance->otherService?->due_date?->format('M j, Y') ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Completed</span><span class="cv-value">{{ $instance->date_completed?->format('M j, Y') ?? '—' }}</span></div>
                            @if ($instance->notes)
                                <div class="cv-pair cv-full"><span class="cv-label">Notes</span><span class="cv-value">{{ $instance->notes }}</span></div>
                            @endif
                        </div>
                        <div class="cv-card-actions">
                            @if ($instance->status === 'todo')
                                <form method="POST" action="{{ route('admin.service-tracker.start', $instance) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Start</button></form>
                            @elseif ($instance->status === 'in_progress')
                                <form method="POST" action="{{ route('admin.service-tracker.hold', $instance) }}" class="hold-form">@csrf<input type="text" name="reason" class="form-control form-control-sm hold-input" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason"><button type="submit" class="btn btn-outline btn-sm">Hold</button></form>
                            @elseif ($instance->status === 'on_hold')
                                <form method="POST" action="{{ route('admin.service-tracker.resume', $instance) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Resume</button></form>
                            @endif
                            @if ($instance->status === 'in_progress')
                                <form method="POST" action="{{ route('admin.service-tracker.complete', $instance) }}">@csrf<button type="submit" class="btn btn-success btn-sm">Complete</button></form>
                            @endif
                            @if (auth()->user()->isAdmin())
                                <button type="button" class="btn btn-outline btn-sm"
                                        data-reassign-open="{{ $instance->id }}"
                                        data-service="{{ $instance->service?->name }}"
                                        data-client="{{ $instance->client?->business_name ?: $instance->client?->name }}"
                                        data-current="{{ $instance->assignments->first()?->displayName() }}"
                                        data-current-id="{{ $instance->assignments->first()?->staff_id }}"
                                        data-action="{{ route('admin.service-tracker.update-assignment', $instance) }}">
                                    Change Staff
                                </button>
                            @endif
                            <a href="{{ route('admin.service-tracker.show', $instance) }}" class="btn btn-outline btn-sm">History</a>
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No service instances yet.</p>
                @endforelse
            </div>
        </div>
        {{ $instances->links('pagination.simple') }}
    </div>

    @if (auth()->user()->isAdmin())
        <div id="reassignModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="reassignModalTitle">
            <div class="modal-card reassign-card">
                <h3 id="reassignModalTitle">Change assigned staff</h3>
                <p>Choose the new staff member for this service. Service status, dates, progress, notes and history are preserved.</p>
                <form id="reassignForm" method="POST"
                      onsubmit="return (window.egliane && egliane.confirm.form(this, { title: 'Change assigned staff?', message: 'The assigned staff member will be updated for this service.', confirmLabel: 'Change Staff' })) !== false;">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="instance_id" id="reassignInstanceId">
                    <div class="form-group">
                        <span class="form-label">Service</span>
                        <div class="reassign-static" id="reassignService"></div>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Client</span>
                        <div class="reassign-static" id="reassignClient"></div>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Current assigned staff</span>
                        <div class="reassign-static" id="reassignCurrent"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reassignStaff">New assigned staff</label>
                        <select class="form-control" name="staff_id" id="reassignStaff" required>
                            <option value="">&hellip;select staff&hellip;</option>
                        </select>
                        <div class="form-error hidden" id="reassignError"></div>
                    </div>
                    <div class="btn-group-row">
                        <button type="button" class="btn btn-outline" data-reassign-cancel>Cancel</button>
                        <button type="submit" class="btn btn-primary" id="reassignSubmitBtn">Change Staff</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if (auth()->user()->isAdmin())
@push('styles')
<style>
    .reassign-card { max-width: 440px; }
    .reassign-card p { margin-bottom: 14px; }
    .reassign-static { font-size: 14px; font-weight: 600; color: var(--navy, #1B1B3A); padding-top: 6px; word-break: break-word; }
    .reassign-card .form-group { margin-bottom: 14px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    var STAFF_ACCOUNTS = @json($staffAccounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values());

    var modal = document.getElementById('reassignModal');
    if (!modal) return;

    var form = document.getElementById('reassignForm');
    var serviceEl = document.getElementById('reassignService');
    var clientEl = document.getElementById('reassignClient');
    var currentEl = document.getElementById('reassignCurrent');
    var staffSelect = document.getElementById('reassignStaff');
    var errorEl = document.getElementById('reassignError');
    var submitBtn = document.getElementById('reassignSubmitBtn');

    function renderStaffOptions(excludeId) {
        staffSelect.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '\u2026select staff\u2026';
        staffSelect.appendChild(placeholder);
        STAFF_ACCOUNTS.forEach(function (s) {
            if (excludeId !== null && String(s.id) === String(excludeId)) return;
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            staffSelect.appendChild(o);
        });
    }

    function closeModal() {
        modal.classList.add('hidden');
        form.dataset.submitting = '0';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Change Staff';
        errorEl.classList.add('hidden');
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-reassign-open]');
        if (openBtn) {
            document.getElementById('reassignInstanceId').value = openBtn.dataset.reassignOpen;
            serviceEl.textContent = openBtn.dataset.service || '\u2014';
            clientEl.textContent = openBtn.dataset.client || '\u2014';
            currentEl.textContent = openBtn.dataset.current || '\u2014';
            form.action = openBtn.dataset.action;
            renderStaffOptions(openBtn.dataset.currentId ? openBtn.dataset.currentId : null);
            staffSelect.value = '';
            form.dataset.submitting = '0';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Change Staff';
            errorEl.classList.add('hidden');
            modal.classList.remove('hidden');
            staffSelect.focus();
            return;
        }
        if (e.target.closest('[data-reassign-cancel]') || e.target.id === 'reassignModal') {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    form.addEventListener('submit', function () {
        if (form.dataset.submitting === '1') return false;
        if (!staffSelect.value) {
            errorEl.textContent = 'Please select a new staff member.';
            errorEl.classList.remove('hidden');
            staffSelect.focus();
            return false;
        }
        form.dataset.submitting = '1';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving\u2026';
        return true;
    });
})();
</script>
@endpush
@endif
