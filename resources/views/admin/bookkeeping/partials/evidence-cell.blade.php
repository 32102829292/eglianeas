{{--
    The evidence cell shared by the weekly, monthly and quarterly trackers.

    Renders the state the target is already in; it never decides whether a file
    may be uploaded, replaced or removed. Those rules stay in the controllers.

    Parameters:
      $target       the bookkeeping target
      $bookkeeping  the plan the target belongs to
      $prefix       the module route prefix, e.g. `admin.monthly-bookkeeping`
--}}
@php
    $hasEvidence = filled($target->attachment_path);
@endphp
@if ($hasEvidence)
    <div class="bk-evidence">
        <span class="bk-evidence-pill">
            <span aria-hidden="true">&#128206;</span>
            Evidence
        </span>
        <div class="bk-evidence-links">
            <a href="{{ route($prefix.'.view-attachment', [$bookkeeping, $target]) }}" target="_blank" rel="noopener">View</a>
            <a href="{{ route($prefix.'.download-attachment', [$bookkeeping, $target]) }}" download>Download</a>
        </div>
        @if (filled($target->attachment_name))
            <small class="bk-evidence-name" title="{{ $target->attachment_name }}">{{ $target->attachment_name }}</small>
        @endif
    </div>
@else
    <span class="bk-evidence-empty">
        <span aria-hidden="true">&#128206;</span>
        No evidence
    </span>
@endif
