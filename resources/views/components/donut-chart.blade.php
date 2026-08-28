@props(['segments' => [], 'size' => 128, 'thickness' => 12])

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
        '#F7C0BB', // --danger   (Overdue)
    ];
    $ti = 0;
@endphp

<div class="donut-chart">
    @if($hasData)
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
            <defs>
                @foreach($segments as $i => $seg)
                    @if((int) $seg['value'] <= 0) @continue @endif
                    <linearGradient id="{{ $uid }}-{{ $loop->index }}" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="{{ $tints[$ti] ?? '#FFFFFF' }}"></stop>
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
            <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" class="donut-chart-total">{{ $total }}</text>
        </svg>
        <ul class="donut-chart-legend list-unstyled d-flex flex-wrap gap-3 justify-content-center mb-0">
            @foreach($segments as $seg)
                @if((int) $seg['value'] <= 0) @continue @endif
                <li class="d-flex align-items-center gap-1 m-0"><span class="donut-chart-dot" style="background:{{ $seg['color'] }};"></span>{{ $seg['label'] }} — {{ $seg['value'] }}</li>
            @endforeach
        </ul>
    @else
        <p class="chart-empty">No billing data yet.</p>
    @endif
</div>
