@extends('layouts.auth')

@section('title', 'Login — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Log in with your PIN or face.</p>

    @include('auth.partials.login-form')
@endsection
