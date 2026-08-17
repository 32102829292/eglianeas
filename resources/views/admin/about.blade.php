@extends('layouts.dashboard')

@section('title', 'About Page — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>About Page</h1>
        <p>Edit the Mission, Vision, and Core Values shown on the public About page.</p>
    </div>

    <div class="card">
        <h3 class="card-title">Mission &amp; Vision</h3>
        <form method="POST" action="{{ route('admin.about.update') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="mission">Mission</label>
                <textarea class="form-control" id="mission" name="mission" rows="3" maxlength="2000" required>{{ old('mission', $about->mission) }}</textarea>
                @error('mission')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="vision">Vision</label>
                <textarea class="form-control" id="vision" name="vision" rows="3" maxlength="2000" required>{{ old('vision', $about->vision) }}</textarea>
                @error('vision')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Core Values</label>
                <div id="valueRows">
                    @foreach ($coreValues as $value)
                        <div class="rule-row">
                            <input class="form-control" name="values[]" value="{{ $value->label }}" maxlength="100" placeholder="Value label">
                            <button type="button" class="btn btn-outline btn-sm danger" data-remove-rule>&times;</button>
                        </div>
                    @endforeach
                    @if ($coreValues->isEmpty())
                        <div class="rule-row">
                            <input class="form-control" name="values[]" value="" maxlength="100" placeholder="Value label">
                            <button type="button" class="btn btn-outline btn-sm danger" data-remove-rule>&times;</button>
                        </div>
                    @endif
                </div>
                <button type="button" class="btn btn-outline btn-sm mt-2" id="addValue">+ Add value</button>
            </div>

            <button type="submit" class="btn btn-primary mt-2">Save</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('#addValue')) {
        var row = document.createElement('div');
        row.className = 'rule-row';
        row.innerHTML = '<input class="form-control" name="values[]" maxlength="100" placeholder="Value label">' +
            '<button type="button" class="btn btn-outline btn-sm danger" data-remove-rule>&times;</button>';
        document.getElementById('valueRows').appendChild(row);
    }
    if (e.target.closest('[data-remove-rule]')) {
        var row = e.target.closest('.rule-row');
        if (row) row.remove();
    }
});
</script>
@endpush
