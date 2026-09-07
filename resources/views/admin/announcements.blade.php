@extends('layouts.dashboard')

@section('title', 'Announcements — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Announcements</h1>
            <p>Post updates that appear as a feed on the landing page.</p>
        </div>
        <div class="page-head-actions">
            @if ($announcements->count())
                <a href="#ann-post" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New announcement
                </a>
            @endif
        </div>
    </div>

    <div class="card ann-compose" id="ann-post">
        <div class="card-head">
            <h2 class="card-title">Post an announcement</h2>
        </div>
        <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label" for="announcement_title">Title</label>
                <input class="form-control" id="announcement_title" name="title" type="text" maxlength="120" placeholder="Enter announcement title" value="{{ old('title') }}">
                @error('title')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="announcement_body">Message</label>
                <textarea class="form-control ann-message-input" id="announcement_body" name="body" rows="5" maxlength="2000" required placeholder="Write the announcement message&hellip;">{{ old('body') }}</textarea>
                @error('body')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="announcement_image">Image</label>
                <label class="ann-upload-drop" for="announcement_image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span class="ann-upload-copy" id="ann-upload-copy">Choose image</span>
                    <span class="ann-upload-hint">JPG, PNG, or WebP &middot; Maximum 5 MB</span>
                    <input type="file" id="announcement_image" name="image" accept="image/jpeg,image/png,image/webp" class="ann-file">
                </label>
                <div class="ann-upload-preview" id="ann-upload-preview" hidden>
                    <img id="ann-upload-img" alt="Selected image preview">
                    <div class="ann-upload-meta">
                        <span class="ann-upload-name" id="ann-upload-name"></span>
                        <button type="button" class="btn btn-link btn-sm" id="ann-upload-clear">Remove</button>
                    </div>
                </div>
                @error('image')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Post announcement</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Recent announcements <span class="count-pill">{{ $announcements->count() }}</span></h2>
        </div>
        <div class="ann-feed">
            @forelse ($announcements as $announcement)
                <article class="ann-card">
                    <div class="ann-thumb">
                        @if ($announcement->hasImage())
                            <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy">
                        @else
                            <span class="ann-thumb-placeholder" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 11-5.8-1.6"/></svg>
                            </span>
                        @endif
                    </div>
                    <div class="ann-body">
                        <h3 class="ann-title">{{ $announcement->title ?: 'Announcement' }}</h3>
                        <div class="ann-meta">
                            <time title="{{ $announcement->posted_at?->format('M j, Y g:i A') }}">Posted {{ $announcement->posted_at?->format('M j, Y') ?? '—' }}</time>
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 11-5.8-1.6"/></svg>
                    <p>No announcements yet. Post the first one above.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('announcement_image');
    var preview = document.getElementById('ann-upload-preview');
    var img = document.getElementById('ann-upload-img');
    var nameEl = document.getElementById('ann-upload-name');
    var copyEl = document.getElementById('ann-upload-copy');
    var clear = document.getElementById('ann-upload-clear');
    if (!input) return;

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
            preview.hidden = false;
            copyEl.textContent = 'Replace image';
        };
        reader.readAsDataURL(file);
    });

    clear.addEventListener('click', function () {
        input.value = '';
        preview.hidden = true;
        img.removeAttribute('src');
        copyEl.textContent = 'Choose image';
    });
})();
</script>
@endpush