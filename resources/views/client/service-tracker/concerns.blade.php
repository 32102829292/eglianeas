@extends('layouts.dashboard')

@section('title', 'Concerns Log — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Concerns Log</h1>
        <p>Submit a concern or view the status of previously submitted issues.</p>
    </div>

    {{-- Submission form --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Log a Concern</h2>
        </div>
        <form method="POST" action="{{ route('client.service-tracker.concerns.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="description_of_issue">Description of issue <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description_of_issue" name="description_of_issue" rows="3" maxlength="2000" required placeholder="Describe the issue you'd like to report">{{ old('description_of_issue') }}</textarea>
                @error('description_of_issue')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="related_service_id">Related service (optional)</label>
                <select class="form-control" id="related_service_id" name="related_service_id">
                    <option value="">Select a service&hellip;</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('related_service_id') == $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
                @error('related_service_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Submit Concern</button>
        </form>
    </div>

    {{-- Concerns list --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Your Concerns</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Issue</th>
                        <th>Related Service</th>
                        <th>Solution</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($concerns as $concern)
                        <tr>
                            <td>{{ $concern->date_identified?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $concern->description_of_issue }}</td>
                            <td>{{ $concern->relatedService?->name ?? '—' }}</td>
                            <td>{{ $concern->proposed_solution ?? '—' }}</td>
                            <td>
                                @php($s = $concern->status)
                                <span class="badge {{ $s === 'frequent' ? 'badge-danger' : ($s === 'seldom' ? 'badge-warn' : 'badge-neutral') }}">{{ $concern->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No concerns logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($concerns as $concern)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Date</span><span class="cv-value">{{ $concern->date_identified?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Issue</span><span class="cv-value">{{ $concern->description_of_issue }}</span></div>
                        <div class="cv-row"><span class="cv-label">Related Service</span><span class="cv-value">{{ $concern->relatedService?->name ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Solution</span><span class="cv-value">{{ $concern->proposed_solution ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@php($s = $concern->status)<span class="badge {{ $s === 'frequent' ? 'badge-danger' : ($s === 'seldom' ? 'badge-warn' : 'badge-neutral') }}">{{ $concern->statusLabel() }}</span></span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No concerns logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
