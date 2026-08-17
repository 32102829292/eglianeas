<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\ClientProfile;
use App\Models\MasterlistExportLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = trim((string) $request->get('q'));
        $clients = $this->getFilteredClients($q);

        $this->logExport('csv', $clients->count(), $q);

        if ($clients->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $filename = 'Egliane-Client-Masterlist-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($clients) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Client ID', 'Client Name', 'Business Name', 'Business Type', 'Line of Business',
                'BIR Registration Type', 'Business Address', 'Contact No.', 'Email Address',
                '2nd Person Contact No.', '2nd Person Email Address', 'Birth Date',
                'TIN No.', "Mother's Maiden Name", "Father's Name", 'Status',
                'Payment Status', 'Date Started', 'Remarks',
            ]);

            foreach ($clients as $client) {
                $p = $client->profile;
                fputcsv($handle, [
                    $client->client_code ?? '',
                    $client->name,
                    $client->business_name ?? '',
                    $p?->business_type ?? '',
                    $p?->line_of_business ?? '',
                    $p?->bir_registration_type ?? '',
                    $p?->business_address ?? '',
                    $p?->contact_no ?? '',
                    $client->email,
                    $p?->second_contact_no ?? '',
                    $p?->second_email ?? '',
                    $p?->birth_date?->format('m/d/Y') ?? '',
                    $p?->tin_no ?? '',
                    $p?->mother_maiden_name ?? '',
                    $p?->father_name ?? '',
                    $p?->statusLabel() ?? '',
                    $p?->paymentStatusLabel() ?? '',
                    $p?->date_started?->format('m/d/Y') ?? '',
                    $p?->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $q = trim((string) $request->get('q'));
        $clients = $this->getFilteredClients($q);

        $this->logExport('pdf', $clients->count(), $q);

        if ($clients->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.clients.masterlist-pdf', [
            'clients' => $clients,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-Client-Masterlist-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    private function getFilteredClients(string $q): Collection
    {
        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->get()
            ->sortBy(fn (User $client) => strtolower($client->business_name ?: $client->name))
            ->values();
    }

    private function logExport(string $format, int $count, string $query): void
    {
        MasterlistExportLog::create([
            'admin_id' => auth()->id(),
            'format' => $format,
            'client_count' => $count,
            'filter_query' => $query ?: null,
            'exported_at' => now(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.masterlist_exported',
            "Exported client masterlist as {$format} ({$count} clients)."
        );
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
