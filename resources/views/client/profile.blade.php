@php
    $user = auth()->user();
    $maskTin = function (?string $value): string {
        if (! $value) {
            return '';
        }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };
    $maskName = function (?string $value): string {
        if (! $value) {
            return '';
        }
        return mb_substr($value, 0, 1).str_repeat('•', max(mb_strlen($value) - 1, 0));
    };
    $tinFull = old('tin_no') ?? $profile->tin_no;
    $maidenFull = old('mother_maiden_name') ?? $profile->mother_maiden_name;
    $fatherFull = old('father_name') ?? $profile->father_name;

    $modeFor = function (string $section) use ($hasData, $editSections): string {
        return (! $hasData || in_array($section, $editSections, true)) ? 'edit' : 'view';
    };
    $editUrl = fn (string $section): string => route('client.profile.edit', ['edit' => $section]);
    $editBtn = fn (string $section) => '<a href="'.$editUrl($section).'" class="btn btn-outline btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg> Edit</a>';

    $lobOptions = \App\Models\ClientProfile::LINE_OF_BUSINESS_OPTIONS;
    $lobSaved = old('line_of_business', $profile->line_of_business);
    $lobIsOther = $lobSaved !== null && $lobSaved !== '' && ! in_array($lobSaved, $lobOptions, true);
    $lobSelect = $lobIsOther ? 'Other' : (string) $lobSaved;
    $lobOtherVal = $lobIsOther ? (string) $lobSaved : (string) old('line_of_business_other');
@endphp

@extends('layouts.dashboard')

