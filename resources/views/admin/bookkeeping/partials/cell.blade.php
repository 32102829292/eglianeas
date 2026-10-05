{{-- One cell of the monthly / quarterly comparison grid.
     A null target renders the workbook's "-": the task does not apply to
     that client in this period, so there is nothing to report. --}}
@php
    if (! isset($target) || ! $target) {
        echo '<span class="cmp-none" aria-label="Not applicable">&ndash;</span>';
        return;
    }

    $planFk = $config['kind'] === 'monthly' ? 'monthly_bookkeeping_id' : 'quarterly_bookkeeping_id';

    $status = $target->effectiveStatus();
    $tone = match (true) {
        $target->isCompleted() && $target->isLate() => 'late',
        $target->isCompleted() => 'done',
        $target->isInProgress() => 'active',
        $target->isPastDue() => 'overdue',
        default => 'todo',
    };
    $label = $target->cellLabel();
@endphp
<a href="{{ route($config['route_prefix'].'.show', $target->{$planFk}) }}#target-{{ $target->id }}"
   class="cmp-cell cmp-{{ $tone }}"
   title="{{ $target->taskLabel() }} — {{ $target->effectiveStatusLabel() }}{{ $target->timingLabel() ? ' ('.$target->timingLabel().')' : '' }}@if($target->performedByDisplayName()) — {{ $target->performedByDisplayName() }}@endif">
    <span class="cmp-cell-text">{{ $label }}</span>
    @if ($target->timingLabel())
        <span class="cmp-cell-sub">{{ $target->timingLabel() }}</span>
    @endif
</a>
