@extends('layouts.auth')

@section('title', 'Login — Egliane Accounting Services')

@section('login-shell')
    <div class="login-shell">

        {{-- Left: branding panel (desktop) --}}
        <aside class="login-brand" aria-hidden="false">
            <a href="{{ route('home') }}" class="login-brand__logo">
                <img src="/images/logo-icon.png" alt="Egliane Accounting Services logo">
                <span class="login-brand__name">Egliane <small>Accounting Services</small></span>
            </a>
            <p class="login-brand__tag">Secure access to your business account.</p>
            <ul class="login-benefits">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    Billing statements
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    Tax and filing updates
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    Secure client portal
                </li>
            </ul>
            <div class="login-brand__viz" aria-hidden="true">
                <div class="login-brand__viz-head">
                    <span class="login-brand__viz-title">Client snapshot</span>
                    <span class="login-brand__viz-badge">Live</span>
                </div>
                <div class="login-brand__viz-row">
                    <span>Statements issued</span><strong>1,284</strong>
                </div>
                <div class="login-brand__viz-row">
                    <span>Filings completed on time</span><strong>96%</strong>
                </div>
                <div class="login-brand__viz-chart">
                    <i></i><i></i><i></i><i></i><i></i><i></i>
                </div>
            </div>
            <div class="login-seal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Secure client portal
            </div>
            <p class="login-brand__foot">Bookkeeping &middot; Tax &middot; Payroll</p>
        </aside>

        {{-- Right: login card --}}
        <div class="login-card">
            <a href="{{ route('home') }}" class="login-card__brand">
                <img src="/images/logo-icon.png" alt="Egliane Accounting Services logo">
                <span>Egliane Accounting Services</span>
            </a>
            <h1 class="login-card__title">Welcome back</h1>
            <p class="login-card__sub">Sign in securely to continue to your Egliane account.</p>

            @include('auth.partials.login-form')

            <p class="login-secure-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Secure sign-in &middot; Egliane Accounting Services
            </p>
        </div>
    </div>
@endsection