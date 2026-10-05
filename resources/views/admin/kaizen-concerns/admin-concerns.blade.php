@extends('layouts.dashboard')

@section('title', 'Admin Concerns — Egliane Accounting Services')

@section('content')
    @php
        // The shared board partial reads this to decide whether to offer the
        // "Assigned Staff" filter. Always false here: this page is admin only.
        $isStaffView = false;
    @endphp

    {{--
        The Admin Concerns board.

        Same shared kaizen_concerns table, same design and same partial as the
        Improvement Suggestions board — only the records differ. Every row here is
        type = admin_concern, which is what the Admin Concern create path records;
        a record is never placed here or there based on who created it, so an admin
        who submits an Employee Suggestion still lands on the Improvement
        Suggestions board.
    --}}
    <div class="page-head page-head-row">
        <div>
            <h1>Admin Concerns</h1>
            <p>Concerns raised by the firm — track ownership, progress, and implementation.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            {{-- Cross-links, not duplicated functionality. --}}
            <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">
                Improvement Suggestions
            </a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.kaizen-concerns.create') }}" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 6px; vertical-align: -3px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Concern
                </a>
            @endif
        </div>
    </div>

    @include('admin.kaizen-concerns.partials.board', [
        'boardRoute' => 'admin.kaizen-concerns.admin-concerns.index',
        'recordLabel' => 'Admin Concern',
        'boardHeading' => 'Admin Concerns',
        'pluralUnit' => 'concern',
        'showAssigned' => true,
        'emptyMessage' => 'No admin concerns yet. Use "Create Concern" to raise the first one.',
        'filteredEmpty' => 'No admin concerns match your current filters.',
    ])
@endsection
