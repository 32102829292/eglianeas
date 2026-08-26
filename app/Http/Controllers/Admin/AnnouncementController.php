<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
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
            'image' => ['nullable', 'file', 'image:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store(
                'announcements',
                'supabase'
            );
        }

        Announcement::query()->create(array_merge($validated, [
            'image_path' => $imagePath,
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
        $announcement->deleteImage();
        $announcement->delete();

        ActivityLog::record(auth()->user(), 'settings.announcement_deleted', 'Removed an announcement.');

        return back()->with('status', 'Announcement removed.');
    }
}
