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
                {{-- Opens the modal below instead of navigating away, so creating a
                     concern no longer moves the page. Same POST endpoint and same
                     fields; only the presentation changed. --}}
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createConcernModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 6px; vertical-align: -3px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Concern
                </button>
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

    {{--
        Create Concern modal (Bootstrap 5.3.3, which this layout already loads —
        the Alpine-based x-modal component is not loaded here and would not work).

        Height is capped to the viewport and only .modal-body scrolls, so on a
        short screen the dialog scrolls internally instead of the whole page.

        store() still receives exactly the same POST fields as before; when
        validation fails Laravel sends the user back here, so the modal is
        reopened below and old() repopulates the form.
    --}}
    @if (auth()->user()->isAdmin())
        <div class="modal fade concern-modal" id="createConcernModal" tabindex="-1" role="dialog"
             aria-labelledby="createConcernModalTitle" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.kaizen-concerns.store') }}" class="concern-modal-form">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="createConcernModalTitle">Create Admin Concern</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-error" role="alert">
                                    Please correct the highlighted field(s) below.
                                </div>
                            @endif
                            @include('admin.kaizen-concerns.partials.create-fields')
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Create Concern</button>
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <script>
                window.addEventListener('load', function () {
                    var el = document.getElementById('createConcernModal');
                    if (el && window.bootstrap) {
                        bootstrap.Modal.getOrCreateInstance(el).show();
                    }
                });
            </script>
        @endif
    @endif
@endsection
