<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->withCount(['billings', 'documents'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->get()
            ->map(function (User $client): array {
                $profile = $client->profile;

                return [
                    'user' => $client,
                    'profile' => $profile,
                    'status' => $profile?->status ?? ClientProfile::STATUS_PENDING,
                    'payment_status' => $profile?->payment_status,
                    'outstanding' => (float) $client->billings()
                        ->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])
                        ->sum('total'),
                ];
            })
            ->sortBy(fn (array $entry) => strtolower($entry['user']->business_name ?: $entry['user']->name))
            ->values();

        return view('admin.clients.index', [
            'clients' => $clients,
            'q' => $q,
            'statuses' => ClientProfile::STATUSES,
            'statusNotes' => ClientProfile::STATUS_NOTES,
        ]);
    }

    public function show(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $profile = $client->getClientProfile();

        return view('admin.clients.show', [
            'client' => $client,
            'profile' => $profile,
            'statuses' => ClientProfile::STATUSES,
            'statusNotes' => ClientProfile::STATUS_NOTES,
            'billingStats' => [
                'count' => $client->billings()->count(),
                'paid' => $client->billings()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
            ],
            'documentCount' => $client->documents()->count(),
        ]);
    }

    public function edit(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        return view('admin.clients.edit', [
            'client' => $client,
            'profile' => $client->getClientProfile(),
            'statuses' => ClientProfile::STATUSES,
            'statusNotes' => ClientProfile::STATUS_NOTES,
            'businessTypes' => ClientProfile::BUSINESS_TYPES,
            'lobOptions' => ClientProfile::LINE_OF_BUSINESS_OPTIONS,
            'regTypes' => ClientProfile::BIR_REGISTRATION_TYPES,
        ]);
    }

    public function update(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$client->id],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'line_of_business' => ['nullable', 'string', 'max:255'],
            'line_of_business_other' => ['nullable', 'string', 'max:255'],
            'bir_registration_type' => ['nullable', 'string', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_no' => ['nullable', 'string', 'max:40'],
            'second_contact_no' => ['nullable', 'string', 'max:40'],
            'second_email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'tin_no' => ['nullable', 'string', 'max:40'],
            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', array_keys(ClientProfile::STATUSES))],
            'payment_status' => ['nullable', 'in:paid,partial,unpaid'],
            'date_started' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($validated['line_of_business'] ?? null) === 'Other') {
            $validated['line_of_business'] = ($validated['line_of_business_other'] ?? null) ?: null;
        }
        unset($validated['line_of_business_other']);

        $userData = array_intersect_key($validated, array_flip(['name', 'email', 'business_name']));
        $client->fill($userData);
        $client->save();

        $profileData = $validated;
        unset($profileData['name'], $profileData['email'], $profileData['business_name']);

        foreach ($profileData as $key => $value) {
            if ($value === '') {
                $profileData[$key] = null;
            }
        }

        // Never persist masked values if the admin did not reveal them.
        if (str_contains((string) ($profileData['tin_no'] ?? ''), 'X')) {
            $profileData['tin_no'] = $client->profile?->tin_no;
        }
        foreach (['mother_maiden_name', 'father_name'] as $field) {
            if (str_contains((string) ($profileData[$field] ?? ''), '•')) {
                $profileData[$field] = $client->profile?->{$field};
            }
        }

        $profile = $client->getClientProfile();
        $profile->fill($profileData);
        $profile->save();

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(auth()->user(), 'admin.client_updated', "Updated the client record for {$displayName}.");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client record updated.');
    }
}
