@extends('layouts.dashboard')

@section('title', 'Client Concerns — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Client Concerns Log</h1>
            <p>Track issues, problems, and concerns encountered with clients.</p>
        </div>
    </div>

    {{-- Add concern form (staff) --}}
    <div class="card" style="margin-bottom:24px;">
        <div class="card-head">
            <h2 class="card-title">Log a concern</h2>
        </div>
        <form method="POST" action="{{ route('admin.service-tracker.concerns.store') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="client_id">Client</label>
                    <select class="form-control" id="client_id" name="client_id" required>
                        <option value="">Select client&hellip;</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }} — {{ $client->business_name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_identified">Date identified</label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date" value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Frequency</label>
                    <select class="form-control" id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'seldom') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="related_service_id">Related service (optional)</label>
                    <select class="form-control" id="related_service_id" name="related_service_id">
                        <option value="">None</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected(old('related_service_id') == $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('related_service_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="description_of_issue">Description of issue</label>
                <textarea class="form-control" id="description_of_issue" name="description_of_issue" rows="2" maxlength="2000" required placeholder="Describe the issue encountered">{{ old('description_of_issue') }}</textarea>
                @error('description_of_issue')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="proposed_solution">Proposed solution</label>
                <textarea class="form-control" id="proposed_solution" name="proposed_solution" rows="2" maxlength="2000" placeholder="How was it resolved or what is planned?">{{ old('proposed_solution') }}</textarea>
                @error('proposed_solution')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Log concern</button>
        </form>
    </div>

    {{-- Filters --}}
    <div class="card" style="margin-bottom:24px;">
        <div class="card-head">
            <h2 class="card-title">Filter concerns</h2>
        </div>
        <form method="GET" action="{{ route('admin.service-tracker.concerns') }}" style="padding:0 20px 20px;">
            <div class="form-grid three" style="align-items:end; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="filter_submitted_by">Submitted by</label>
                    <select class="form-control" id="filter_submitted_by" name="submitted_by">
                        <option value="">All</option>
                        <option value="client" @selected($submittedByFilter === 'client')>Client</option>
                        <option value="staff" @selected($submittedByFilter === 'staff')>Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter_reviewed">Review status</label>
                    <select class="form-control" id="filter_reviewed" name="reviewed">
                        <option value="">All</option>
                        <option value="0" @selected($reviewedFilter === '0')>New (unreviewed)</option>
                        <option value="1" @selected($reviewedFilter === '1')>Reviewed</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex; gap:8px; align-items:end;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($submittedByFilter || $reviewedFilter)
                        <a href="{{ route('admin.service-tracker.concerns') }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Concerns list --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Concerns history</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Issue</th>
                        <th>Related Service</th>
                        <th>Solution</th>
                        <th class="text-center">Frequency</th>
                        <th class="text-center">Source</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($concerns as $concern)
                        <tr class="{{ $concern->isNew() ? 'table-warning' : '' }}">
                            <td data-col="Date">{{ $concern->date_identified?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Client">
                                <div class="fw-semibold">{{ $concern->client?->business_name ?: $concern->client?->name }}</div>
                                <small class="text-muted">{{ $concern->client?->name }}</small>
                            </td>
                            <td data-col="Issue">
                                {{ Str::limit($concern->description_of_issue, 80) }}
                                @if ($concern->isNew())
                                    <span class="badge badge-success badge-new">New</span>
                                @endif
                            </td>
                            <td data-col="Related Service">{{ $concern->relatedService?->name ?? '—' }}</td>
                            <td data-col="Solution">
                                @if ($concern->proposed_solution)
                                    {{ Str::limit($concern->proposed_solution, 60) }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-col="Frequency" class="text-center">
                                @php($s = $concern->status)
                                <span class="badge {{ $s === 'frequent' ? 'badge-danger' : ($s === 'seldom' ? 'badge-warn' : 'badge-neutral') }}">{{ $concern->statusLabel() }}</span>
                            </td>
                            <td data-col="Source" class="text-center">
                                @if ($concern->isClientSubmitted())
                                    <span class="badge badge-info">Client</span>
                                @else
                                    <span class="badge badge-neutral">Staff</span>
                                @endif
                            </td>
                            <td data-col="Actions" class="text-end">
                                <button type="button" class="btn btn-link btn-sm" onclick="toggleEdit({{ $concern->id }})">Edit</button>
                                @if ($concern->isNew())
                                    <form method="POST" action="{{ route('admin.service-tracker.concerns.review', $concern) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm text-success">Mark reviewed</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.service-tracker.concerns.destroy', $concern) }}" class="d-inline" onsubmit="return confirm('Delete this concern?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm text-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        {{-- Inline edit row --}}
                        <tr id="edit-{{ $concern->id }}" style="display:none;">
                            <td colspan="8" style="background:#f8fafc; padding:16px 20px;">
                                <form method="POST" action="{{ route('admin.service-tracker.concerns.update', $concern) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-grid two" style="gap:12px;">
                                        <div class="form-group">
                                            <label class="form-label">Description of issue</label>
                                            <textarea class="form-control" name="description_of_issue" rows="2" maxlength="2000" required>{{ $concern->description_of_issue }}</textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Proposed solution</label>
                                            <textarea class="form-control" name="proposed_solution" rows="2" maxlength="2000" placeholder="How was it resolved or what is planned?">{{ $concern->proposed_solution }}</textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Frequency</label>
                                            <select class="form-control" name="status" required>
                                                @foreach ($statuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($concern->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Related service</label>
                                            <select class="form-control" name="related_service_id">
                                                <option value="">None</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}" @selected($concern->related_service_id == $service->id)>{{ $service->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-top:12px; display:flex; gap:8px;">
                                        <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit({{ $concern->id }})">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No concerns logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-view-list">
                @forelse ($concerns as $concern)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Date</span><span class="cv-value">{{ $concern->date_identified?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Client</span><span class="cv-value">{{ $concern->client?->business_name ?: $concern->client?->name }}<br><small class="text-muted">{{ $concern->client?->name }}</small></span></div>
                        <div class="cv-row"><span class="cv-label">Issue</span><span class="cv-value">{{ Str::limit($concern->description_of_issue, 80) }}@if ($concern->isNew()) <span class="badge badge-success">New</span>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Related Service</span><span class="cv-value">{{ $concern->relatedService?->name ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Solution</span><span class="cv-value">@if ($concern->proposed_solution){{ Str::limit($concern->proposed_solution, 60) }}@else<span class="text-muted">—</span>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Frequency</span><span class="cv-value">@php($s = $concern->status)<span class="badge {{ $s === 'frequent' ? 'badge-danger' : ($s === 'seldom' ? 'badge-warn' : 'badge-neutral') }}">{{ $concern->statusLabel() }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Source</span><span class="cv-value">@if ($concern->isClientSubmitted())<span class="badge badge-info">Client</span>@else<span class="badge badge-neutral">Staff</span>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value"><button type="button" class="btn btn-link btn-sm" onclick="toggleEditCard(this)">Edit</button>@if ($concern->isNew())<form method="POST" action="{{ route('admin.service-tracker.concerns.review', $concern) }}" class="d-inline">@csrf<button type="submit" class="btn btn-link btn-sm text-success">Mark reviewed</button></form>@endif<form method="POST" action="{{ route('admin.service-tracker.concerns.destroy', $concern) }}" class="d-inline" onsubmit="return confirm('Delete this concern?');">@csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm text-danger">Delete</button></form></span></div>
                        <div class="cv-edit-form" style="display:none; margin-top:10px; padding-top:10px; border-top:1px solid var(--border);">
                            <form method="POST" action="{{ route('admin.service-tracker.concerns.update', $concern) }}">
                                @csrf
                                @method('PUT')
                                <div class="form-grid two" style="gap:10px;">
                                    <div class="form-group">
                                        <label class="form-label">Description of issue</label>
                                        <textarea class="form-control" name="description_of_issue" rows="2" maxlength="2000" required>{{ $concern->description_of_issue }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Proposed solution</label>
                                        <textarea class="form-control" name="proposed_solution" rows="2" maxlength="2000" placeholder="How was it resolved or what is planned?">{{ $concern->proposed_solution }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Frequency</label>
                                        <select class="form-control" name="status" required>
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($concern->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Related service</label>
                                        <select class="form-control" name="related_service_id">
                                            <option value="">None</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}" @selected($concern->related_service_id == $service->id)>{{ $service->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-top:10px; display:flex; gap:8px;">
                                    <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditCard(this)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No concerns logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function toggleEdit(id) {
            const row = document.getElementById('edit-' + id);
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }
        function toggleEditCard(btn) {
            var form = btn.closest('.cv-card').querySelector('.cv-edit-form');
            if (form) form.style.display = form.style.display === 'none' ? '' : 'none';
        }
    </script>
@endsection
