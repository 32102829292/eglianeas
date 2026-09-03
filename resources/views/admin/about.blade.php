@extends('layouts.dashboard')

@section('title', 'About Page — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>About Page</h1>
            <p>Mission, Vision, Core Values, and Certificates shown on the public About page.</p>
        </div>
        @if (! $editing)
            <a href="{{ route('admin.about', ['edit' => 1]) }}" class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                Edit
            </a>
        @endif
    </div>

    @if (! $editing)
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Mission &amp; Vision</h2>
            </div>
            <div class="profile-grid">
                <div class="profile-row">
                    <span class="profile-k">Mission</span>
                    <span class="profile-v">{{ $about->mission ?? '—' }}</span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Vision</span>
                    <span class="profile-v">{{ $about->vision ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Core Values</h2>
            </div>
            @if ($coreValues->count())
                <div class="services-grid">
                    @foreach ($coreValues as $value)
                        <div class="service-card">
                            <div class="service-icon" style="background:var(--sky-soft); color:var(--sky-deep);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                            </div>
                            <h3 style="font-size:15px;">{{ $value->label }}</h3>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted" style="margin-bottom:0;">No core values added yet.</p>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Certificates</h2>
            </div>
            @if ($certificates->count())
                <div class="cert-admin-grid">
                    @foreach ($certificates as $cert)
                        <div class="cert-admin-item">
                            @if ($cert->isImage())
                                <img src="{{ route('certificates.file', $cert) }}" alt="{{ $cert->label }}" class="cert-admin-thumb">
                            @else
                                <div class="cert-admin-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                </div>
                            @endif
                            <span class="cert-admin-label">{{ $cert->label }}</span>
                            <form method="POST" action="{{ route('admin.about.certificate.destroy', $cert) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this certificate?', message: 'This certificate will be removed from the About page.', danger: true, confirmLabel: 'Remove' });">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline danger btn-sm">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted" style="margin-bottom:0;">No certificates uploaded yet.</p>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('admin.about.update') }}">
            @csrf
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Mission &amp; Vision</h2>
                </div>
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
            </div>

            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Core Values</h2>
                </div>
                <div class="form-group">
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
            </div>

            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Save</button>
                @if ($hasData)
                    <a href="{{ route('admin.about') }}" class="btn btn-outline">Cancel</a>
                @endif
            </div>
        </form>

        <div class="card" style="margin-top:20px;">
            <div class="card-head">
                <h2 class="card-title">Certificates</h2>
            </div>
            @if ($certificates->count())
                <div class="cert-admin-grid" style="margin-bottom:20px;">
                    @foreach ($certificates as $cert)
                        <div class="cert-admin-item">
                            @if ($cert->isImage())
                                <img src="{{ route('certificates.file', $cert) }}" alt="{{ $cert->label }}" class="cert-admin-thumb">
                            @else
                                <div class="cert-admin-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                </div>
                            @endif
                            <span class="cert-admin-label">{{ $cert->label }}</span>
                            <form method="POST" action="{{ route('admin.about.certificate.destroy', $cert) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this certificate?', message: 'This certificate will be removed from the About page.', danger: true, confirmLabel: 'Remove' });">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline danger btn-sm">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.about.certificate.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="cert_label">Label</label>
                    <input class="form-control" id="cert_label" name="label" type="text" maxlength="100" required placeholder="e.g. BIR Certificate of Registration">
                    @error('label')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="cert_file">File</label>
                    <input class="form-control" id="cert_file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-hint">PDF, JPG, or PNG. Max 10 MB.</div>
                    @error('file')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Upload certificate</button>
            </form>
        </div>
    @endif

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Team Members</h2>
            </div>
            @if ($teamMembers->count())
                <div class="team-admin-grid">
                    @foreach ($teamMembers as $member)
                        <div class="team-admin-card">
                            <div class="team-admin-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="team-admin-info">
                                <h3>{{ $member->name }}</h3>
                                <span class="team-admin-position">{{ $member->position }}</span>
                                <span class="team-admin-rank">{{ $member->rank }}</span>
                                @if ($member->reports_to)
                                    <span class="team-admin-reports">Reports to: {{ $member->reports_to }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted" style="margin-bottom:0;">No team members added yet.</p>
            @endif
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
