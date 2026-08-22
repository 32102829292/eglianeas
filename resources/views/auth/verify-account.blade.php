@extends('layouts.auth')

@section('title', 'Verify your email — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Verify your email</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (! empty($noPending))
        <p class="auth-sub">We couldn&rsquo;t find a pending registration. Please create an account first.</p>
        <a href="{{ route('register') }}" class="btn btn-primary btn-block mt-2">Create an account</a>
    @elseif (! empty($alreadyVerified))
        <p class="auth-sub">Your email is already verified.</p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-block mt-2">Log in</a>
    @else
        <p class="auth-sub">We sent a 6-digit code to <b>{{ $email }}</b>. Enter it below to activate your account.</p>

        <form method="POST" action="{{ route('verify.account') }}" id="verifyCodeForm">
            @csrf
            <input type="hidden" name="code" id="codeValue">
            <div class="code-inputs">
                @for ($i = 0; $i < 6; $i++)
                    <input class="code-input" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit {{ $i + 1 }}">
                @endfor
            </div>
            @error('code')<div class="form-error text-center">{{ $message }}</div>@enderror
        </form>

        @if (app()->environment('local') && ! empty($devCode))
            <button type="button" class="btn btn-outline btn-sm mt-2" id="devCodeBtn" data-code="{{ $devCode }}">Use demo code ({{ $devCode }})</button>
        @endif

        <div class="resend-row">
            <span>Didn&rsquo;t get the code?</span>
            <button type="button" class="link" id="resendBtn" data-url="{{ route('verify.resend') }}">Resend</button>
            <span id="resendCountdown"></span>
        </div>

        <a href="{{ route('register') }}" class="btn btn-outline btn-block mt-2">Start over</a>
    @endif
@endsection

@push('scripts')
<script>
document.body.setAttribute('data-cooldown-until', '{{ $cooldownUntil ?? 0 }}');
</script>
@endpush
