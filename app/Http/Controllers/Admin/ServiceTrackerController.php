<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClientConcern;
use App\Models\TrackerAssignment;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceTrackerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');
        $serviceId = $request->get('service_id');
        $staff = trim((string) $request->get('staff'));

        $instances = TrackerInstance::query()
            ->with('service', 'client', 'assignments')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('client', function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%");
                })->orWhereHas('service', function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%");
                });
            })
            ->when($status !== '' && $status !== null, fn ($query) => $query->where('status', $status))
            ->when($serviceId, fn ($query) => $query->where('service_id', $serviceId))
            ->when($staff !== '', function ($query) use ($staff) {
                $query->whereHas('assignments', function ($query) use ($staff) {
                    $query->where('staff_name', 'like', "%{$staff}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $allInstances = TrackerInstance::query()->with('assignments')->get();
        $totalAssignments = $allInstances->flatMap->assignments->count();
        $doneAssignments = $allInstances->flatMap->assignments->where('completed', true)->count();

        return view('admin.service-tracker.index', [
            'instances' => $instances,
            'services' => TrackerService::ordered()->get(),
            'allStaff' => TrackerAssignment::distinct()->pluck('staff_name')->filter()->sort()->values(),
            'q' => $q,
            'activeStatus' => $status,
            'activeServiceId' => $serviceId,
            'activeStaff' => $staff,
            'stats' => [
                'total' => $allInstances->count(),
                'done' => $allInstances->where('status', TrackerInstance::STATUS_DONE)->count(),
                'todo' => $allInstances->where('status', TrackerInstance::STATUS_TODO)->count(),
                'assignmentsTotal' => $totalAssignments,
                'assignmentsDone' => $doneAssignments,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.service-tracker.create', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'services' => TrackerService::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:tracker_services,id'],
            'primary_responsible' => ['nullable', 'string', 'max:120'],
            'date_identified' => ['nullable', 'date'],
            'date_started' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'staff_names' => ['nullable', 'array'],
            'staff_names.*' => ['string', 'max:120'],
        ]);

        $validated['status'] = TrackerInstance::STATUS_TODO;

        $instance = TrackerInstance::create($validated);

        $staffNames = $request->input('staff_names', []);
        $staffNames = array_filter(array_map('trim', $staffNames));

        foreach ($staffNames as $name) {
            $instance->assignments()->create([
                'staff_name' => $name,
                'completed' => false,
            ]);
        }

        ActivityLog::record(
            auth()->user(),
            'admin.tracker_instance_created',
            "Created tracker instance for {$instance->service?->name} ({$instance->client?->name})."
        );

        return redirect()->route('admin.service-tracker.index')->with('status', 'Service instance created.');
    }

    public function toggleAssignment(TrackerAssignment $assignment): RedirectResponse
    {
        $assignment->toggleComplete();

        ActivityLog::record(
            auth()->user(),
            'admin.tracker_assignment_toggled',
            "Toggled \"{$assignment->staff_name}\" completion on \"{$assignment->instance?->service?->name}\"."
        );

        return back()->with('status', 'Assignment status updated.');
    }

    public function summary(): View
    {
        $services = TrackerService::ordered()->with('instances.assignments')->get();

        $serviceSummary = $services->map(function (TrackerService $service) {
            $instances = $service->instances;

            return [
                'service' => $service,
                'total' => $instances->count(),
                'done' => $instances->where('status', TrackerInstance::STATUS_DONE)->count(),
                'todo' => $instances->where('status', TrackerInstance::STATUS_TODO)->count(),
            ];
        })->filter(fn (array $entry) => $entry['total'] > 0)
            ->sortByDesc(fn (array $entry) => $entry['total'])
            ->values();

        $allAssignments = TrackerAssignment::all();
        $staffNames = $allAssignments->pluck('staff_name')->filter()->unique()->sort()->values();

        $staffSummary = $staffNames->map(function (string $name) use ($allAssignments) {
            $myAssignments = $allAssignments->where('staff_name', $name);

            return [
                'name' => $name,
                'total' => $myAssignments->count(),
                'done' => $myAssignments->where('completed', true)->count(),
            ];
        })->sortByDesc(fn (array $entry) => $entry['total'])->values();

        return view('admin.service-tracker.summary', [
            'serviceSummary' => $serviceSummary,
            'staffSummary' => $staffSummary,
            'overall' => [
                'total' => TrackerInstance::count(),
                'done' => TrackerInstance::where('status', TrackerInstance::STATUS_DONE)->count(),
                'todo' => TrackerInstance::where('status', TrackerInstance::STATUS_TODO)->count(),
            ],
        ]);
    }

    public function concerns(Request $request): View
    {
        $submittedBy = $request->get('submitted_by');
        $reviewed = $request->get('reviewed');

        $query = ClientConcern::with('client', 'relatedService')
            ->orderByDesc('date_identified')
            ->orderByDesc('id');

        if ($submittedBy === 'client') {
            $query->where('submitted_by', 'client');
        } elseif ($submittedBy === 'staff') {
            $query->where('submitted_by', 'staff');
        }

        if ($reviewed === '0') {
            $query->where('reviewed', false);
        } elseif ($reviewed === '1') {
            $query->where('reviewed', true);
        }

        $concerns = $query->get();

        return view('admin.service-tracker.concerns', [
            'concerns' => $concerns,
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'statuses' => ClientConcern::STATUSES,
            'services' => TrackerService::ordered()->get(),
            'submittedByFilter' => $submittedBy,
            'reviewedFilter' => $reviewed,
        ]);
    }

    public function storeConcern(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'date_identified' => ['required', 'date'],
            'description_of_issue' => ['required', 'string', 'max:2000'],
            'proposed_solution' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ClientConcern::STATUSES))],
            'related_service_id' => ['nullable', 'exists:tracker_services,id'],
        ]);

        $validated['submitted_by'] = ClientConcern::SUBMITTED_BY_STAFF;
        $validated['reviewed'] = true;

        ClientConcern::create($validated);

        ActivityLog::record(
            auth()->user(),
            'admin.client_concern_created',
            'Logged client concern for '.User::find($validated['client_id'])?->name.'.'
        );

        return back()->with('status', 'Client concern logged.');
    }

    public function updateConcern(Request $request, ClientConcern $concern): RedirectResponse
    {
        $validated = $request->validate([
            'description_of_issue' => ['required', 'string', 'max:2000'],
            'proposed_solution' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ClientConcern::STATUSES))],
            'related_service_id' => ['nullable', 'exists:tracker_services,id'],
        ]);

        $validated['reviewed'] = true;

        $concern->update($validated);

        ActivityLog::record(
            auth()->user(),
            'admin.client_concern_updated',
            "Updated client concern #{$concern->id} for ".$concern->client?->name.'.'
        );

        return back()->with('status', 'Concern updated.');
    }

    public function markReviewed(ClientConcern $concern): RedirectResponse
    {
        $concern->update(['reviewed' => true]);

        ActivityLog::record(
            auth()->user(),
            'admin.client_concern_reviewed',
            "Marked client concern #{$concern->id} as reviewed."
        );

        return back()->with('status', 'Concern marked as reviewed.');
    }

    public function destroyConcern(ClientConcern $concern): RedirectResponse
    {
        $concern->delete();

        ActivityLog::record(auth()->user(), 'admin.client_concern_deleted', 'Deleted a client concern record.');

        return back()->with('status', 'Concern deleted.');
    }

    public function clientsJson(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('business_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'business_name', 'email', 'client_code']);

        return response()->json($clients);
    }
}
