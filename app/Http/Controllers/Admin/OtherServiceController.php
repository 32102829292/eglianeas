<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\OtherService;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtherServiceController extends Controller
{
    public function fillUp(): View
    {
        return view('admin.other-services.fill-up', [
            'clients' => User::query()->where('role', User::ROLE_CLIENT)->orderBy('name')->get(),
            'serviceTypes' => ServiceType::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:users,id'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'custom_label' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'requested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (empty($validated['service_type_id']) && empty($validated['custom_label'])) {
            return back()->withErrors(['custom_label' => 'Please select a service type or enter a custom label.'])->withInput();
        }

        $validated['requested_at'] = $validated['requested_at'] ?? now();
        $validated['status'] = OtherService::STATUS_UNPAID;

        $service = OtherService::create($validated);

        Notification::create([
            'user_id' => $service->client_id,
            'title' => 'New service request',
            'body' => "A new \"{$service->serviceName()}\" service has been added to your account. Amount: {$service->money()}.",
            'type' => 'service',
            'link' => route('client.other-services.billing'),
        ]);

        $client = User::find($service->client_id);
        if ($client) {
            PushNotificationService::send($client, 'New service request', "A new \"{$service->serviceName()}\" service has been added. Amount: {$service->money()}.", route('client.other-services.billing'));
        }

        ActivityLog::record(
            auth()->user(),
            'admin.other_service_created',
            "Created \"{$service->serviceName()}\" service for {$service->client?->name}."
        );

        return redirect()->route('admin.other-services.billing')->with('status', 'Service request created.');
    }

    public function billing(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $services = OtherService::query()
            ->with('client', 'serviceType')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('client', function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.other-services.billing', [
            'services' => $services,
            'q' => $q,
            'stats' => [
                'total' => (float) OtherService::query()->sum('amount'),
                'paid' => (float) OtherService::query()->where('status', OtherService::STATUS_PAID)->sum('amount'),
                'outstanding' => (float) OtherService::query()->whereIn('status', [OtherService::STATUS_UNPAID, OtherService::STATUS_OVERDUE])->sum('amount'),
                'count' => OtherService::query()->count(),
            ],
        ]);
    }

    public function collections(Request $request): View
    {
        $status = $request->get('status');

        $services = OtherService::query()
            ->with('client', 'serviceType')
            ->whereIn('status', [OtherService::STATUS_UNPAID, OtherService::STATUS_OVERDUE])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $all = OtherService::query()->whereIn('status', [OtherService::STATUS_UNPAID, OtherService::STATUS_OVERDUE])->get();

        return view('admin.other-services.collections', [
            'services' => $services,
            'statuses' => OtherService::STATUSES,
            'stats' => [
                'outstanding' => (float) $all->sum('amount'),
                'overdueCount' => $all->filter(fn (OtherService $s) => $s->status === OtherService::STATUS_OVERDUE)->count(),
                'dueSoon' => $all->filter(fn (OtherService $s) => $s->status === OtherService::STATUS_UNPAID
                    && $s->due_date !== null
                    && $s->due_date->lte(now()->addDays(7)))->count(),
                'unpaidCount' => $all->where('status', OtherService::STATUS_UNPAID)->count(),
            ],
            'activeStatus' => $status,
        ]);
    }

    public function pay(Request $request, OtherService $otherService): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(OtherService::STATUSES))],
            'paid_at' => ['nullable', 'date'],
        ]);

        $otherService->status = $validated['status'];
        $otherService->paid_at = $validated['status'] === OtherService::STATUS_PAID
            ? (! empty($validated['paid_at']) ? \Illuminate\Support\Carbon::parse($validated['paid_at']) : now())
            : null;
        $otherService->save();

        if ($otherService->isPaid()) {
            Notification::create([
                'user_id' => $otherService->client_id,
                'title' => 'Payment received',
                'body' => "Your \"{$otherService->serviceName()}\" service payment of {$otherService->money()} has been marked as paid.",
                'type' => 'payment',
                'link' => route('client.other-services.collections'),
            ]);

            $client = User::find($otherService->client_id);
            if ($client) {
                PushNotificationService::send($client, 'Payment received', "Your \"{$otherService->serviceName()}\" payment of {$otherService->money()} has been marked as paid.", route('client.other-services.collections'));
            }
        }

        ActivityLog::record(
            auth()->user(),
            'admin.other_service_paid',
            "Marked \"{$otherService->serviceName()}\" for {$otherService->client?->name} as {$validated['status']}."
        );

        return back()->with('status', 'Service payment status updated.');
    }

    public function destroy(OtherService $otherService): RedirectResponse
    {
        $label = $otherService->serviceName();
        $client = $otherService->client;
        $otherService->delete();

        ActivityLog::record(auth()->user(), 'admin.other_service_deleted', "Deleted \"{$label}\" service for {$client?->name}.");

        return redirect()->route('admin.other-services.billing')->with('status', 'Service record deleted.');
    }

    public function receipt(OtherService $otherService): View
    {
        $otherService->load('client', 'serviceType');

        return view('admin.other-services.receipt', ['service' => $otherService]);
    }

    public function settings(): View
    {
        return view('admin.other-services.settings', [
            'serviceTypes' => ServiceType::ordered()->get(),
        ]);
    }

    public function storeServiceType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        ServiceType::query()->create([
            'label' => $validated['label'],
            'sort_order' => (int) ServiceType::query()->max('sort_order') + 1,
        ]);

        ActivityLog::record(auth()->user(), 'admin.service_type_added', "Added service type \"{$validated['label']}\".");

        return back()->with('status', 'Service type added.');
    }

    public function destroyServiceType(ServiceType $serviceType): RedirectResponse
    {
        $label = $serviceType->label;
        $serviceType->delete();

        ActivityLog::record(auth()->user(), 'admin.service_type_removed', "Removed service type \"{$label}\".");

        return back()->with('status', 'Service type removed.');
    }

    public function clientsJson(Request $request): \Illuminate\Http\JsonResponse
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
