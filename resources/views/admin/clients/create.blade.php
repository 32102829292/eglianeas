@extends('layouts.dashboard')

@section('title', 'Add Client — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Clients
    </a>

    <div class="page-head">
        <h1>Add client</h1>
        <p>Create a new client account with login credentials.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">New client account</h2>
        </div>

        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf

            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="name">Contact name</label>
                    <input class="form-control" id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="business_name">Business name</label>
                    <input class="form-control" id="business_name" name="business_name" type="text" value="{{ old('business_name') }}">
                    <div class="form-hint">Optional. Leave blank if not applicable.</div>
                    @error('business_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="section-divider"></div>

            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" required>
                    <div class="form-hint">Minimum 8 characters.</div>
                    @error('password')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
            </div>

            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Create client account</button>
                <a href="{{ route('admin.clients.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
