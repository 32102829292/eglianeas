@props(['values' => [], 'width' => 120, 'height' => 36, 'color' => 'var(--sky-deep)'])

@php
    $points = collect($values)->values();
    $count = $points->count();
@endphp

@if($count < 2)
    <span class="text-meta">Not enough data yet</span>
@else
    @php
        $max = $points->max();
        $min = $points->min();
        $range = ($max - $min) ?: 1;
        $stepX = $width / ($count - 1);
        $coords = $points->map(function ($val, $i) use ($stepX, $height, $min, $range) {
            $x = round($i * $stepX, 2);
            $y = round($height - (($val - $min) / $range) * $height, 2);
            return "{$x},{$y}";
        })->implode(' ');
        $lastX = round(($count - 1) * $stepX, 2);
        $areaPath = "0,{$height} {$coords} {$lastX},{$height}";
    @endphp
    <svg class="sparkline" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" preserveAspectRatio="none">
        <polygon points="{{ $areaPath }}" fill="{{ $color }}" opacity="0.12"></polygon>
        <polyline points="{{ $coords }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
    </svg>
@endif
