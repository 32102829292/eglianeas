<form method="GET" action="{{ $action }}" class="quarter-filter" role="search">
    <label class="quarter-filter-label" for="quarter-select">Period</label>
    <select name="quarter" id="quarter-select" class="quarter-filter-select" onchange="this.form.submit()">
        @foreach ($availableQuarters as $period)
            <option value="{{ $period->key() }}" @selected($period->key() === $activeQuarter->key())>{{ $period->label() }}</option>
        @endforeach
    </select>
    <noscript>
        <button type="submit" class="btn btn-outline btn-sm">Go</button>
    </noscript>
    <span class="quarter-filter-range">{{ $activeQuarter->rangeLabel() }}</span>
</form>