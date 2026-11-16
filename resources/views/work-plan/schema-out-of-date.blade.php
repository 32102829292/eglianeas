@extends('layouts.dashboard')

@section('title', 'Work Plan - Database Out of Date')

@section('content')
<div class="container-fluid px-4 py-5">
    <div class="alert alert-warning border-0 shadow-sm" role="alert">
        <h4 class="alert-heading mb-2">
            <i class="fas fa-database me-2"></i>Database is out of date, run migrations.
        </h4>
        <p class="mb-3">
            The Work Plan module needs a few database changes before it can run. No data was changed.
        </p>

        @if (! empty($missing))
            <p class="mb-1 fw-semibold">Missing schema pieces:</p>
            <ul class="mb-3 small">
                @foreach ($missing as $item)
                    <li><code>{{ $item }}</code></li>
                @endforeach
            </ul>
        @endif

        <p class="mb-1 fw-semibold">Run these commands on the server:</p>
        <pre class="bg-dark text-light p-3 rounded small mb-0"><code>php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=WorkPlanLegendSeeder</code></pre>
    </div>
</div>
@endsection
