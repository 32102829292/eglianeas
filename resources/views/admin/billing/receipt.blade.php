@extends('layouts.dashboard')

@section('title', $billing->periodTitle().' — Receipt — Egliane Accounting Services')

@section('content')
    @php($client = $billing->client)
    @php($gcashNumber = \App\Models\Setting::get('gcash_number', ''))
    @php($shareUrl = rtrim((string) config('app.url'), '/') . '/' . ltrim(route('client.billing.show', $billing, false), '/'))

    <a href="{{ route('admin.billing.show', $client) }}" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to {{ $client?->business_name ?: $client?->name }}
    </a>

    <div class="page-head page-head-row no-print">
        <div>
            <h1>Billing statement</h1>
            <p>Period: {{ $billing->period_label }}</p>
        </div>
        <div class="btn-row">
            <form method="POST" action="{{ route('admin.billing.sendEmail', $billing) }}" class="inline-form" id="emailBillingForm">
                @csrf
                <button type="submit" class="btn btn-outline" title="Send to {{ $client?->email }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Send via Email
                </button>
            </form>
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-outline" id="shareBillingBtn"
                    data-client="{{ $client?->business_name ?: $client?->name }}"
                    data-period="{{ $billing->period_label }}"
                    data-url="{{ $shareUrl }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    Share to&hellip;
                </button>
                <div class="dropdown-menu" id="share-menu">
                    <button type="button" class="dropdown-item" id="shareMessengerBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Messenger
                    </button>
                    <button type="button" class="dropdown-item" id="shareViberBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Viber
                    </button>
                    <button type="button" class="dropdown-item" id="shareTelegramBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        Telegram
                    </button>
                    <button type="button" class="dropdown-item" id="shareEmailBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email
                    </button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item" id="shareCopyBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copy Link
                    </button>
                </div>
            </div>
            <a href="{{ route('admin.billing.csv', $billing) }}" class="btn btn-outline">Download CSV</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Download as PDF</button>
        </div>
    </div>

    <div class="statement-wrap">
        @include('partials.billing-statement')
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('shareBillingBtn');
            var menu = document.getElementById('share-menu');
            if (!btn || !menu) return;

            function buildMessage() {
                var lines = [
                    'Hi ' + btn.getAttribute('data-client') + ',',
                    '',
                    'Your billing statement for ' + btn.getAttribute('data-period') + ' is ready to view.',
                    '',
                    'Open your statement here:',
                    btn.getAttribute('data-url'),
                    '',
                    '\u2014 Egliane Accounting Services'
                ];
                return lines.join('\n');
            }

            function shortMessage() {
                return 'Your billing statement for ' + btn.getAttribute('data-period') + ' is ready to view.';
            }

            function statementUrl() {
                return btn.getAttribute('data-url');
            }

            function copyToClipboard(text, okMessage) {
                function fallback() {
                    try {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        ta.setSelectionRange(0, text.length);
                        var ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        if (ok) { notify(okMessage); return; }
                    } catch (e) {}
                    window.prompt('Copy this link and paste it anywhere to share:', text);
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { notify(okMessage); }, fallback);
                    return;
                }
                fallback();
            }

            function notify(message) {
                if (typeof window.E !== 'undefined' && E.toast) {
                    E.toast(message);
                } else {
                    window.alert(message);
                }
            }

            function placeMenu() {
                var wrap = menu.offsetParent || menu.parentElement;
                var wr = wrap.getBoundingClientRect();
                var margin = 12;

                menu.style.left = '';
                menu.style.right = '';
                menu.style.top = '';
                menu.style.bottom = '';

                var m = menu.getBoundingClientRect();
                var left = m.left;
                var rightEdge = m.right;
                if (rightEdge > window.innerWidth - margin) {
                    left = Math.max(margin, window.innerWidth - m.width - margin);
                } else if (left < margin) {
                    left = margin;
                }

                var top = m.top;
                var bottomEdge = m.bottom;
                if (bottomEdge > window.innerHeight - margin) {
                    var above = top - m.height - 4;
                    if (above < margin) {
                        top = Math.max(margin, window.innerHeight - m.height - margin);
                    } else {
                        top = above;
                    }
                }

                menu.style.left = (left - wr.left) + 'px';
                menu.style.top = (top - wr.top) + 'px';
            }

            function openMenu() {
                menu.style.display = 'block';
                placeMenu();
            }

            function closeMenu() {
                menu.style.display = 'none';
                menu.style.left = '';
                menu.style.right = '';
                menu.style.top = '';
                menu.style.bottom = '';
            }

            // Desktop always opens the explicit menu so Messenger, Viber,
            // Telegram, Email and Copy Link stay available even when the
            // native target list has no Messenger. Mobile keeps the native
            // Share Sheet where the browser exposes one (unchanged behavior).
            var mobileFormFactor = (typeof navigator.userAgentData !== 'undefined' && navigator.userAgentData && navigator.userAgentData.mobile) || window.matchMedia('(max-width: 767px)').matches;

            if (navigator.share && mobileFormFactor) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    navigator.share({ title: 'Egliane Billing Statement', text: shortMessage(), url: statementUrl() }).catch(function (err) {
                        if (err && err.name === 'AbortError') return;
                        openMenu();
                    });
                });
            } else {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (menu.style.display === 'block') { closeMenu(); } else { openMenu(); }
                });
            }

            document.addEventListener('click', function (e) {
                if (!btn.contains(e.target) && !menu.contains(e.target)) {
                    closeMenu();
                }
            });

            document.getElementById('shareCopyBtn').addEventListener('click', function () {
                closeMenu();
                copyToClipboard(statementUrl(), 'Billing statement link copied to clipboard.');
            });

            document.getElementById('shareMessengerBtn').addEventListener('click', function () {
                closeMenu();
                window.open('https://www.messenger.com/share/?link=' + encodeURIComponent(statementUrl()), '_blank', 'noopener');
            });

            function openViberShare() {
                var appUrl = 'viber://forward?text=' + encodeURIComponent(buildMessage());
                var attempted = false;

                var timeout = setTimeout(function () {
                    if (attempted) return;
                    attempted = true;
                    if (window.navigator.share) {
                        window.navigator.share({
                            title: 'Egliane Billing Statement',
                            text: buildMessage(),
                            url: statementUrl()
                        }).catch(function () {
                            copyToClipboard(statementUrl(), 'Viber was not detected. The link was copied instead.');
                        });
                    } else {
                        copyToClipboard(statementUrl(), 'Viber was not detected. The link was copied instead.');
                    }
                }, 1200);

                var hidden = document.createElement('a');
                hidden.href = appUrl;
                hidden.style.display = 'none';
                hidden.rel = 'noopener';
                document.body.appendChild(hidden);
                hidden.click();
                document.body.removeChild(hidden);

                window.addEventListener('blur', function () {
                    clearTimeout(timeout);
                    attempted = true;
                }, { once: true });
            }

            document.getElementById('shareViberBtn').addEventListener('click', function () {
                closeMenu();
                openViberShare();
            });

            document.getElementById('shareTelegramBtn').addEventListener('click', function () {
                closeMenu();
                window.open('https://t.me/share/url?url=' + encodeURIComponent(statementUrl()) + '&text=' + encodeURIComponent(shortMessage()), '_blank', 'noopener');
            });

            document.getElementById('shareEmailBtn').addEventListener('click', function () {
                closeMenu();
                window.location.href = 'mailto:?subject=' + encodeURIComponent('Egliane Billing Statement') + '&body=' + encodeURIComponent(buildMessage());
            });

            var emailForm = document.getElementById('emailBillingForm');
            if (emailForm) {
                emailForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var recipient = @json($client?->email);
                    egliane.confirm.action({ title: 'Send this billing statement?', message: 'Send this billing statement to ' + (recipient || 'the client') + ' by email?', confirmLabel: 'Send' }, function () {
                        emailForm.submit();
                    });
                });
            }
        })();
    </script>
@endpush
