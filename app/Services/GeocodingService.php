<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Centralized geocoding against a LocationIQ v1 endpoint.
 *
 * LocationIQ's /v1/search is an up-to-stream Nominatim-compatible API: it
 * accepts the same query parameters (q, format, limit, countrycodes) and
 * returns results shaped like Nominatim (lat/lon/display_name), so it is a
 * drop-in replacement.
 *
 * Shares the HTTP call, User-Agent header, timeout, retry and caching between
 * the client-facing and admin-facing geocoders so the whole app has one
 * provider to debug and one place to swap when the external service is
 * unreachable or blocks the hosting provider's IPs.
 *
 * Requires LOCATIONIQ_API_KEY (sent as the `key` query parameter).
 *
 * Returns a discriminated result:
 *   ['status' => 'ok', 'lat' => float, 'lng' => float, 'display_name' => string]
 *   ['status' => 'not_found']
 *   ['status' => 'error']
 */
class GeocodingService
{
    public function search(string $address): array
    {
        $query = $this->normalizeQuery($address);
        if ($query === '') {
            return ['status' => 'not_found'];
        }

        $cacheKey = 'geocode:'.md5(strtolower($query));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            // Tolerate entries written before this service added a status key.
            if (isset($cached['status'])) {
                return $cached;
            }
            if (isset($cached['lat'], $cached['lng'])) {
                return [
                    'status' => 'ok',
                    'lat' => $cached['lat'],
                    'lng' => $cached['lng'],
                    'display_name' => $cached['display_name'] ?? '',
                ];
            }
        }

        $url = (string) config('geocoding.url');
        $ua = (string) config('geocoding.user_agent');
        $apiKey = (string) (config('geocoding.api_key') ?? '');
        $timeout = (int) config('geocoding.timeout', 8);
        $retries = max(0, (int) config('geocoding.retries', 0));
        $attempts = $retries + 1;

        if ($apiKey === '') {
            Log::once('Geocoding request attempted without an API key (LOCATIONIQ_API_KEY)', [
                'service' => 'geocode',
                'url' => $url,
            ]);
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $started = microtime(true);

            try {
                $params = [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'ph',
                ];
                if ($apiKey !== '') {
                    $params['key'] = $apiKey;
                }

                $response = Http::withHeaders([
                    'User-Agent' => $ua,
                    'Accept' => 'application/json',
                ])->timeout($timeout)->get($url, $params);

                $elapsed = round(microtime(true) - $started, 3);

                if ($response->failed()) {
                    Log::warning('Geocoding request failed (HTTP status)', [
                        'service' => 'geocode',
                        'url' => $url,
                        'query' => $query,
                        'status' => $response->status(),
                        'attempt' => $attempt,
                        'elapsed_seconds' => $elapsed,
                        'user_agent' => $ua,
                        'timeout' => $timeout,
                    ]);

                    if ($attempt === $attempts) {
                        return ['status' => 'error'];
                    }

                    $this->pauseBetweenRetries();
                    continue;
                }

                $result = $this->resultFromJson($response->json());

                if ($result !== null) {
                    $payload = ['status' => 'ok'] + $result;
                    Cache::put($cacheKey, $payload, (int) config('geocoding.cache_ttl_hits', 86400));

                    return $payload;
                }

                Cache::put($cacheKey, ['status' => 'not_found'], (int) config('geocoding.cache_ttl_misses', 1800));

                return ['status' => 'not_found'];
            } catch (\Throwable $e) {
                $elapsed = round(microtime(true) - $started, 3);

                Log::error('Geocoding request threw an exception', [
                    'service' => 'geocode',
                    'url' => $url,
                    'query' => $query,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                    'elapsed_seconds' => $elapsed,
                    'user_agent' => $ua,
                    'timeout' => $timeout,
                ]);

                if ($attempt === $attempts) {
                    return ['status' => 'error'];
                }

                $this->pauseBetweenRetries();
            }
        }

        return ['status' => 'error'];
    }

    private function resultFromJson(mixed $json): ?array
    {
        if (! is_array($json) || empty($json[0])) {
            return null;
        }

        $place = $json[0];
        if (empty($place['lat']) || empty($place['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $place['lat'],
            'lng' => (float) $place['lon'],
            'display_name' => (string) ($place['display_name'] ?? ''),
        ];
    }

    private function normalizeQuery(string $address): string
    {
        $query = trim($address);
        if ($query !== '' && ! str_contains(strtolower($query), 'philippines')) {
            $query .= ', Philippines';
        }

        return $query;
    }

    private function pauseBetweenRetries(): void
    {
        usleep(200_000);
    }
}