<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BirFormStatus;
use App\Models\ClientInfoEntry;
use App\Models\ClientProfile;
use App\Models\MasterlistExportLog;
use App\Models\User;
use App\Services\GeocodingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->withCount(['billings', 'documents'])
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
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

    public function create(): View
    {
        return view('admin.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_CLIENT,
            'business_name' => $validated['business_name'] ?? null,
            'email_verified_at' => now(),
        ]);

        $user->getClientProfile();

        ActivityLog::record(auth()->user(), 'admin.client_created', "Created client account for {$user->name} ({$user->email}).");

        return redirect()->route('admin.clients.show', $user)->with('status', 'Client account created.');
    }

    public function destroy(User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $displayName = $client->business_name ?: $client->name;
        $client->delete();

        ActivityLog::record(auth()->user(), 'admin.client_deleted', "Deleted client account for {$displayName}.");

        return redirect()->route('admin.clients.index')->with('status', 'Client account deleted.');
    }

    public function exportXlsx(Request $request): StreamedResponse
    {
        $q = trim((string) $request->get('q'));
        $clients = $this->getFilteredClients($q);

        $this->logExport('xlsx', $clients->count(), $q);

        if ($clients->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Egliane-Client-Masterlist-'.now()->format('Y-m-d').'.xlsx"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($clients) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'Client ID', 'Taxpayer Name', 'Business Name', 'Taxpayer\'s Name', 'Business Type', 'Type of Taxpayer',
                'Line of Business', 'BIR Registration Type', 'Business Address', 'Contact No.', 'Email Address',
                '2nd Contact Name', '2nd Person Contact No.', '2nd Person Email Address', 'Birth Date',
                'TIN No.', "Mother's Maiden Name", "Father's Name", 'Status',
                'Payment Status', 'Date Started', 'Remarks',
            ];

            $colWidths = [14, 20, 24, 24, 20, 18, 20, 18, 30, 16, 26, 18, 16, 24, 14, 16, 22, 20, 12, 14, 14, 24];

            foreach ($headers as $col => $header) {
                $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                $cell = $sheet->getCell("{$colLetter}1");
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
                $sheet->getColumnDimension($colLetter)->setWidth($colWidths[$col]);
            }

            $row = 2;
            foreach ($clients as $client) {
                $p = $client->profile;
                $values = [
                    $client->client_code ?? '',
                    $client->name,
                    $client->business_name ?? '',
                    $p?->taxpayer_name ?? '',
                    $p?->business_type ?? '',
                    $p?->taxpayer_type ?? '',
                    $p?->line_of_business ?? '',
                    $p?->bir_registration_type ?? '',
                    $p?->business_address ?? '',
                    $p?->contact_no ?? '',
                    $client->email,
                    $p?->second_contact_name ?? '',
                    $p?->second_contact_display ?? '',
                    $p?->second_email ?? '',
                    $p?->birth_date?->format('m/d/Y') ?? '',
                    $p?->tin_no ?? '',
                    $p?->mother_maiden_name ?? '',
                    $p?->father_name ?? '',
                    $p?->statusLabel() ?? '',
                    $p?->paymentStatusLabel() ?? '',
                    $p?->date_started?->format('m/d/Y') ?? '',
                    $p?->remarks ?? '',
                ];

                foreach ($values as $col => $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->getCell("{$colLetter}{$row}")->setValue($value);
                }
                $row++;
            }

            $sheet->freezePane('A2');

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setIncludeCharts(false);
            $writer->save('php://output');
        }, 200, $headers);
    }

    public function exportPdf(Request $request): Response
    {
        $q = trim((string) $request->get('q'));
        $clients = $this->getFilteredClients($q);

        $this->logExport('pdf', $clients->count(), $q);

        if ($clients->isEmpty()) {
            abort(404, 'No clients found for the current filter.');
        }

        $pdf = Pdf::loadView('admin.clients.masterlist-pdf', [
            'clients' => $clients,
        ])->setPaper('a4', 'landscape');

        $filename = 'Egliane-Client-Masterlist-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    private function applySearch($query, string $q): void
    {
        $digits = preg_replace('/[^0-9]/', '', $q);

        $query->where(function ($query) use ($q, $digits) {
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('business_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhereHas('profile', function ($profile) use ($q, $digits) {
                    $profile->where('tin_no', 'like', "%{$q}%");

                    if ($digits !== '') {
                        $normalized = "REPLACE(REPLACE(tin_no, '-', ''), ' ', '')";
                        $profile->orWhereRaw("{$normalized} like ?", ["%{$digits}%"]);
                    }
                });
        });
    }

    private function getFilteredClients(string $q): Collection
    {
        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->when($q !== '', fn ($query) => $this->applySearch($query, $q))
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
            'applicableForms' => $client->birFormStatuses()
                ->where('applicable', true)
                ->orderBy('form_type')
                ->pluck('form_type'),
            'billingStats' => [
                'count' => $client->billings()->whereIn('status', Billing::ACTIVE_STATUSES)->count(),
                'paid' => $client->billings()->where('status', Billing::STATUS_PAID)->sum('total'),
                'outstanding' => $client->billings()->whereIn('status', [Billing::STATUS_PENDING, Billing::STATUS_UNPAID, Billing::STATUS_OVERDUE])->sum('total'),
            ],
            'documentCount' => $client->documents()->count(),
        ]);
    }

    public function edit(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        [$formTypes, $applicableForms] = $this->birFormData($client);

        return view('admin.clients.edit', [
            'client' => $client,
            'profile' => $client->getClientProfile(),
            'statuses' => ClientProfile::STATUSES,
            'statusNotes' => ClientProfile::STATUS_NOTES,
            'businessTypes' => ClientProfile::BUSINESS_TYPES,
            'taxpayerTypes' => ClientProfile::TAXPAYER_TYPES,
            'lobOptions' => ClientProfile::LINE_OF_BUSINESS_OPTIONS,
            'regTypes' => ClientProfile::BIR_REGISTRATION_TYPES,
            'formTypes' => $formTypes,
            'applicableForms' => $applicableForms,
        ]);
    }

    public function update(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$client->id],
            'business_name' => ['nullable', 'string', 'max:255'],
            'taxpayer_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:120'],
            'taxpayer_type' => ['nullable', 'string', 'max:120'],
            'line_of_business' => ['nullable', 'string', 'max:255'],
            'line_of_business_other' => ['nullable', 'string', 'max:255'],
            'bir_registration_type' => ['nullable', 'string', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_no' => ['nullable', 'string', 'max:40'],
            'second_contact_name' => ['nullable', 'string', 'max:255'],
            'second_contact_channel' => ['nullable', 'string', Rule::in(ClientProfile::SECOND_CONTACT_CHANNELS)],
            'second_contact_no' => array_merge(
                ['nullable', 'string', 'max:255'],
                in_array(($request->input('second_contact_channel') ?? ClientProfile::SECOND_CONTACT_CHANNEL_PHONE), ClientProfile::SECOND_CONTACT_URL_CHANNELS, true)
                    ? ['url', 'regex:/^https?:\/\/.+/']
                    : (($request->input('second_contact_channel') ?? ClientProfile::SECOND_CONTACT_CHANNEL_PHONE) === ClientProfile::SECOND_CONTACT_CHANNEL_PHONE
                        ? ['regex:/^(?:\+63|0)[\d\s\-()]{7,17}$/']
                        : [])
            ),
            'second_email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'tin_no' => ['nullable', 'string', 'max:40'],
            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', array_keys(ClientProfile::STATUSES))],
            'payment_status' => ['nullable', 'in:paid,partial,unpaid'],
            'date_started' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'bir_forms' => ['nullable', 'array'],
            'bir_forms.*' => ['string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
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

        $currentAddress = $profile->getOriginal('business_address');
        $newAddress = $profileData['business_address'] ?? null;
        $submittedLat = (string) ($profileData['latitude'] ?? '');
        $submittedLng = (string) ($profileData['longitude'] ?? '');
        $coordsUnchanged = $submittedLat === (string) ($profile->getOriginal('latitude') ?? '')
            && $submittedLng === (string) ($profile->getOriginal('longitude') ?? '');
        $hasCoords = $submittedLat !== '' && $submittedLng !== '';

        if (empty($newAddress)) {
            $profileData['latitude'] = null;
            $profileData['longitude'] = null;
        } elseif (! $hasCoords) {
            $coords = $this->geocodeAddress($newAddress);
            $profileData['latitude'] = $coords['lat'] ?? null;
            $profileData['longitude'] = $coords['lng'] ?? null;
        } elseif ($coordsUnchanged && $newAddress !== $currentAddress) {
            $coords = $this->geocodeAddress($newAddress);
            $profileData['latitude'] = $coords['lat'] ?? null;
            $profileData['longitude'] = $coords['lng'] ?? null;
        }

        $profile->fill($profileData);
        $profile->save();

        $this->syncBirForms($request, $client);

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(auth()->user(), 'admin.client_updated', "Updated the client record for {$displayName}.");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client record updated.');
    }

    private function birFormData(User $client): array
    {
        $formTypes = collect(BirFormStatus::FORM_TYPES)
            ->merge($client->birFormStatuses->pluck('form_type'))
            ->unique()
            ->sort()
            ->values();
        $applicableForms = $client->birFormStatuses
            ->where('applicable', true)
            ->pluck('form_type');

        return [$formTypes, $applicableForms];
    }

    private function syncBirForms(Request $request, User $client): void
    {
        $known = collect(BirFormStatus::FORM_TYPES);

        $selected = collect($request->input('bir_forms', []))
            ->map(fn ($type) => strtoupper(trim((string) $type)))
            ->filter(fn ($type) => $known->contains($type))
            ->unique()
            ->values();

        DB::transaction(function () use ($client, $selected) {
            foreach ($client->birFormStatuses as $status) {
                $status->update(['applicable' => $selected->contains($status->form_type)]);
            }

            foreach ($selected as $formType) {
                BirFormStatus::firstOrCreate(
                    ['client_id' => $client->id, 'form_type' => $formType],
                    ['status' => BirFormStatus::STATUS_NOT_FILED, 'applicable' => true]
                );
            }
        });
    }

    private function geocodeAddress(string $address): ?array
    {
        $result = app(GeocodingService::class)->search($address);

        if (($result['status'] ?? '') !== 'ok') {
            return null;
        }

        return [
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'display_name' => $result['display_name'] ?? '',
        ];
    }

    public function storeInfoEntry(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:500'],
        ]);

        $maxOrder = $client->infoEntries()->max('sort_order') ?? 0;

        $client->infoEntries()->create([
            'key' => $validated['key'],
            'value' => $validated['value'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        ActivityLog::record(auth()->user(), 'admin.client_info_added', "Added info entry \"{$validated['key']}\" for {$client->name}.");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Info entry added.');
    }

    public function updateInfoEntry(Request $request, User $client, ClientInfoEntry $entry): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        abort_unless($entry->user_id === $client->id, 404);

        $validated = $request->validate([
            'key' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:500'],
        ]);

        $entry->fill($validated);
        $entry->save();

        ActivityLog::record(auth()->user(), 'admin.client_info_updated', "Updated info entry \"{$entry->key}\" for {$client->name}.");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Info entry updated.');
    }

    public function destroyInfoEntry(User $client, ClientInfoEntry $entry): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        abort_unless($entry->user_id === $client->id, 404);

        $entryName = $entry->key;
        $entry->delete();

        ActivityLog::record(auth()->user(), 'admin.client_info_deleted', "Deleted info entry \"{$entryName}\" for {$client->name}.");

        return redirect()->route('admin.clients.show', $client)->with('status', 'Info entry deleted.');
    }
}