@section('title', 'My Profile — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>My profile</h1>
            <p>Keep your business information up to date.</p>
        </div>
    </div>

    @if ($hasData)
        <div class="form-hint profile-edit-hint">Use <b>Edit</b> on a section to change its details, then save.</div>
    @endif

    <div class="profile-meta">
        <div class="profile-meta-chip">
            <span class="meta-k">Client ID</span>
            <span class="meta-v code-pill">{{ $user->client_code ?? '—' }}</span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Account Status</span>
            <span class="badge badge-{{ $profile->status }}">{{ $profile->statusLabel() }}</span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Payment Status</span>
            <span class="badge badge-{{ $profile->payment_status ?? 'unpaid' }}">{{ $profile->paymentStatusLabel() ?? '—' }}</span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Date Started</span>
            <span class="meta-v">{{ $profile->date_started?->format('M j, Y') ?? '—' }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Business information</h2>
            @if ($modeFor('business') === 'view')
                {!! $editBtn('business') !!}
            @endif
        </div>

        @if ($modeFor('business') === 'edit')
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="profile-grid">
                    <div class="form-group">
                        <label class="form-label" for="client_name">Taxpayer name</label>
                        <input class="form-control" id="client_name" type="text" value="{{ $user->name }}" disabled>
                        <div class="form-hint">Contact Egliane to change your registered name.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="business_name">Business name</label>
                        <input class="form-control" id="business_name" type="text" name="business_name" value="{{ old('business_name', $user->business_name) }}">
                        @error('business_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taxpayer_name">Taxpayer's name</label>
                        <input class="form-control" id="taxpayer_name" type="text" name="taxpayer_name" value="{{ old('taxpayer_name', $profile->taxpayer_name) }}" placeholder="If different from business name">
                        @error('taxpayer_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="business_type">Business type</label>
                        <select class="form-control" id="business_type" name="business_type">
                            <option value="">Select business type</option>
                            @foreach (\App\Models\ClientProfile::BUSINESS_TYPES as $type)
                                <option value="{{ $type }}" @selected((string) old('business_type', $profile->business_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('business_type')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taxpayer_type">Type of taxpayer</label>
                        <select class="form-control" id="taxpayer_type" name="taxpayer_type">
                            <option value="">Select taxpayer type</option>
                            @foreach (\App\Models\ClientProfile::TAXPAYER_TYPES as $type)
                                <option value="{{ $type }}" @selected((string) old('taxpayer_type', $profile->taxpayer_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('taxpayer_type')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="line_of_business">Line of business</label>
                        <select class="form-control" id="line_of_business" name="line_of_business" data-lob-select>
                            <option value="">Select line of business</option>
                            @foreach ($lobOptions as $lob)
                                <option value="{{ $lob }}" @selected((string) $lobSelect === (string) $lob)>{{ $lob }}</option>
                            @endforeach
                        </select>
                        @error('line_of_business')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-span-2" data-lob-other @if (! $lobIsOther) hidden @endif>
                        <label class="form-label" for="line_of_business_other">Other line of business</label>
                        <input class="form-control" id="line_of_business_other" type="text" name="line_of_business_other" value="{{ $lobOtherVal }}" placeholder="Describe your line of business">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="bir_registration_type">BIR registration type</label>
                        <select class="form-control" id="bir_registration_type" name="bir_registration_type">
                            <option value="">Select registration type</option>
                            @foreach (\App\Models\ClientProfile::BIR_REGISTRATION_TYPES as $reg)
                                <option value="{{ $reg }}" @selected((string) old('bir_registration_type', $profile->bir_registration_type) === $reg)>{{ $reg }}</option>
                            @endforeach
                        </select>
                        @error('bir_registration_type')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="btn-group-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    @if ($hasData)
                        <a href="{{ route('client.profile.edit') }}" class="btn btn-outline">Cancel</a>
                    @endif
                </div>
            </form>
        @else
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Taxpayer name</span><span class="profile-v">{{ $user->name }}</span></div>
                <div class="profile-row"><span class="profile-k">Business name</span><span class="profile-v">{{ $user->business_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Taxpayer's name</span><span class="profile-v">{{ $profile->taxpayer_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Business type</span><span class="profile-v">{{ $profile->business_type ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Type of taxpayer</span><span class="profile-v">{{ $profile->taxpayer_type ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Line of business</span><span class="profile-v">{{ $profile->line_of_business ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">BIR registration type</span><span class="profile-v">{{ $profile->bir_registration_type ?: '—' }}</span></div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Address &amp; location</h2>
            @if ($modeFor('address') === 'view')
                {!! $editBtn('address') !!}
            @endif
        </div>

        @if ($modeFor('address') === 'edit')
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="profile-grid">
                    <div class="form-group col-span-2">
                        <label class="form-label" for="business_address">Business address</label>
                        <input class="form-control" id="business_address" type="text" name="business_address" value="{{ old('business_address', $profile->business_address) }}" placeholder="e.g. 123 Rizal Avenue, Quezon City">
                        @error('business_address')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $profile->latitude) }}">
                    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $profile->longitude) }}">
                    <x-address-map :latitude="null" :longitude="null" id="profileMapLoader" hidden />
                    <div class="form-group col-span-2">
                        <button type="button" class="btn btn-outline btn-sm" id="locateAddressBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            Locate address
                        </button>
                        <div class="form-hint">Coordinates are set automatically from the business address above. Drag the map pin to adjust.</div>
                        <div class="form-hint geo-status" id="geoStatus" hidden></div>
                        <div class="map-preview" id="mapPreview" hidden></div>
                    </div>
                </div>
                <div class="btn-group-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    @if ($hasData)
                        <a href="{{ route('client.profile.edit') }}" class="btn btn-outline">Cancel</a>
                    @endif
                </div>
            </form>
        @else
            <div class="profile-grid">
                <div class="profile-row col-span-2"><span class="profile-k">Business address</span><span class="profile-v">{{ $profile->business_address ?: '—' }}</span></div>
                @if ($profile->latitude !== null && $profile->longitude !== null && (float) $profile->latitude != 0 && (float) $profile->longitude != 0)
                    <div class="profile-row col-span-2"><span class="profile-k">Map</span><x-address-map :latitude="$profile->latitude" :longitude="$profile->longitude" id="mapView" /></div>
                @endif
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Contact information</h2>
            @if ($modeFor('contact') === 'view')
                {!! $editBtn('contact') !!}
            @endif
        </div>

        @if ($modeFor('contact') === 'edit')
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="profile-grid">
                    <div class="form-group">
                        <label class="form-label" for="contact_no">Contact number</label>
                        <input class="form-control" id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', $profile->contact_no) }}" placeholder="+63 917 000 0000">
                        @error('contact_no')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="second_contact_name">2nd contact name</label>
                        <input class="form-control" id="second_contact_name" type="text" name="second_contact_name" value="{{ old('second_contact_name', $profile->second_contact_name) }}" placeholder="Name of 2nd contact person">
                        @error('second_contact_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="second_contact_channel">2nd person contact channel</label>
                        <div class="form-inline-row">
                            <select class="form-control" id="second_contact_channel" name="second_contact_channel" data-second-channel>
                                @foreach (\App\Models\ClientProfile::SECOND_CONTACT_CHANNELS as $ch)
                                    <option value="{{ $ch }}" {{ old('second_contact_channel', $profile->second_contact_channel ?? 'phone') === $ch ? 'selected' : '' }}>{{ ['phone' => 'Phone Number', 'viber' => 'Viber', 'facebook' => 'Facebook', 'telegram' => 'Telegram'][$ch] }}</option>
                                @endforeach
                            </select>
                            <input class="form-control" id="second_contact_no" type="text" name="second_contact_no" value="{{ old('second_contact_no', $profile->second_contact_no) }}" placeholder="0918 765 4321"
                                @if (old('second_contact_channel', $profile->second_contact_channel ?? 'phone') === 'phone')
                                    pattern="(?:\+63|0)[\d\s\-()]{7,17}" title="PH mobile or landline, e.g. 0918 765 4321"
                                @endif>
                        </div>
                        @error('second_contact_channel')<div class="form-error">{{ $message }}</div>@enderror
                        @error('second_contact_no')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="second_email">2nd person email</label>
                        <input class="form-control" id="second_email" type="email" name="second_email" value="{{ old('second_email', $profile->second_email) }}" placeholder="name@example.com">
                        @error('second_email')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="birth_date">Birth date</label>
                        <input class="form-control" id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $profile->birth_date?->format('Y-m-d')) }}">
                        @error('birth_date')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="btn-group-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    @if ($hasData)
                        <a href="{{ route('client.profile.edit') }}" class="btn btn-outline">Cancel</a>
                    @endif
                </div>
            </form>
        @else
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Contact number</span><span class="profile-v">{{ $profile->contact_no ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd contact name</span><span class="profile-v">{{ $profile->second_contact_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd person contact</span><span class="profile-v">{{ $profile->second_contact_display ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd person email</span><span class="profile-v">{{ $profile->second_email ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Birth date</span><span class="profile-v">{{ $profile->birth_date?->format('M j, Y') ?? '—' }}</span></div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">BIR details</h2>
            @if ($modeFor('bir') === 'view')
                {!! $editBtn('bir') !!}
            @endif
        </div>

        @if ($modeFor('bir') === 'edit')
            <div class="form-hint mb-2">Sensitive fields are masked. Click &ldquo;Reveal&rdquo; to view or edit.</div>
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="profile-grid">
                    <div class="form-group">
                        <label class="form-label" for="tin_no">TIN number</label>
                        <div class="reveal-field">
                            <input class="form-control" id="tin_no" type="text" name="tin_no" value="{{ old('tin_no', $maskTin($tinFull)) }}" data-full="{{ $tinFull }}" data-masked="{{ $maskTin($tinFull) }}" placeholder="123-456-789-000">
                            <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="tin_no">Reveal</button>
                        </div>
                        @error('tin_no')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="mother_maiden_name">Mother&rsquo;s maiden name</label>
                        <div class="reveal-field">
                            <input class="form-control" id="mother_maiden_name" type="text" name="mother_maiden_name" value="{{ old('mother_maiden_name', $maskName($maidenFull)) }}" data-full="{{ $maidenFull }}" data-masked="{{ $maskName($maidenFull) }}">
                            <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="mother_maiden_name">Reveal</button>
                        </div>
                        @error('mother_maiden_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="father_name">Father&rsquo;s name</label>
                        <div class="reveal-field">
                            <input class="form-control" id="father_name" type="text" name="father_name" value="{{ old('father_name', $maskName($fatherFull)) }}" data-full="{{ $fatherFull }}" data-masked="{{ $maskName($fatherFull) }}">
                            <button type="button" class="btn btn-outline btn-sm reveal-btn" data-target="father_name">Reveal</button>
                        </div>
                        @error('father_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="btn-group-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    @if ($hasData)
                        <a href="{{ route('client.profile.edit') }}" class="btn btn-outline">Cancel</a>
                    @endif
                </div>
            </form>
        @else
            <div class="form-hint mb-2">Sensitive fields are masked.</div>
            <div class="profile-grid">
                <div class="profile-row">
                    <span class="profile-k">TIN number</span>
                    <span class="profile-v">
                        @if ($tinFull)
                            <span class="reveal-text" data-full="{{ $tinFull }}" data-masked="{{ $maskTin($tinFull) }}">{{ $maskTin($tinFull) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Mother&rsquo;s maiden name</span>
                    <span class="profile-v">
                        @if ($maidenFull)
                            <span class="reveal-text" data-full="{{ $maidenFull }}" data-masked="{{ $maskName($maidenFull) }}">{{ $maskName($maidenFull) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Father&rsquo;s name</span>
                    <span class="profile-v">
                        @if ($fatherFull)
                            <span class="reveal-text" data-full="{{ $fatherFull }}" data-masked="{{ $maskName($fatherFull) }}">{{ $maskName($fatherFull) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        var profileLat = @json($profile->latitude ? (float) $profile->latitude : null);
        var profileLng = @json($profile->longitude ? (float) $profile->longitude : null);
    </script>
    <script src="/js/client.js" defer></script>
@endpush
