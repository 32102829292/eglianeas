{{--
    The at-a-glance status strip shared by the weekly, monthly and quarterly
    target pages.

    It only re-presents numbers the plan model and the loaded targets already
    expose — `completionPercent()`, `isCompleted()` and friends — so nothing here
    recomputes or re-derives a bookkeeping result. Its job is to make the current
    state readable before the reader reaches the tables.

    Parameters:
      $bookkeeping  the plan
      $targets      the plan's targets
--}}
@php
    $percent = (int) $bookkeeping->completionPercent();
    $counts = [
        'done' => $targets->filter(fn ($t) => $t->isCompleted())->count(),
        'active' => $targets->filter(fn ($t) => $t->isInProgress())->count(),
        'pending' => $targets->filter(fn ($t) => $t->isPending())->count(),
        'overdue' => $targets->filter(fn ($t) => ! $t->isCompleted() && $t->isPastDue())->count(),
    ];
    $finished = $counts['done'] === $targets->count() && $targets->isNotEmpty();
@endphp
<div class="bk-plan-strip {{ $finished ? 'is-finished' : '' }}">
    <div class="bk-plan-strip-main">
        <div class="bk-plan-strip-label">
            {{ $finished ? 'All targets completed' : 'Target progress' }}
        </div>
        <div class="bk-plan-strip-value">
            {{ $percent }}<span class="bk-plan-strip-pct">%</span>
        </div>
        <div class="bk-plan-bar" role="img"
             aria-label="{{ $counts['done'] }} of {{ $targets->count() }} targets completed">
            <span style="width: {{ $percent }}%"></span>
        </div>
    </div>
    <div class="bk-plan-strip-counts">
        <span class="bk-count bk-count-done">{{ $counts['done'] }} Completed</span>
        <span class="bk-count bk-count-active">{{ $counts['active'] }} In Progress</span>
        <span class="bk-count bk-count-pending">{{ $counts['pending'] }} Pending</span>
        <span class="bk-count bk-count-overdue">{{ $counts['overdue'] }} Missed / Past Due</span>
    </div>
</div>
