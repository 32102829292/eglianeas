@extends('layouts.dashboard')

@section('title', 'Announcements — Egliane Accounting Services')

@section('content')
    @php
        $me = auth()->user();
        $myInitials = collect(preg_split('/\s+/', trim($me->name ?? 'Egliane')))
            ->filter()->take(2)
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp
    <div class="announcements-page">
        <div class="page-head page-head-row">
            <div class="page-head-main">
                <span class="page-eyebrow">Communication</span>
                <h1>Announcements</h1>
                <p>Share important updates, reminders, and news with your clients.</p>
            </div>
            <div class="page-head-actions">
                @if ($announcements->count())
                    <a href="#ann-post" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        New announcement
                    </a>
                @endif
            </div>
        </div>

        <section class="card ann-compose" id="ann-post" aria-labelledby="ann-compose-title">
            <div class="ann-compose-head">
                <span class="ann-compose-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h15l-1.5 4L21 16H6a3 3 0 0 1-3-3m3-5a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3M9 16l1 4h3l-1-4"/></svg>
                </span>
                <div>
                    <h2 class="card-title" id="ann-compose-title">Create an announcement</h2>
                    <p class="card-sub">Publish an update that will appear on your public feed.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data" data-submit-label="Posting…" id="ann-compose-form">
                @csrf
                <div class="ann-compose-body">
                    <div class="ann-compose-fields">
                        <div class="ann-section">
                            <h3 class="ann-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                Content
                            </h3>
                            <div class="form-group">
                                <label class="form-label" for="announcement_title">Title <span class="form-label-optional">Optional</span></label>
                                <input class="form-control" id="announcement_title" name="title" type="text" maxlength="120" placeholder="Enter announcement title" value="{{ old('title') }}" data-ann-char-target="ann_title_count">
                                <div class="ann-field-hint">
                                    <span>Short headlines help your clients spot updates at a glance.</span>
                                    <span class="char-count" id="ann_title_count" aria-live="polite">0/120</span>
                                </div>
                                @error('title')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="announcement_body">Message</label>
                                <textarea class="form-control ann-message-input" id="announcement_body" name="body" rows="5" maxlength="2000" required placeholder="Write the announcement message…" data-ann-char-target="ann_body_count">{{ old('body') }}</textarea>
                                <div class="ann-field-hint">
                                    <span>This message appears on the public feed exactly as written.</span>
                                    <span class="char-count" id="ann_body_count" aria-live="polite">0/2000</span>
                                </div>
                                @error('body')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="ann-section">
                            <h3 class="ann-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                Cover image
                            </h3>
                            <div class="form-group">
                                <label class="form-label" for="announcement_image">Image <span class="form-label-optional">Optional</span></label>
                                <label class="ann-upload-drop" for="announcement_image">
                                    <span class="ann-upload-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    </span>
                                    <span class="ann-upload-copy" id="ann-upload-copy">Choose an image</span>
                                    <span class="ann-upload-hint">JPG, PNG, or WebP &middot; Maximum 5 MB</span>
                                    <input type="file" id="announcement_image" name="image" accept="image/jpeg,image/png,image/webp" class="ann-file">
                                </label>
                                <div class="ann-upload-preview" id="ann-upload-preview" hidden>
                                    <img id="ann-upload-img" alt="">
                                    <div class="ann-upload-meta">
                                        <div class="ann-upload-info">
                                            <span class="ann-upload-name" id="ann-upload-name"></span>
                                            <span class="ann-upload-size" id="ann-upload-size"></span>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm ann-upload-clear" id="ann-upload-clear">Remove</button>
                                    </div>
                                </div>
                                @error('image')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="ann-form-actions">
                            <button type="submit" class="btn btn-primary">Post announcement</button>
                            <button type="button" class="btn btn-outline" id="ann-clear">Clear draft</button>
                        </div>
                    </div>

                    <aside class="ann-preview-wrap" aria-label="Live preview">
                        <div class="ann-preview-card">
                            <div class="ann-preview-head">
                                <span class="ann-preview-title">Live preview</span>
                                <span class="ann-preview-badge">Preview</span>
                            </div>
                            <div class="ann-preview-inner" id="ann-preview">
                                <div class="ann-preview-empty" id="ann-preview-empty">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                    <span>Your announcement preview appears here as you type.</span>
                                </div>
                                <div class="ann-preview-body" id="ann-preview-body" hidden>
                                    <div class="ann-preview-meta">
                                        <span class="ann-preview-avatar" aria-hidden="true">{{ $myInitials }}</span>
                                        <span class="ann-preview-author">
                                            <b>{{ $me->name ?? 'Egliane' }}</b>
                                            <time>Just now</time>
                                        </span>
                                    </div>
                                    <div class="ann-preview-thumb" id="ann-preview-thumb" hidden>
                                        <img id="ann-preview-image" alt="">
                                    </div>
                                    <h4 class="ann-preview-title2" id="ann-preview-title"></h4>
                                    <p class="ann-preview-text" id="ann-preview-text"></p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </section>

        <div class="card" aria-labelledby="ann-recent-title">
            <div class="card-head">
                <h2 class="card-title" id="ann-recent-title">Recent announcements <span class="count-pill">{{ $announcements->count() }}</span></h2>
            </div>
            <div class="ann-feed">
                @forelse ($announcements as $announcement)
                    @php
                        $posterName = $announcement->poster?->name ?? 'Egliane Admin';
                    @endphp
                    <article class="ann-card @if ($announcement->hasImage()) has-cover @endif">
                        <div class="ann-thumb">
                            @if ($announcement->hasImage())
                                <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy">
                            @else
                                <span class="ann-thumb-placeholder" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                                </span>
                            @endif
                        </div>
                        <div class="ann-body">
                            <h3 class="ann-title">{{ $announcement->title ?: 'Announcement' }}</h3>
                            <div class="ann-meta">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <time datetime="{{ $announcement->posted_at?->toIso8601String() }}" title="{{ $announcement->posted_at?->format('M j, Y g:i A') }}">Posted {{ $announcement->posted_at?->format('M j, Y') ?? '—' }}</time>
                                <span class="ann-author" title="Posted by">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    {{ $posterName }}
                                </span>
                            </div>
                            @if ($announcement->body)
                                <p class="ann-text">{{ $announcement->body }}</p>
                            @endif
                        </div>
                        <div class="ann-actions">
                            @if (Route::has('admin.announcements.update'))
                                <a href="#" class="btn btn-outline btn-sm">Edit</a>
                            @endif
                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this announcement?', message: 'This announcement and its image will be removed.', danger: true, confirmLabel: 'Remove' });">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="empty-state compact">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                        <p>No announcements yet. Post the first one above.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('announcement_image');
    if (!input) return;

    var form = document.getElementById('ann-compose-form');
    var preview = document.getElementById('ann-upload-preview');
    var img = document.getElementById('ann-upload-img');
    var nameEl = document.getElementById('ann-upload-name');
    var sizeEl = document.getElementById('ann-upload-size');
    var copyEl = document.getElementById('ann-upload-copy');
    var clear = document.getElementById('ann-upload-clear');
    var annClear = document.getElementById('ann-clear');

    var titleInput = document.getElementById('announcement_title');
    var bodyInput = document.getElementById('announcement_body');
    var pvEmpty = document.getElementById('ann-preview-empty');
    var pvBody = document.getElementById('ann-preview-body');
    var pvThumb = document.getElementById('ann-preview-thumb');
    var pvImage = document.getElementById('ann-preview-image');
    var pvTitle = document.getElementById('ann-preview-title');
    var pvText = document.getElementById('ann-preview-text');

    function fmtSize(bytes) {
        if (!bytes && bytes !== 0) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function syncCounter(el) {
        var out = document.getElementById(el.getAttribute('data-ann-char-target'));
        if (!out) return;
        var max = parseInt(el.getAttribute('maxlength'), 10) || 0;
        var len = el.value.length;
        out.textContent = len + '/' + max;
        out.classList.toggle('over', max > 0 && len > max);
    }

    function initCounters() {
        var els = document.querySelectorAll('[data-ann-char-target]');
        Array.prototype.forEach.call(els, function (el) {
            syncCounter(el);
            el.addEventListener('input', function () { syncCounter(el); });
        });
    }

    function renderPreview() {
        var val = titleInput.value.trim() || 'Announcement';
        var text = bodyInput.value;
        var hasText = bodyInput.value.trim().length > 0;

        if (!titleInput.value.trim() && !text && !img.getAttribute('src')) {
            pvEmpty.hidden = false;
            pvBody.hidden = true;
            return;
        }
        pvEmpty.hidden = true;
        pvBody.hidden = false;
        pvTitle.textContent = val;
        pvText.textContent = text;
        pvText.hidden = !hasText;
        var hasCover = !!img.getAttribute('src');
        pvThumb.hidden = !hasCover;
        if (hasCover) pvImage.src = img.getAttribute('src');
        else pvImage.removeAttribute('src');
        pvBody.classList.toggle('is-image', hasCover);
    }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('Image is larger than 5 MB. Please choose a smaller file.');
            input.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            nameEl.textContent = file.name;
            sizeEl.textContent = fmtSize(file.size);
            preview.hidden = false;
            copyEl.textContent = 'Replace image';
            renderPreview();
        };
        reader.readAsDataURL(file);
    });

    [titleInput, bodyInput].forEach(function (el) {
        el.addEventListener('input', renderPreview);
    });

    clear.addEventListener('click', function () {
        input.value = '';
        preview.hidden = true;
        img.removeAttribute('src');
        nameEl.textContent = '';
        sizeEl.textContent = '';
        copyEl.textContent = 'Choose an image';
        renderPreview();
    });

    if (annClear) {
        annClear.addEventListener('click', function () {
            form.reset();
            [titleInput, bodyInput].forEach(syncCounter);
            input.value = '';
            preview.hidden = true;
            img.removeAttribute('src');
            nameEl.textContent = '';
            sizeEl.textContent = '';
            copyEl.textContent = 'Choose an image';
            renderPreview();
        });
    }

    initCounters();
    renderPreview();
})();
</script>
@endpush