@extends('layouts.dashboard')

@section('title', 'Create Kaizen Concern — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Create Kaizen Concern</h1>
        <p>Add a new workplace challenge or opportunity for improvement.</p>
    </div>

    {{-- Same fields and same POST endpoint as the Create Concern modal; this is
         the full-page fallback for a short screen or a deep link. --}}
    <div class="card card-narrow">
        <form method="POST" action="{{ route('admin.kaizen-concerns.store') }}">
            @csrf
            @include('admin.kaizen-concerns.partials.create-fields')
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Create Concern</button>
                <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
