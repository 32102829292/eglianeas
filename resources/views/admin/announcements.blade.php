@extends('layouts.dashboard')

@section('title', 'Announcements — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Announcements</h1>
        <p>Post updates that appear as a feed on the landing page, newest first.</p>
    </div>

    <div class="card card-narrow">
        <h3 class="card-title">Post an announcement</h3>
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="mb-2" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label" for="announcement_title">Title <span class="opt-tag">(optional)</span></label>
                <input class="form-control" id="announcement_title" name="title" type="text" maxlength="120" placeholder="e.g. BIR deadline reminder">
                @error('title')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="announcement_body">Message</label>
                <textarea class="form-control" id="announcement_body" name="body" rows="3" maxlength="2000" required placeholder="What do clients need to know?">{{ old('body') }}</textarea>
                @error('body')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="announcement_image">Image <span class="opt-tag">(optional)</span></label>
                <input class="form-control" id="announcement_image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="form-hint">JPG, PNG, or WebP. Max 5 MB.</div>
                @error('image')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary mt-2">Post announcement</button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title">Posted</h3>
        @if ($announcements->count())
            <div class="list">
                @foreach ($announcements as $announcement)
                    <div class="list-item" style="align-items: flex-start;">
                        <div class="list-item-main">
                            <div class="list-item-title">
                                @if ($announcement->title)
                                    <span>{{ $announcement->title }}</span>
                                @else
                                    <span>Announcement</span>
                                @endif
                            </div>
                            @if ($announcement->hasImage())
                                <div style="margin: 8px 0;">
                                    <img src="{{ $announcement->imageUrl() }}" alt="Announcement image" style="max-width: 320px; max-height: 200px; border-radius: 6px; border: 1px solid var(--border, #e5e7eb);">
                                </div>
                            @endif
                            <div class="list-item-sub">{{ Str::limit($announcement->body, 120) }}</div>
                            <div class="list-item-meta">
                                {{ $announcement->poster?->name ?? 'Egliane Admin' }} &middot;
                                <time title="{{ $announcement->posted_at?->format('M j, Y g:i A') }}">{{ $announcement->posted_at?->diffForHumans() }}</time>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return egliane.confirm.form(this, { title: 'Remove this announcement?', message: 'This announcement and its image will be removed.', danger: true, confirmLabel: 'Remove' });">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link danger">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="muted" style="margin-bottom:0;">No announcements yet.</p>
        @endif
    </div>
@endsection
