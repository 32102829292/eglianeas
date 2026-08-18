@extends('layouts.dashboard')

@section('title', 'Distribution — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Distribution</h1>
        <p>Your BIR form checklist and softcopy documents.</p>
    </div>

    {{-- BIR Form Checklist (read-only) --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">BIR Form Checklist</h2>
            @php($filedCount = collect($birStatuses)->filter(fn ($s) => $s === 'filed')->count())
            <span class="badge badge-{{ $filedCount > 0 ? 'current' : 'neutral' }}">{{ $filedCount }}/{{ count($formTypes) }} filed</span>
        </div>
        <p class="card-sub">Status of your BIR form filings as tracked by Egliane.</p>
        @if (count($formTypes) > 0)
            <div class="bir-checklist">
                @foreach ($formTypes as $ft)
                    @php($current = $birStatuses[$ft] ?? null)
                    <div class="bir-checklist-item">
                        <span class="bir-checklist-type">{{ $ft }}</span>
                        <span class="badge badge-{{ $current === 'filed' ? 'current' : 'critical' }}">
                            {{ $current ? $statuses[$current] : 'Not Filed' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <p>No BIR forms have been assigned to your account yet.</p>
            </div>
        @endif
    </div>

    {{-- Softcopy Documents --}}
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Softcopy documents</h2>
        </div>
        <p class="card-sub">Digital copies of your documents uploaded by Egliane.</p>

        @if ($softcopies->isNotEmpty())
            @foreach ($softcopies as $formType => $files)
                <div class="softcopy-group">
                    <h3 class="softcopy-group-title"><span class="code-pill">{{ $formType }}</span></h3>
                    @foreach ($files as $doc)
                        <div class="softcopy-row">
                            <div>
                                <div class="cell-name">{{ $doc->original_name }}</div>
                                <small class="muted">{{ $doc->sizeLabel() }} &middot; {{ $doc->created_at?->format('M j, Y') }}</small>
                            </div>
                            <div class="btn-row">
                                <a href="{{ route('documents.view', $doc) }}" class="btn btn-outline btn-sm">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    View
</a>
                                <a href="{{ route('client.documents.download', $doc) }}" class="btn btn-outline btn-sm">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Download
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="empty-state">
                <p>No softcopy documents available yet.</p>
            </div>
        @endif
    </div>
@endsection
