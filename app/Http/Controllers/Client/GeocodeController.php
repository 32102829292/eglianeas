<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    private const NOT_FOUND_MESSAGE = "Couldn't locate that address automatically — please drag the pin on the map or enter coordinates manually.";

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
        ]);

        $address = trim($validated['q']);

        if (! str_contains(strtolower($address), 'philippines')) {
            $address .= ', Philippines';
        }

        $cacheKey = 'geocode:'.md5($address);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'EglianeAccountingServices/1.0 (contact: support@eglianeas.com)',
                'Accept' => 'application/json',
            ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 0,
            ]);
        } catch (\Throwable) {
            return $this->errorResponse();
        }

        if ($response->failed()) {
            return $this->errorResponse();
        }

        $results = $response->json();

        if (empty($results) || empty($results[0])) {
            $payload = ['error' => self::NOT_FOUND_MESSAGE];
            Cache::put($cacheKey, $payload, now()->addMinutes(30));

            return response()->json($payload, 404);
        }

        $first = $results[0];
        $payload = [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
            'display_name' => $first['display_name'] ?? null,
        ];
        Cache::put($cacheKey, $payload, now()->addDay());

        return response()->json($payload);
    }

    private function errorResponse(): JsonResponse
    {
        return response()->json(['error' => self::NOT_FOUND_MESSAGE], 502);
    }
}
