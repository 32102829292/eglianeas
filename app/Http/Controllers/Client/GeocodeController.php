<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    private const NOT_FOUND_MESSAGE = "Couldn't locate that address automatically — please drag the pin on the map or enter coordinates manually.";
    private const SERVICE_ERROR_MESSAGE = 'Location service is temporarily unavailable — please drag the pin on the map or enter coordinates manually.';

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
        ]);

        $result = app(GeocodingService::class)->search(trim($validated['q']));

        return match ($result['status']) {
            'ok' => response()->json([
                'lat' => $result['lat'],
                'lng' => $result['lng'],
                'display_name' => $result['display_name'] ?? null,
            ]),
            'not_found' => response()->json(['error' => self::NOT_FOUND_MESSAGE], 404),
            default => response()->json(['error' => self::SERVICE_ERROR_MESSAGE], 502),
        };
    }
}
