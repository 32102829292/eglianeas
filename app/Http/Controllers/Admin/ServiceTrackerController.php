<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClientConcern;
use App\Models\TeamMember;
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

        $isStaff = ! auth()->user()->isAdmin();
        $user = auth()->user();

        $scopeToOwn = function ($query) use ($isStaff, $user) {
            if (! $isStaff) {
                return;
            }
            $query->whereHas('assignments', function ($assignment) use ($user) {
                $assignment->where('staff_id', $user->id)
                    ->orWhereRaw('LOWER(staff_name) = ?', [mb_strtolower(trim((string) $user->name))]);
            });
        };

        $instances = TrackerInstance::query()
            ->with('service', 'client', 'assignments.staff', 'otherService')
            ->tap($scopeToOwn)
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
                    $query->where('staff_name', 'like', "%{$staff}%")
                        ->orWhereHas('staff', fn ($q) => $q->where('name', 'like', "%{$staff}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $scopedAll = TrackerInstance::query()
            ->with('assignments.staff')
            ->tap($scopeToOwn)
            ->get();

        return view('admin.service-tracker.index', [
            'instances' => $instances,
            'services' => TrackerService::ordered()->get(),
            'allStaff' => $scopedAll->flatMap->assignments
                ->map(fn (TrackerAssignment $a) => $a->displayName())
                ->filter()
                ->unique(fn (string $n) => mb_strtolower(trim($n)))
                ->sort()
                ->values(),
            'q' => $q,
            'activeStatus' => $status,
            'activeServiceId' => $serviceId,
            'activeStaff' => $staff,
            'badgeClasses' => [
                TrackerInstance::STATUS_TODO => 'badge-neutral',
                TrackerInstance::STATUS_IN_PROGRESS => 'badge-info',
                TrackerInstance::STATUS_ON_HOLD => 'badge-warn',
                TrackerInstance::STATUS_DONE => 'badge-success',
            ],
            'stats' => [
                'total' => $scopedAll->count(),
                'done' => $scopedAll->where('status', TrackerInstance::STATUS_DONE)->count(),
                'inProgress' => $scopedAll->where('status', TrackerInstance::STATUS_IN_PROGRESS)->count(),
                'todo' => $scopedAll->where('status', TrackerInstance::STATUS_TODO)->count(),
                'onHold' => $scopedAll->where('status', TrackerInstance::STATUS_ON_HOLD)->count(),
                'assignmentsTotal' => $scopedAll->flatMap->assignments->count(),
                'assignmentsDone' => $scopedAll->flatMap->assignments->where('completed', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create service instances.');

        return view('admin.service-tracker.create', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'services' => TrackerService::ordered()->get(),
            'staffRoster' => TeamMember::ordered()
                ->get(['name', 'position', 'user_id'])
                ->map(fn (TeamMember $member) => [
                    'name' => $member->name,
                    'user_id' => $member->user_id,
                    'label' => trim($member->name.' — '.$member->position),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can create service instances.');

        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:tracker_services,id'],
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

        $nameToUserId = TeamMember::query()
            ->whereNotNull('user_id')
            ->get(['name', 'user_id'])
            ->mapWithKeys(fn (TeamMember $member) => [mb_strtolower($member->name) => $member->user_id]);

        foreach ($staffNames as $name) {
            $instance->assignments()->create([
                'staff_name' => $name,
                'staff_id' => $nameToUserId[mb_strtolower($name)] ?? null,
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
            $assignment->completed
                ? "Marked \"{$assignment->staff_name}\" as done on \"{$assignment->instance?->service?->name}\"."
                : "Reopened \"{$assignment->staff_name}\" on \"{$assignment->instance?->service?->name}\".",
            $assignment->instance
        );

        return back()->with('status', 'Assignment status updated.');
    }

    private function authorizeManage(TrackerInstance $instance): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $instance->isAssignedTo($user), 403, 'You are not assigned to this service.');
    }

    public function start(TrackerInstance $instance): RedirectResponse
    {
        $this->authorizeManage($instance);

        try {
            $instance->startProcessing();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        ActivityLog::record(
            auth()->user(),
            'service.started',
            "Started \"{$instance->service?->name}\" for {$instance->client?->name}.",
            $instance
        );

        return back()->with('status', 'Service marked as in progress.');
    }

    public function hold(Request $request, TrackerInstance $instance): RedirectResponse
    {
        $this->authorizeManage($instance);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $instance->hold($validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        ActivityLog::record(
            auth()->user(),
            'service.on_hold',
            "Put \"{$instance->service?->name}\" on hold for {$instance->client?->name}: \"{$validated['reason']}\".",
            $instance
        );

        return back()->with('status', 'Service put on hold.');
    }

    public function resume(TrackerInstance $instance): RedirectResponse
    {
        $this->authorizeManage($instance);

        try {
            $instance->resume();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        ActivityLog::record(
            auth()->user(),
            'service.resumed',
            "Resumed \"{$instance->service?->name}\" for {$instance->client?->name}.",
            $instance
        );

        return back()->with('status', 'Service resumed.');
    }

    public function complete(TrackerInstance $instance): RedirectResponse
    {
        $this->authorizeManage($instance);

        try {
            $instance->complete();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        ActivityLog::record(
            auth()->user(),
            'service.completed',
            "Completed \"{$instance->service?->name}\" for {$instance->client?->name}.",
            $instance
        );

        return back()->with('status', 'Service completed.');
    }

    public function show(TrackerInstance $instance): View
    {
        $this->authorizeManage($instance);

        $instance->load('service', 'client', 'assignments.staff', 'history.user', 'otherService.serviceType');

        return view('admin.service-tracker.show', [
            'instance' => $instance,
            'badgeClasses' => [
                TrackerInstance::STATUS_TODO => 'badge-neutral',
                TrackerInstance::STATUS_IN_PROGRESS => 'badge-info',
                TrackerInstance::STATUS_ON_HOLD => 'badge-warn',
                TrackerInstance::STATUS_DONE => 'badge-success',
            ],
            'eventLabels' => [
                'service.created' => 'Service requested',
                'service.staff_assigned' => 'Staff assigned',
                'service.started' => 'Work started',
                'service.on_hold' => 'Service placed on hold',
                'service.resumed' => 'Work resumed',
                'service.completed' => 'Service completed',
                'admin.tracker_instance_created' => 'Instance created',
                'admin.tracker_assignment_toggled' => 'Assignment updated',
            ],
        ]);
    }

    public function summary(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can view the summary.');

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

        $allAssignments = TrackerAssignment::with('staff')->get();

        // Group by a canonical person key: the linked staff_id when available
        // (immune to name changes and casing variants), otherwise the
        // normalized free-text name for "Other / not listed" custom entries.
        $staffSummary = $allAssignments
            ->groupBy(function (TrackerAssignment $assignment) {
                return $assignment->staff_id !== null
                    ? 'id:'.$assignment->staff_id
                    : 'name:'.mb_strtolower(trim((string) $assignment->staff_name));
            })
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'name' => $first->displayName(),
                    'total' => $group->count(),
                    'done' => $group->where('completed', true)->count(),
                ];
            })
            ->sortByDesc(fn (array $entry) => $entry['total'])
            ->values();

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
