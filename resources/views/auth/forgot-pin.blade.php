@extends('layouts.auth')

@section('title', 'Forgot your PIN — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Forgot your PIN?</h1>
    <p class="auth-sub">Enter the email on your account and we&rsquo;ll send you a code to reset your PIN.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('forgot-pin.send') }}" id="forgotPinEmailForm">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@gmail.com">
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-2">Send verification code</button>
    </form>

    <p class="auth-footer"><a href="{{ route('login') }}">Back to login</a></p>
@endsection