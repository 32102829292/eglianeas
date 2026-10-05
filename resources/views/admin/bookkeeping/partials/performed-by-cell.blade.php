{{--
    The "Performed By" cell shared by the weekly, monthly and quarterly trackers.

    Shows the accountability the target already records: who did the work, the
    role they held, and when it finished. Nothing here is derived or invented.

    Parameters:
      $target  the bookkeeping target
--}}
@php
    $hasPerformer = $target->performed_by_id || $target->performed_by_name;
@endphp
@if ($hasPerformer)
    <div class="bk-performed">
        <div class="bk-performed-name">{{ $target->performedByDisplayName() }}</div>
        <div class="bk-performed-meta">
            <span class="bk-role bk-role-{{ $target->performed_by_role ?: 'staff' }}">{{ $target->performedByRoleLabel() }}</span>
            @if ($target->ended_at)
                <span class="bk-performed-time">Completed: {{ $target->ended_at->format('M j, Y g:i A') }}</span>
            @elseif ($target->started_at)
                <span class="bk-performed-time">Started: {{ $target->started_at->format('M j, Y g:i A') }}</span>
            @endif
        </div>
    </div>
@else
    <span class="muted">&mdash;</span>
@endif
