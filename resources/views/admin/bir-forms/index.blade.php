@extends('layouts.dashboard')

@section('title', 'BIR Forms — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>BIR Forms</h1>
            <p>Select which BIR forms apply to each client. Only checked forms appear on the Distribution page.</p>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.bir-forms.index') }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search business, contact, or client code&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client Code</th>
                        <th>Business</th>
                        <th>Contact</th>
                        @foreach ($formTypes as $ft)
                            <th class="text-center" style="font-size:11px;white-space:nowrap;">{{ $ft }}</th>
                        @endforeach
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $entry)
                        @php($client = $entry['user'])
                        @php($statuses = $client->birFormStatuses->pluck('applicable', 'form_type'))
                        <tr>
                            <td>
                                <span class="badge bg-dark">{{ $client->client_code ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->business_name ?: $client->name }}</div>
                                <small class="text-muted">{{ $client->profile?->line_of_business ?? '—' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <small class="text-muted">{{ $client->email }}</small>
                            </td>
                            @foreach ($formTypes as $ft)
                                @php($isOn = $statuses[$ft] ?? false)
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.bir-forms.toggle', $client) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="form_type" value="{{ $ft }}">
                                        <button type="submit" class="bir-toggle {{ $isOn ? 'bir-toggle-on' : '' }}" title="{{ $ft }}: {{ $isOn ? 'Applicable' : 'Not applicable' }}">
                                            @if ($isOn)
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            @endif
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td class="text-center">
                                <span class="badge @if($entry['applicableCount'] > 0) bg-success @else bg-secondary @endif">{{ $entry['applicableCount'] }}/{{ $entry['totalForms'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($formTypes) }}" class="text-center text-muted py-4">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .bir-toggle {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px; border: 1.5px solid var(--border);
        background: #fff; color: var(--text-muted); cursor: pointer;
        transition: all .15s ease;
    }
    .bir-toggle:hover { border-color: var(--sky); color: var(--sky); }
    .bir-toggle-on { background: #dcfce7; border-color: #22c55e; color: #16a34a; }
    .bir-toggle-on:hover { background: #bbf7d0; }
</style>
@endpush
