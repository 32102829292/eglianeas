<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements', [
            'announcements' => Announcement::query()
                ->with('poster')
                ->latest('posted_at')
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        Announcement::query()->create(array_merge($validated, [
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]));

        ActivityLog::record(auth()->user(), 'settings.announcement_posted', 'Posted a new announcement.');

        $title = $validated['title'] ?: 'New Announcement';
        PushNotificationService::sendToClients($title, $validated['body'], route('home'));

        return back()->with('status', 'Announcement posted.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        ActivityLog::record(auth()->user(), 'settings.announcement_deleted', 'Removed an announcement.');

        return back()->with('status', 'Announcement removed.');
    }
}
