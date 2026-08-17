@extends('layouts.dashboard')

@section('title', 'Notifications')

@section('content')
    <div class="page-head">
        <h1>Notifications</h1>
        <p>Your recent updates from Egliane.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">All notifications</h2>
            @if ($notifications->total() > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Mark all read</button>
                </form>
            @endif
        </div>
        <div class="list">
            @forelse ($notifications as $notification)
                <a href="{{ route('notifications.open', $notification) }}" class="list-item @if ($notification->isUnread()) unread @endif">
                    <div class="list-item-main">
                        <div class="list-item-title">
                            {{ $notification->title }}
                            @if ($notification->isUnread())
                                <span class="ok-dot" title="Unread"></span>
                            @endif
                        </div>
                        @if ($notification->body)
                            <div class="list-item-sub">{{ $notification->body }}</div>
                        @endif
                        @if (($notification->reminder_count ?? 1) > 1)
                            <div class="reminder-count">Reminded {{ $notification->reminder_count }} times</div>
                        @endif
                        <div class="list-item-meta">{{ $notification->created_at->format('M j, Y g:i A') }}</div>
                    </div>
                    <span class="link">Open</span>
                </a>
            @empty
                <div class="empty-cell">You have no notifications yet.</div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
