{{--
    Shared Kaizen status indicator.

    Used by the improvement board (table row + mobile card) and the detail
    screen so every surface communicates the same four states with the same
    colour language:

        Pending          amber
        In Progress      blue
        Implemented      green  (stronger, with a tick)
        Not Implemented  grey
        Overdue          red

    The label comes from $concern->statusLabel(), which reads the derived status
    (KaizenConcern::effectiveStatus) so it always reflects the real
    implementation state rather than a hand-picked value.

    $concern is required.
--}}
@php
    $isImplemented = $concern->isImplemented();
    $isNotImplemented = $concern->isNotImplemented();

    $dotClass = match ($concern->effectiveStatus()) {
        \App\Models\KaizenConcern::STATUS_IMPLEMENTED => 'kaizen-dot-done',
        \App\Models\KaizenConcern::STATUS_NOT_IMPLEMENTED => 'kaizen-dot-none',
        \App\Models\KaizenConcern::STATUS_IN_PROGRESS => 'kaizen-dot-progress',
        \App\Models\KaizenConcern::STATUS_OVERDUE => 'kaizen-dot-overdue',
        default => 'kaizen-dot-pending',
    };
@endphp

<span class="kaizen-status {{ $isImplemented ? 'kaizen-status-done' : ($isNotImplemented ? 'kaizen-status-none' : '') }}">
    @if ($isImplemented)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" width="12" height="12" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
    @else
        <span class="kaizen-dot {{ $dotClass }}" aria-hidden="true"></span>
    @endif
    <span class="kaizen-status-label">{{ $isImplemented ? 'Implemented' : $concern->statusLabel() }}</span>
</span>