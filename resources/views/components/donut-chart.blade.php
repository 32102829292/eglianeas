@props(['segments' => [], 'size' => 128, 'thickness' => 12, 'showTotalLabel' => false, 'legend' => 'bottom', 'showPct' => false, 'emptyText' => 'No billing data yet.'])

@php
    static $donutIdx = 0;
    $uid = 'dc-' . $donutIdx++;
    $total = collect($segments)->sum('value');
    $hasData = $total > 0;
    $radius = ($size - $thickness) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset = 0;
    // Lighter tint (65% toward white) paired with each segment's full color,
    // used as the soft start of the gradient. Order follows $segments.
    $tints = [
        '#B3E3C7', // --success  (Paid)
        '#FADBC0', // --warning  (Pending)
        '#AFAFBA', // --navy     (Unpaid)
        '#F7C0BB', // --danger   (Overdue)
    ];
    $ti = 0;
@endphp

<div class="donut-chart {{ $legend === 'right' ? 'is-legend-right' : '' }}">
    @if($hasData)
        @if($legend === 'right')<div class="donut-figure">@endif
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
            <defs>
                @foreach($segments as $i => $seg)
                    @if((int) $seg['value'] <= 0) @continue @endif
                    <linearGradient id="{{ $uid }}-{{ $loop->index }}" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="{{ $seg['tint'] ?? ($tints[$ti] ?? '#FFFFFF') }}"></stop>
                        <stop offset="100%" stop-color="{{ $seg['color'] }}"></stop>
                    </linearGradient>
                    @php $ti++; @endphp
                @endforeach
            </defs>
            <g transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})">
                @foreach($segments as $seg)
                    @if((int) $seg['value'] <= 0) @continue @endif
                    @php
                        $fraction = $seg['value'] / $total;
                        $dash = $fraction * $circumference;
                        $gap = $circumference - $dash;
                    @endphp
                    <circle
                        cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                        fill="none" stroke="url(#{{ $uid }}-{{ $loop->index }})" stroke-width="{{ $thickness }}"
                        stroke-linecap="round"
                        stroke-dasharray="{{ round($dash, 2) }} {{ round($gap, 2) }}"
                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                    ></circle>
                    @php $offset += $dash; @endphp
                @endforeach
            </g>
            @if($showTotalLabel)
                <text x="50%" y="46%" text-anchor="middle" class="donut-chart-total">{{ $total }}</text>
                <text x="50%" y="58%" text-anchor="middle" class="donut-chart-total-label">Total</text>
            @else
                <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" class="donut-chart-total">{{ $total }}</text>
            @endif
        </svg>
        @if($legend === 'right')</div>@endif
        <ul class="donut-chart-legend list-unstyled d-flex flex-wrap gap-3 {{ $legend === 'right' ? 'legend-right' : 'justify-content-center' }} mb-0">
            @foreach($segments as $seg)
                @if((int) $seg['value'] <= 0) @continue @endif
                @php $segPct = round($seg['value'] / $total * 100, 1); @endphp
                <li class="d-flex align-items-center gap-2 m-0">
                    <span class="donut-chart-dot" style="background:{{ $seg['color'] }};"></span>
                    @if($legend === 'right')
                        <span class="legend-label">{{ $seg['label'] }}</span>
                        <span class="legend-value">{{ $seg['value'] }}@if($showPct) <small>({{ $segPct }}%)</small>@endif</span>
                    @else
                        {{ $seg['label'] }} — {{ $seg['value'] }}@if($showPct) ({{ $segPct }}%)@endif
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="chart-empty">{{ $emptyText }}</p>
    @endif
</div>
