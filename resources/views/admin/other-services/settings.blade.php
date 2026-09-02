@extends('layouts.dashboard')

@section('title', 'Manage Service Types — Other Services — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Manage Service Types</h1>
            <p>Available options shown in the Other Services dropdown.</p>
        </div>
        <a href="{{ route('admin.other-services.settings') }}" class="btn btn-outline btn-sm" style="display:none">Back</a>
        <a href="{{ route('admin.other-services.billing') }}" class="btn btn-outline btn-sm">Back to Other Services</a>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Service Types</h3>
            <p class="card-sub">These appear in the search dropdown when creating a new service request.</p>
        </div>

        <div class="chip-list">
            @forelse ($serviceTypes as $type)
                <div class="chip-row">
                    <span class="chip-label">{{ $type->label }}</span>
                    <form method="POST" action="{{ route('admin.other-services.service-types.destroy', $type) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this service type?', message: 'It will no longer appear in the Other Services dropdown.', danger: true, confirmLabel: 'Remove' });">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link danger">&times;</button>
                    </form>
                </div>
            @empty
                <p class="muted" style="margin:0;">No service types yet. Add one below.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.other-services.service-types.store') }}" class="inline-add mt-2">
            @csrf
            <input class="form-control" name="label" type="text" maxlength="120" placeholder="Service type label, e.g. Business Registration" required>
            <button type="submit" class="btn btn-outline">Add service type</button>
        </form>
        @error('label')<div class="form-error">{{ $message }}</div>@enderror
    </div>
@endsection
