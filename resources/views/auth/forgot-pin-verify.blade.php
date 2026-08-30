@extends('layouts.auth')

@section('title', 'Verify your code — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Enter verification code</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (! empty($hasSessionEmail))
        <p class="auth-sub">We sent a 6-digit code to <b>{{ $maskedEmail }}</b>. Enter it below to reset your PIN.</p>
    @else
        <p class="auth-sub">Enter your email and the 6-digit code we sent you to set up or reset your PIN.</p>
    @endif

    <form method="POST" action="{{ route('forgot-pin.verify.post') }}" id="verifyCodeForm">
        @csrf
        @if (! empty($hasSessionEmail))
            <input type="hidden" name="email" value="{{ $email }}">
        @else
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            </div>
        @endif
        <input type="hidden" name="code" id="codeValue">
        <div class="code-inputs">
            @for ($i = 0; $i < 6; $i++)
                <input class="code-input" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit {{ $i + 1 }}">
            @endfor
        </div>
        @error('code')<div class="form-error text-center">{{ $message }}</div>@enderror
        @error('email')<div class="form-error text-center">{{ $message }}</div>@enderror
    </form>

    @if (app()->environment('local') && ! empty($devCode))
        <button type="button" class="btn btn-outline btn-sm mt-2" id="devCodeBtn" data-code="{{ $devCode }}">Use demo code ({{ $devCode }})</button>
    @endif

    <div class="resend-row">
        <span>Didn&rsquo;t get the code?</span>
        <button type="button" class="link" id="resendBtn" data-url="{{ route('forgot-pin.resend') }}">Resend</button>
        <span id="resendCountdown"></span>
    </div>

    <a href="{{ route('forgot-pin') }}" class="btn btn-outline btn-block mt-2">Start over</a>
@endsection

@push('scripts')
<script>
document.body.setAttribute('data-cooldown-until', '{{ $cooldownUntil ?? 0 }}');
</script>
@endpush