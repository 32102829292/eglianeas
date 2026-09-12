@props([
    'value' => null,
    'label' => null,
    'class' => null,
])

@php
    $href = \App\Support\UrlLink::href($value);
@endphp

@if (is_string($href))
    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="url-link {{ $class }}">
        {{ $label ?? $value }}
    </a>
@elseif (is_string($value) && trim($value) !== '')
    <span class="url-link-plain {{ $class }}">{{ $value }}</span>
@endif