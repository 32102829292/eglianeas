{{-- One cell of the weekly comparison grid.
     A null target renders the workbook's "-": the task does not apply to
     that client this week, so there is nothing to report. --}}
@php
    if (! isset($target) || ! $target) {
        echo '<span class="cmp-none" aria-label="Not applicable">&ndash;</span>';
        return;
    }

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
<a href="{{ route('admin.weekly-bookkeeping.show', $target->weekly_bookkeeping_id) }}#target-{{ $target->id }}"
   class="cmp-cell cmp-{{ $tone }}"
   title="{{ $target->taskLabel() }} — {{ $target->effectiveStatusLabel() }}{{ $target->timingLabel() ? ' ('.$target->timingLabel().')' : '' }}@if($target->performedByDisplayName()) — {{ $target->performedByDisplayName() }}@endif">
    <span class="cmp-cell-text">{{ $label }}</span>
    @if ($target->timingLabel())
        <span class="cmp-cell-sub">{{ $target->timingLabel() }}</span>
    @endif
</a>
