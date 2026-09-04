<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientConcern;
use App\Models\Notification;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ServiceTrackerController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $instances = $user->trackerInstances()
            ->with('service', 'assignments')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $all = $user->trackerInstances()->with('assignments')->get();

        return view('client.service-tracker.index', [
            'instances' => $instances,
            'summary' => [
                'total' => $all->count(),
                'done' => $all->where('status', TrackerInstance::STATUS_DONE)->count(),
                'todo' => $all->where('status', TrackerInstance::STATUS_TODO)->count(),
            ],
        ]);
    }

    public function concerns(): View
    {
        $concerns = auth()->user()->clientConcerns()
            ->with('relatedService')
            ->orderByDesc('date_identified')
            ->orderByDesc('id')
            ->get();

        return view('client.service-tracker.concerns', [
            'concerns' => $concerns,
            'statuses' => ClientConcern::STATUSES,
            'services' => TrackerService::ordered()->get(),
        ]);
    }

    public function storeConcern(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description_of_issue' => ['required', 'string', 'max:2000'],
            'related_service_id' => ['nullable', 'exists:tracker_services,id'],
        ]);

        $concern = ClientConcern::create([
            'client_id' => auth()->id(),
            'date_identified' => now()->toDateString(),
            'description_of_issue' => $validated['description_of_issue'],
            'related_service_id' => $validated['related_service_id'] ?? null,
            'status' => ClientConcern::STATUS_SELDOM,
            'submitted_by' => ClientConcern::SUBMITTED_BY_CLIENT,
            'reviewed' => false,
        ]);

        $staff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])->get();
        foreach ($staff as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'title' => 'New Client Concern',
                'body' => auth()->user()->name . ' submitted a concern: ' . Str::limit($concern->description_of_issue, 80),
                'type' => 'client_concern',
                'group_key' => 'client-concern-' . $concern->id,
                'link' => route('admin.service-tracker.concerns'),
                'reminder_count' => 1,
            ]);

            PushNotificationService::send($recipient, 'New Client Concern', auth()->user()->name . ' submitted a concern.', route('admin.service-tracker.concerns'));
        }

        return back()->with('status', 'Your concern has been submitted. Our team will review it shortly.');
    }
}
