@extends('layouts.guest')

@section('title', 'Verify your email — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Verify your email</h1>
    <p class="auth-sub">{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}</p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 verify-actions" style="margin-top: 22px;">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                {{ __('Resend Verification Email') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
@endsection
