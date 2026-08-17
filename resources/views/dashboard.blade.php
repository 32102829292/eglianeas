@extends('layouts.dashboard')

@section('title', 'Dashboard — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Dashboard</h1>
        <p>Welcome back, {{ $user->name }}.</p>
    </div>

    <div class="card">
        <p>You&rsquo;re logged in!</p>
    </div>
@endsection
