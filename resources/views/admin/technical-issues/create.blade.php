@extends('layouts.dashboard')

@section('title', 'Report a Technical Issue — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Report a Technical Issue</h1>
            <p>Tell us what went wrong so the team can investigate, fix it, and keep you posted.</p>
        </div>
        <a href="{{ route('admin.technical-issues.index') }}" class="btn btn-outline">Back to My Reports</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.technical-issues.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Best-effort capture of the page the reporter came from. --}}
            <input type="hidden" name="page_url" value="{{ old('page_url', $pageUrl) }}">

            <section class="form-section">
                <div class="form-group">
                    <label class="form-label" for="title">Issue Title <span class="text-danger">*</span></label>
                    <input class="form-control" id="title" name="title" type="text" maxlength="255"
                           value="{{ old('title') }}" required placeholder="Short summary, e.g. Billing totals show the wrong amount.">
                    @error('title')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="">— Select a category —</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-control" id="priority" name="priority">
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', \App\Models\TechnicalIssue::PRIORITY_MEDIUM) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="form-error">{{ $message }}</div>@enderror
                        <div class="form-hint">How urgent this feels to you. The team may adjust it.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">What happened? <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="6" maxlength="5000" required
                              placeholder="Describe the problem, the steps you took, and what you expected to happen.">{{ old('description') }}</textarea>
                    @error('description')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">The more detail you give, the faster it can be fixed.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="screenshots">Screenshot (optional)</label>
                    <input class="form-control" id="screenshots" name="screenshots[]" type="file" multiple
                           accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    @error('screenshots')<div class="form-error">{{ $message }}</div>@enderror
                    @error('screenshots.*')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="form-hint">PNG, JPG or WebP. Up to 5 files, 5 MB each.</div>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="vertical-align:-3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Submit Report
                </button>
                <a href="{{ route('admin.technical-issues.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
