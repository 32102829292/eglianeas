@extends('layouts.auth')

@section('title', 'Login — Egliane Accounting Services')

@section('login-shell')
    <div class="login-shell">

        {{-- Left: branding panel (desktop) --}}
        <aside class="login-brand" aria-hidden="false">
            <span class="login-brand__decor" aria-hidden="true">
                <i class="lp-blob lp-blob--a"></i>
                <i class="lp-blob lp-blob--b"></i>

                <svg class="lp-obj lp-obj--pie" viewBox="0 0 84 84" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="42" cy="42" r="30" stroke="rgba(90,179,240,0.30)" stroke-width="7"/>
                    <path d="M42 12a30 30 0 0 1 26 15" stroke="rgba(46,155,222,0.70)" stroke-width="7" stroke-linecap="round"/>
                    <path d="M68 42a30 30 0 0 1-9 21" stroke="rgba(46,155,222,0.35)" stroke-width="7" stroke-linecap="round"/>
                    <circle cx="42" cy="42" r="4" fill="rgba(46,155,222,0.60)"/>
                </svg>

                <svg class="lp-obj lp-obj--doc" viewBox="0 0 96 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 6h44l20 20v78H16z" fill="rgba(255,255,255,0.95)" stroke="rgba(46,155,222,0.45)" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M60 6v20h20" fill="rgba(255,255,255,0.95)" stroke="rgba(46,155,222,0.45)" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M28 44h40M28 54h40M28 64h40" stroke="rgba(46,155,222,0.30)" stroke-width="2" stroke-linecap="round"/>
                    <rect x="28" y="74" width="9" height="22" rx="2" fill="rgba(90,179,240,0.35)"/>
                    <rect x="41" y="68" width="9" height="28" rx="2" fill="rgba(46,155,222,0.55)"/>
                    <rect x="54" y="80" width="9" height="16" rx="2" fill="rgba(90,179,240,0.30)"/>
                </svg>

                <svg class="lp-obj lp-obj--book" viewBox="0 0 120 84" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 20C46 10 28 8 10 10v52c18-2 36 0 50 12 14-12 32-14 50-12V10C92 8 74 10 60 20z" fill="rgba(255,255,255,0.95)" stroke="rgba(46,155,222,0.45)" stroke-width="2"/>
                    <path d="M60 20v54" stroke="rgba(46,155,222,0.50)" stroke-width="2"/>
                    <path d="M20 30h24M20 40h20M20 50h12" stroke="rgba(46,155,222,0.30)" stroke-width="2" stroke-linecap="round"/>
                    <path d="M76 30h24M76 40h20" stroke="rgba(46,155,222,0.30)" stroke-width="2" stroke-linecap="round"/>
                    <path d="M70 52h26" stroke="rgba(46,155,222,0.25)" stroke-width="2" stroke-linecap="round"/>
                </svg>

                <i class="lp-dot lp-dot--1"></i>
            </span>
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