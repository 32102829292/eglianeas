@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-danger m-0']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
