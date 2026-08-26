@props(['segments' => [], 'size' => 140, 'thickness' => 20])

@php
    $total = collect($segments)->sum('value') ?: 1;
    $radius = ($size - $thickness) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset = 0;
@endphp

<div class="donut-chart">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
        <g transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})">
            @foreach($segments as $seg)
                @php
                    $fraction = $seg['value'] / $total;
                    $dash = $fraction * $circumference;
                    $gap = $circumference - $dash;
                @endphp
                <circle
                    cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                    fill="none" stroke="{{ $seg['color'] }}" stroke-width="{{ $thickness }}"
                    stroke-dasharray="{{ round($dash, 2) }} {{ round($gap, 2) }}"
                    stroke-dashoffset="{{ round(-$offset, 2) }}"
                ></circle>
                @php $offset += $dash; @endphp
            @endforeach
        </g>
        <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" class="donut-chart-total">{{ $total }}</text>
    </svg>
    <ul class="donut-chart-legend">
        @foreach($segments as $seg)
            <li><span class="donut-chart-dot" style="background:{{ $seg['color'] }};"></span>{{ $seg['label'] }} — {{ $seg['value'] }}</li>
        @endforeach
    </ul>
</div>
