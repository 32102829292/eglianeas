<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BirFormStatus;
use App\Models\ClientProfile;
use App\Models\Document;
use App\Models\DocumentDelivery;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
                $filed = $statuses->filter(fn (string $s) => $s === BirFormStatus::STATUS_FILED)->count();
                $softcopyCount = $client->documents()->whereNotNull('form_type')->count();

                return [
                    'user' => $client,
                    'filed' => $filed,
                    'total' => count(BirFormStatus::FORM_TYPES),
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
            ->get()
            ->pluck('status', 'form_type')
            ->toArray();

        $deliveries = $client->documentDeliveries()
            ->orderByDesc('date_received')
            ->orderByDesc('created_at')
            ->get();

        $softcopies = $client->documents()
            ->whereNotNull('form_type')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('form_type');

        return view('admin.distribution.show', [
            'client' => $client,
            'profile' => $profile,
            'birStatuses' => $birStatuses,
            'deliveries' => $deliveries,
            'softcopies' => $softcopies,
            'formTypes' => BirFormStatus::FORM_TYPES,
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
        \App\Models\ActivityLog::record(
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

        $delivery->delete();

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
        $path = $file->store('documents/'.$client->id);

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
        \App\Models\ActivityLog::record(
            auth()->user(),
            'distribution.softcopy_uploaded',
            "Uploaded {$validated['form_type']} softcopy for {$displayName}."
        );

        return back()->with('status', 'Softcopy uploaded.');
    }

    public function view(Document $document)
    {
        abort_unless($document->client_id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $user = auth()->user();
        \App\Models\CorViewLog::create([
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
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroySoftcopy(User $client, Document $document): RedirectResponse
    {
        abort_unless($client->role === User::ROLE_CLIENT, 404);
        abort_unless($document->client_id === $client->id, 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();

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

        return back()->with('status', 'Client location updated.');
    }

    public function geocode(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
        ]);

        $query = $validated['q'];
        if (! str_contains(strtolower($query), 'philippines')) {
            $query .= ', Philippines';
        }

        $cacheKey = 'geocode:'.md5(strtolower(trim($query)));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $response = Http::timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'ph',
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Geocoding service unavailable.'], 502);
            }

            $results = $response->json();
            if (empty($results[0])) {
                $notFound = ['error' => 'Address not found.'];
                Cache::put($cacheKey, $notFound, 1800);

                return response()->json($notFound, 422);
            }

            $place = $results[0];
            $data = [
                'lat' => (float) $place['lat'],
                'lng' => (float) $place['lon'],
                'display_name' => $place['display_name'] ?? '',
            ];

            Cache::put($cacheKey, $data, 86400);

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Geocoding request failed.'], 500);
        }
    }

    private function geocodeAddress(string $address): ?array
    {
        $query = str_contains(strtolower($address), 'philippines')
            ? $address
            : $address.', Philippines';

        $cacheKey = 'geocode:'.md5(strtolower(trim($query)));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'EglianeAccountingServices/1.0 (contact: support@eglianeas.com)',
                'Accept' => 'application/json',
            ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'ph',
            ]);

            if ($response->failed()) {
                return null;
            }

            $results = $response->json();
            if (empty($results[0])) {
                return null;
            }

            $place = $results[0];
            $data = [
                'lat' => (float) $place['lat'],
                'lng' => (float) $place['lon'],
                'display_name' => $place['display_name'] ?? '',
            ];

            Cache::put($cacheKey, $data, now()->addDay());

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }
}
