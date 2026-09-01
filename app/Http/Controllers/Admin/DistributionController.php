<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BirFormStatus;
use App\Models\CorViewLog;
use App\Models\Document;
use App\Models\DocumentDelivery;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DistributionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q'));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->with('profile')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('client_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (User $client): array {
                $statuses = $client->birFormStatuses()->pluck('status', 'form_type');
                $applicableForms = $client->birFormStatuses()->where('applicable', true)->pluck('status', 'form_type');
                $filed = $applicableForms->filter(fn (string $s) => $s === BirFormStatus::STATUS_FILED)->count();
                $softcopyCount = $client->documents()->whereNotNull('form_type')->count();

                return [
                    'user' => $client,
                    'filed' => $filed,
                    'total' => $applicableForms->count(),
                    'softcopies' => $softcopyCount,
                ];
            });

        return view('admin.distribution.index', [
            'clients' => $clients,
            'q' => $q,
        ]);
    }

    public function show(User $client): View
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $profile = $client->profile;

        $birStatuses = $client->birFormStatuses()
            ->where('applicable', true)
            ->get()
            ->pluck('status', 'form_type')
            ->toArray();

        $applicableFormTypes = $client->birFormStatuses()
            ->where('applicable', true)
            ->pluck('form_type')
            ->toArray();

        $deliveries = $client->documentDeliveries()
            ->whereIn('form_type', $applicableFormTypes)
            ->orderByDesc('date_received')
            ->orderByDesc('created_at')
            ->get();

        $softcopies = $client->documents()
            ->whereNotNull('form_type')
            ->whereIn('form_type', $applicableFormTypes)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('form_type');

        return view('admin.distribution.show', [
            'client' => $client,
            'profile' => $profile,
            'birStatuses' => $birStatuses,
            'deliveries' => $deliveries,
            'softcopies' => $softcopies,
            'formTypes' => $applicableFormTypes,
            'statuses' => BirFormStatus::STATUSES,
            'methods' => DocumentDelivery::METHODS,
        ]);
    }

    public function updateBirStatus(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(BirFormStatus::STATUSES))],
        ]);

        BirFormStatus::updateOrCreate(
            ['client_id' => $client->id, 'form_type' => $validated['form_type']],
            ['status' => $validated['status'], 'updated_by' => auth()->id()]
        );

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(
            auth()->user(),
            'distribution.bir_status_updated',
            "Marked {$validated['form_type']} as ".BirFormStatus::STATUSES[$validated['status']]." for {$displayName}."
        );

        return back()->with('status', "{$validated['form_type']} status updated to ".BirFormStatus::STATUSES[$validated['status']].'.');
    }

    public function storeDelivery(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
            'delivery_method' => ['required', 'string', 'in:'.implode(',', array_keys(DocumentDelivery::METHODS))],
            'date_received' => ['nullable', 'date'],
            'time_received' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'no_file_flag' => ['nullable', 'boolean'],
        ]);

        $validated['client_id'] = $client->id;
        $validated['no_file_flag'] = $validated['no_file_flag'] ?? false;

        DocumentDelivery::create($validated);

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(
            auth()->user(),
            'distribution.delivery_logged',
            "Logged {$validated['delivery_method']} delivery of {$validated['form_type']} to {$displayName}."
        );

        return back()->with('status', 'Delivery logged.');
    }

    public function destroyDelivery(User $client, DocumentDelivery $delivery): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        abort_unless($delivery->client_id === $client->id, 404);

        $formType = $delivery->form_type;
        $delivery->delete();

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(
            auth()->user(),
            'distribution.delivery_deleted',
            "Removed {$formType} delivery entry for {$displayName}."
        );

        return back()->with('status', 'Delivery entry removed.');
    }

    public function storeSoftcopy(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'form_type' => ['required', 'string', 'in:'.implode(',', BirFormStatus::FORM_TYPES)],
            'file' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$client->id, 'supabase');

        Document::query()->create([
            'user_id' => auth()->id(),
            'client_id' => $client->id,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'form_type' => $validated['form_type'],
        ]);

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(
            auth()->user(),
            'distribution.softcopy_uploaded',
            "Uploaded {$validated['form_type']} softcopy for {$displayName}."
        );

        return back()->with('status', 'Softcopy uploaded.');
    }

    public function view(Document $document)
    {
        abort_unless($document->client_id, 404);
        abort_unless(Storage::disk('supabase')->exists($document->path), 404);

        $user = auth()->user();
        CorViewLog::create([
            'document_id' => $document->id,
            'viewed_by' => $user->id,
            'viewed_at' => now(),
        ]);

        return view('document-viewer', [
            'document' => $document,
            'viewerName' => $user->name,
            'viewedAt' => now(),
        ]);
    }

    public function download(Document $document)
    {
        abort_unless($document->client_id, 404);
        abort_unless(Storage::disk('supabase')->exists($document->path), 404);

        CorViewLog::create([
            'document_id' => $document->id,
            'viewed_by' => auth()->id(),
            'viewed_at' => now(),
        ]);

        return Storage::disk('supabase')->download($document->path, $document->original_name);
    }

    public function destroySoftcopy(User $client, Document $document): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        abort_unless($document->client_id === $client->id, 404);

        $formType = $document->form_type;
        Storage::disk('supabase')->delete($document->path);
        $document->delete();

        $displayName = $client->business_name ?: $client->name;
        ActivityLog::record(
            auth()->user(),
            'distribution.softcopy_deleted',
            "Removed {$formType} softcopy for {$displayName}."
        );

        return back()->with('status', 'Softcopy removed.');
    }

    public function updateLocation(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'business_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $profile = $client->getClientProfile();

        $address = $validated['business_address'] ?? $profile->business_address;
        $hasCoords = ! empty($validated['latitude']) && ! empty($validated['longitude']);

        if ($address && ! $hasCoords) {
            $coords = $this->geocodeAddress($address);
            if ($coords) {
                $validated['latitude'] = $coords['lat'];
                $validated['longitude'] = $coords['lng'];
            }
        }

        $profile->update($validated);

        $displayName = $client->business_name ?: $client->name;
        $hasCoordsAfter = ! empty($validated['latitude']) && ! empty($validated['longitude']);
        ActivityLog::record(
            auth()->user(),
            'distribution.location_updated',
            "Updated location of {$displayName}".($hasCoordsAfter ? ' (address and coordinates).' : '.')
        );

        return back()->with('status', 'Client location updated.');
    }

    public function geocode(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
        ]);

        $result = app(GeocodingService::class)->search($validated['q']);

        return match ($result['status']) {
            'ok' => response()->json([
                'lat' => $result['lat'],
                'lng' => $result['lng'],
                'display_name' => $result['display_name'],
            ]),
            'not_found' => response()->json(['error' => 'Address not found.'], 422),
            default => response()->json(['error' => 'Geocoding service unavailable.'], 502),
        };
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
}
