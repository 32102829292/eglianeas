@extends('layouts.dashboard')

@section('title', 'Profile — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Profile</h1>
        <p>Your account details and privacy.</p>
    </div>

    <div class="grid-2">
        <div class="card">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
@endsection
