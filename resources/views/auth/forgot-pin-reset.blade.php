@extends('layouts.auth')

@section('title', 'Set a new PIN — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Set your new PIN</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <p class="auth-sub">Choose a new 4-digit PIN. You&rsquo;ll enter it every time you log in.</p>

    <form method="POST" action="{{ route('forgot-pin.reset.post') }}" id="forgotPinForm">
        @csrf
        <input type="hidden" name="pin" id="forgot_pin">
        <input type="hidden" name="pin_confirmation" id="forgot_pin_confirmation">

        <div class="pin-panel">
            <p class="reg-phase-label" id="forgotPinPhaseLabel">Set your new PIN</p>
            <div class="pin-display">
                @for ($i = 0; $i < 4; $i++)<span class="pin-dot"></span>@endfor
            </div>
            <div class="keypad" id="forgotPinKeypad">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                    <button type="button" class="key" data-key="{{ $n }}">{{ $n }}</button>
                @endforeach
                <span class="keypad-blank"></span>
                <button type="button" class="key" data-key="0">0</button>
                <button type="button" class="key backspace" data-key="back" aria-label="Delete">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
                </button>
                <button type="button" class="key ok-key" data-key="ok" aria-label="Confirm" disabled>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
            <p class="form-hint text-center">Your PIN is 4 digits. Press OK to confirm.</p>
        </div>
        <div class="form-error text-center" id="forgotPinError" hidden></div>
        @error('pin')<div class="form-error text-center">{{ $message }}</div>@enderror
        @error('pin_confirmation')<div class="form-error text-center">{{ $message }}</div>@enderror
    </form>

    <p class="auth-footer"><a href="{{ route('login') }}">Cancel and go back to login</a></p>
@endsection