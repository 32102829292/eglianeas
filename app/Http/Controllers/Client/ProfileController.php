<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClientProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $profile = auth()->user()->getClientProfile();

        $hasData = (bool) (auth()->user()->business_name
            || $profile->business_type
            || $profile->line_of_business
            || $profile->bir_registration_type
            || $profile->business_address
            || $profile->contact_no
            || $profile->second_contact_no
            || $profile->second_email
            || $profile->tin_no
            || $profile->mother_maiden_name
            || $profile->father_name);

        $editParam = $request->query('edit');
        $editSections = $editParam
            ? array_values(array_filter(array_map('trim', explode(',', (string) $editParam))))
            : [];

        return view('client.profile', compact('profile', 'hasData', 'editSections'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'taxpayer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'taxpayer_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'line_of_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'line_of_business_other' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bir_registration_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'business_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_no' => ['sometimes', 'nullable', 'string', 'max:40'],
            'second_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'second_contact_channel' => ['sometimes', 'nullable', 'string', Rule::in(ClientProfile::SECOND_CONTACT_CHANNELS)],
            'second_contact_no' => array_merge(
                ['sometimes', 'nullable', 'string', 'max:255'],
                in_array(($request->input('second_contact_channel') ?? ClientProfile::SECOND_CONTACT_CHANNEL_PHONE), ClientProfile::SECOND_CONTACT_URL_CHANNELS, true)
                    ? ['url', 'regex:/^https?:\/\/.+/']
                    : (($request->input('second_contact_channel') ?? ClientProfile::SECOND_CONTACT_CHANNEL_PHONE) === ClientProfile::SECOND_CONTACT_CHANNEL_PHONE
                        ? ['regex:/^(?:\+63|0)[\d\s\-()]{7,17}$/']
                        : [])
            ),
            'second_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'tin_no' => ['sometimes', 'nullable', 'string', 'max:40'],
            'mother_maiden_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();

        if (array_key_exists('business_name', $validated)) {
            $user->business_name = $validated['business_name'] ?: null;
            $user->save();
        }

        $profileData = $validated;
        unset($profileData['business_name']);

        if (($profileData['line_of_business'] ?? null) === 'Other') {
            $profileData['line_of_business'] = ($profileData['line_of_business_other'] ?? null) ?: null;
        }
        unset($profileData['line_of_business_other']);

        foreach ($profileData as $key => $value) {
            if ($value === '') {
                $profileData[$key] = null;
            }
        }

        $profile = $user->getClientProfile();

        $currentAddress = $profile->getOriginal('business_address');
        $newAddress = $profileData['business_address'] ?? null;
        $hasCoords = ! empty($profileData['latitude']) && ! empty($profileData['longitude']);

        if ($newAddress && ($newAddress !== $currentAddress || ! $hasCoords)) {
            $coords = $this->geocodeAddress($newAddress);
            if ($coords) {
                $profileData['latitude'] = $coords['lat'];
                $profileData['longitude'] = $coords['lng'];
            }
        }

        $profile->fill($profileData);
        $profile->save();

        ActivityLog::record($user, 'client.profile_updated', 'Updated their client profile.');

        return redirect()->route('client.profile.edit')->with('status', 'Profile updated.');
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
