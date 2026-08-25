@php
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };
    $maskName = function (?string $value): string {
        if (! $value) { return ''; }
        return mb_substr($value, 0, 1).str_repeat('•', max(mb_strlen($value) - 1, 0));
    };
@endphp

@extends('layouts.dashboard')

@section('title', ($client->business_name ?: $client->name).' — Clients — Egliane Accounting Services')

@section('content')
    <a href="{{ route('admin.clients.index') }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Clients
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1>{{ $client->business_name ?: $client->name }}</h1>
            <p>{{ $client->name }} &middot; {{ $client->email }}</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('admin.billing.show', $client) }}" class="btn btn-outline">Billing Statements</a>
            <a href="{{ route('admin.distribution.show', $client) }}" class="btn btn-outline">Document Distribution</a>
            <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-primary">Edit profile</a>
        </div>
    </div>

    <div class="profile-meta">
        <div class="profile-meta-chip">
            <span class="meta-k">Taxpayer ID</span>
            <span class="meta-v code-pill">{{ $client->client_code ?? '—' }}</span>
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
        <div class="profile-meta-chip">
            <span class="meta-k">Outstanding</span>
            <span class="meta-v {{ $billingStats['outstanding'] > 0 ? 'text-danger' : '' }}">₱{{ number_format($billingStats['outstanding'], 2) }}</span>
        </div>
        <div class="profile-meta-chip">
            <span class="meta-k">Documents</span>
            <span class="meta-v">{{ $documentCount }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Status</h2>
            <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="inline-form" id="statusForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ $client->name }}">
                <input type="hidden" name="email" value="{{ $client->email }}">
                <input type="hidden" name="status" value="{{ $profile->status }}">
                <select class="form-control form-control-sm" name="payment_status" onchange="this.form.submit()">
                    <option value="">Payment: —</option>
                    <option value="paid" @selected($profile->payment_status === 'paid')>Paid</option>
                    <option value="partial" @selected($profile->payment_status === 'partial')>Partial</option>
                    <option value="unpaid" @selected($profile->payment_status === 'unpaid')>Unpaid</option>
                </select>
            </form>
        </div>
        <p class="card-sub">{{ $statusNotes[$profile->status] ?? '' }}</p>
        <div class="status-options">
            @foreach ($statuses as $value => $label)
                <form method="POST" action="{{ route('admin.clients.update', $client) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $client->name }}">
                    <input type="hidden" name="email" value="{{ $client->email }}">
                    <input type="hidden" name="payment_status" value="{{ $profile->payment_status }}">
                    <input type="hidden" name="status" value="{{ $value }}">
                    <button type="submit" class="btn btn-sm {{ $profile->status === $value ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Business information</h3>
                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
            </div>
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Business name</span><span class="profile-v">{{ $client->business_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Taxpayer's name</span><span class="profile-v">{{ $profile->taxpayer_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Business type</span><span class="profile-v">{{ $profile->business_type ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Type of taxpayer</span><span class="profile-v">{{ $profile->taxpayer_type ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Line of business</span><span class="profile-v">{{ $profile->line_of_business ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">BIR registration</span><span class="profile-v">{{ $profile->bir_registration_type ?: '—' }}</span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Address &amp; location</h3>
                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
            </div>
            <div class="profile-grid">
                <div class="profile-row col-span-2"><span class="profile-k">Business address</span><span class="profile-v">{{ $profile->business_address ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Latitude</span><span class="profile-v">{{ $profile->latitude ?? '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Longitude</span><span class="profile-v">{{ $profile->longitude ?? '—' }}</span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Contact information</h3>
                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
            </div>
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Contact number</span><span class="profile-v">{{ $profile->contact_no ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd contact name</span><span class="profile-v">{{ $profile->second_contact_name ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd contact</span><span class="profile-v">{{ $profile->second_contact_display ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">2nd email</span><span class="profile-v">{{ $profile->second_email ?: '—' }}</span></div>
                <div class="profile-row"><span class="profile-k">Birth date</span><span class="profile-v">{{ $profile->birth_date?->format('M j, Y') ?? '—' }}</span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">BIR details</h3>
                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
            </div>
            <div class="form-hint mb-2">Sensitive fields are masked.</div>
            <div class="profile-grid">
                <div class="profile-row">
                    <span class="profile-k">TIN number</span>
                    <span class="profile-v">
                        @if ($profile->tin_no)
                            <span class="reveal-text" data-full="{{ $profile->tin_no }}" data-masked="{{ $maskTin($profile->tin_no) }}">{{ $maskTin($profile->tin_no) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Mother&rsquo;s maiden name</span>
                    <span class="profile-v">
                        @if ($profile->mother_maiden_name)
                            <span class="reveal-text" data-full="{{ $profile->mother_maiden_name }}" data-masked="{{ $maskName($profile->mother_maiden_name) }}">{{ $maskName($profile->mother_maiden_name) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-k">Father&rsquo;s name</span>
                    <span class="profile-v">
                        @if ($profile->father_name)
                            <span class="reveal-text" data-full="{{ $profile->father_name }}" data-masked="{{ $maskName($profile->father_name) }}">{{ $maskName($profile->father_name) }}</span>
                            <button type="button" class="btn btn-outline btn-sm reveal-text-btn">Reveal</button>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">BIR forms</h3>
                <a href="{{ route('admin.clients.edit', $client) }}#bir-forms-card" class="link">Edit</a>
            </div>
            @if ($applicableForms->isNotEmpty())
                <div class="bir-form-badges">
                    @foreach ($applicableForms as $type)
                        <span class="badge badge-neutral bir-form-badge">{{ $type }}</span>
                    @endforeach
                </div>
            @else
                <div class="form-hint">No BIR forms configured for this client yet.</div>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Internal remarks</h3>
                <a href="{{ route('admin.clients.edit', $client) }}" class="link">Edit</a>
            </div>
            @if ($profile->remarks)
                <p style="white-space: pre-wrap;">{{ $profile->remarks }}</p>
            @else
                <div class="form-hint">No remarks yet.</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.reveal-text-btn') : null;
            if (!btn) return;
            var text = btn.parentElement ? btn.parentElement.querySelector('.reveal-text') : null;
            if (!text) return;
            var showing = btn.getAttribute('data-showing') === '1';
            if (showing) {
                text.textContent = text.getAttribute('data-masked') || text.textContent;
                btn.textContent = 'Reveal';
                btn.setAttribute('data-showing', '0');
            } else {
                text.textContent = text.getAttribute('data-full') || text.textContent;
                btn.textContent = 'Hide';
                btn.setAttribute('data-showing', '1');
            }
        });
    </script>
@endpush
