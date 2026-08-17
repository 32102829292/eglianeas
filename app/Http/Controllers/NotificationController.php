<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->markAsRead();

        return redirect($notification->link ?? route('notifications.index'));
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->markAsRead();

        return back();
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->notifications()->unread()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
