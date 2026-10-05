{{--
    The one status badge shared by the weekly, monthly and quarterly trackers.

    Only the presentation is defined here. The underlying status is still
    whatever the target model derives through `effectiveStatus()`, and the
    precedence below is the one the trackers already used, so no status value or
    calculation changes:

        completed but late  -> done, flagged late
        completed           -> done
        in progress         -> active
        past due            -> overdue
        anything else       -> pending

    Parameters:
      $target   the bookkeeping target
      $label    override the text (defaults to the model's own label)
--}}
@php
    $label = $label ?? $target->effectiveStatusLabel();

    $tone = match (true) {
        $target->isCompleted() && $target->isLate() => 'late',
        $target->isCompleted() => 'done',
        $target->isInProgress() => 'active',
        $target->isPastDue() => 'overdue',
        default => 'pending',
    };
@endphp
<span class="bk-status bk-status-{{ $tone }}">{{ $label }}</span>
