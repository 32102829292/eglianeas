@extends('layouts.dashboard')

@section('title', 'Egliane Assistant — Egliane Accounting Services')

@section('content')
    @php
        $chatbotConfig = null;
        if (is_array($chatbot_rules)) {
            $chatbotConfig = $chatbot_rules;
        } elseif (is_string($chatbot_rules)) {
            $decoded = json_decode($chatbot_rules, true);
            $chatbotConfig = is_array($decoded) ? $decoded : null;
        }
        $chatName = $chatbotConfig['name'] ?? config('chatbot.name', 'Egliane Assistant');
        $chatWelcome = $chatbotConfig['welcome_message'] ?? config('chatbot.welcome_message');
        $chatFallback = $chatbotConfig['fallback_message'] ?? config('chatbot.fallback_message');
        $chatRules = $chatbotConfig['rules'] ?? config('chatbot.rules');
    @endphp

    <div class="admin-chat-page">

        {{-- ===================== CONVERSATION VIEW ===================== --}}
        <section class="admin-chat" id="adminChatView" aria-label="Egliane Assistant conversation">
            <header class="admin-chat-head">
                <div class="admin-chat-avatar" aria-hidden="true">
                    <span class="admin-chat-avatar-ring"></span>
                    <span class="admin-chat-avatar-core">E</span>
                </div>
                <div class="admin-chat-identity">
                    <div class="title">{{ $chatName }}</div>
                    <div class="sub">Ask questions about Egliane Accounting Services, services, documents, filing status, and more.</div>
                </div>
                <div class="admin-chat-head-actions">
                    <span class="admin-chat-status"><span class="status-dot"></span>Online</span>
                    <button type="button" class="admin-chat-settings-btn" id="chatbotSettingsToggle" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>Chatbot Settings</span>
                    </button>
                </div>
            </header>

            <div class="admin-chat-messages" id="adminChatMessages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Chat history"></div>

            <div class="admin-chat-quick" id="adminChatQuick" aria-label="Suggested questions">
                <button type="button" data-q="What services do you offer?">What services do you offer?<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>
                <button type="button" data-q="How can I check my filing status?">How can I check my filing status?<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>
                <button type="button" data-q="What documents do I need?">What documents do I need?<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>
                <button type="button" data-q="How do I contact Egliane?">How do I contact Egliane?<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>
                <button type="button" data-q="How much does your service cost?">How much does your service cost?<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>
            </div>

            <footer class="admin-chat-composer">
                <div class="typing-indicator" id="adminChatTyping" hidden role="status" aria-label="Egliane Assistant is typing">
                    <div class="typing-bubble">
                        <span class="typing-avatar" aria-hidden="true">E</span>
                        <span class="typing-dots"><span></span><span></span><span></span></span>
                    </div>
                    <span class="typing-note">{{ $chatName }} is typing&hellip;</span>
                </div>
                <div class="admin-chat-input-row">
                    <textarea id="adminChatInput" rows="1" placeholder="Ask about filings, documents, services&hellip;" autocomplete="off" aria-label="Your message"></textarea>
                    <button type="button" id="adminChatSend" aria-label="Send message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
                    </button>
                </div>
                <div class="admin-chat-hint" aria-hidden="true">Enter to send &middot; Shift + Enter for a new line</div>
            </footer>
        </section>

        {{-- ===================== SETTINGS VIEW ===================== --}}
        <div class="chatbot-settings" id="chatbotSettingsWrap" hidden aria-label="Chatbot settings">
            <div class="chatbot-settings-card">
                <header class="chatbot-settings-head">
                    <div class="chatbot-settings-head-top">
                        <span class="chatbot-settings-eyebrow">Chatbot</span>
                        <button type="button" class="chatbot-settings-back" data-back-to-chat aria-expanded="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            Back to Chat
                        </button>
                    </div>
                    <h1 class="chatbot-settings-title">Chatbot Settings</h1>
                    <p class="chatbot-settings-sub">Customize how the {{ $chatName }} responds to visitors.</p>
                </header>

                <nav class="chatbot-settings-nav" aria-label="Settings sections">
                    <button type="button" class="chatbot-settings-nav-item active" data-settings-nav="section-general" data-settings-nav-label="General" aria-current="true">General</button>
                    <button type="button" class="chatbot-settings-nav-item" data-settings-nav="section-rules" data-settings-nav-label="Response Rules" aria-current="false">Response Rules</button>
                </nav>

                <form method="POST" action="{{ route('admin.chatbot.update') }}" id="chatbotSettingsForm">
                    @csrf
                    <div class="chatbot-settings-body">

                        <section class="chatbot-settings-section" id="section-general">
                            <header class="chatbot-settings-section-head">
                                <div class="chatbot-settings-section-title">
                                    <span class="chatbot-settings-section-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                                    </span>
                                    <h3>General</h3>
                                </div>
                                <p>Control the assistant&rsquo;s main messages.</p>
                            </header>
                            <div class="chatbot-settings-fields">
                                <div class="form-group">
                                    <span class="form-label">Assistant name</span>
                                    <div class="chatbot-identity-row">
                                        <div class="chatbot-identity-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::substr($chatName, 0, 1) }}</div>
                                        <div>
                                            <div class="chatbot-identity-name">{{ $chatName }}</div>
                                            <div class="form-hint chatbot-identity-hint">Managed by Egliane. The name and brand are set automatically.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="chatbot_welcome">Welcome message</label>
                                    <textarea class="form-control" id="chatbot_welcome" name="chatbot_welcome" rows="3" maxlength="500">{{ old('chatbot_welcome', $chatWelcome) }}</textarea>
                                    <p class="form-hint">Shown when someone opens the assistant.</p>
                                    @error('chatbot_welcome')<div class="form-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="chatbot_fallback">Fallback message</label>
                                    <textarea class="form-control" id="chatbot_fallback" name="chatbot_fallback" rows="3" maxlength="500">{{ old('chatbot_fallback', $chatFallback) }}</textarea>
                                    <p class="form-hint">Shown when the assistant cannot match a question.</p>
                                    @error('chatbot_fallback')<div class="form-error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </section>

                        <section class="chatbot-settings-section" id="section-rules">
                            <header class="chatbot-settings-section-head">
                                <div class="chatbot-settings-section-title">
                                    <span class="chatbot-settings-section-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2a2.8 2.8 0 1 1 5 2L7 19l-5 1 1-5L17 2z"/></svg>
                                    </span>
                                    <h3>Response rules</h3>
                                </div>
                                <p>Create keyword-based answers for common questions.</p>
                                <p class="chatbot-settings-hint">Keywords are comma separated. A visitor&rsquo;s message matches when it contains any keyword.</p>
                            </header>
                            <div id="ruleRows" class="chatbot-rule-list">
                                @foreach ($chatRules as $rule)
                                    <div class="chatbot-rule">
                                        <div class="chatbot-rule-head">
                                            <span class="chatbot-rule-index">Rule {{ $loop->iteration }}</span>
                                            <button type="button" class="chatbot-rule-remove" data-remove-rule aria-label="Remove rule {{ $loop->iteration }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </div>
                                        <div class="chatbot-rule-fields">
                                            <label class="chatbot-rule-label" for="rule-keywords-{{ $loop->index }}">Keywords</label>
                                            <input class="form-control" id="rule-keywords-{{ $loop->index }}" name="rules[{{ $loop->index }}][keywords]" placeholder="e.g. price, rates, cost" value="{{ is_array($rule['keywords'] ?? null) ? implode(', ', $rule['keywords']) : ($rule['keywords'] ?? '') }}">
                                            <label class="chatbot-rule-label" for="rule-response-{{ $loop->index }}">Response</label>
                                            <textarea class="form-control" id="rule-response-{{ $loop->index }}" name="rules[{{ $loop->index }}][response]" rows="3" placeholder="The assistant&rsquo;s reply">{{ $rule['response'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="chatbot-rule-add" id="addRule">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                <span>Add response rule</span>
                            </button>
                        </section>

                        <section class="chatbot-settings-section" id="section-availability">
                            <header class="chatbot-settings-section-head">
                                <div class="chatbot-settings-section-title">
                                    <span class="chatbot-settings-section-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                                    </span>
                                    <h3>Availability</h3>
                                </div>
                                <p>Turn the assistant on or off across Egliane&rsquo;s chat points.</p>
                            </header>
                            <label class="checkbox-row">
                                <input type="checkbox" name="chatbot_enabled" value="1" @checked($chatbot_enabled === '1')>
                                <span>Enable chatbot</span>
                            </label>
                        </section>
                    </div>

                    <footer class="chatbot-settings-actions">
                        <p class="chatbot-settings-actions-note">Changes are saved to the chatbot configuration.</p>
                        <div class="chatbot-settings-actions-btns">
                            <button type="button" class="btn btn-outline" data-back-to-chat>Back to Chat</button>
                            <button type="submit" class="btn btn-primary chatbot-settings-save" id="chatbotSettingsSave">
                                Save settings
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script src="/js/admin-chat.js?v=2" defer></script>
<script>
(function () {
    function renumberRules() {
        var rows = document.querySelectorAll('#ruleRows .chatbot-rule');
        Array.prototype.forEach.call(rows, function (row, i) {
            var idx = row.querySelector('.chatbot-rule-index');
            if (idx) idx.textContent = 'Rule ' + (i + 1);
            var rm = row.querySelector('[data-remove-rule]');
            if (rm) rm.setAttribute('aria-label', 'Remove rule ' + (i + 1));
        });
    }

    var addRuleBtn = document.getElementById('addRule');
    if (addRuleBtn) {
        var addSeq = 0;
        addRuleBtn.addEventListener('click', function () {
            var n = 1000 + (addSeq++);
            var row = document.createElement('div');
            row.className = 'chatbot-rule';
            row.innerHTML = '<div class="chatbot-rule-head">' +
                '<span class="chatbot-rule-index"></span>' +
                '<button type="button" class="chatbot-rule-remove" data-remove-rule aria-label="Remove rule">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>' +
                '</div>' +
                '<div class="chatbot-rule-fields">' +
                '<label class="chatbot-rule-label" for="rule-keywords-new-' + n + '">Keywords</label>' +
                '<input class="form-control" id="rule-keywords-new-' + n + '" name="rules[' + n + '][keywords]" placeholder="e.g. price, rates, cost">' +
                '<label class="chatbot-rule-label" for="rule-response-new-' + n + '">Response</label>' +
                '<textarea class="form-control" id="rule-response-new-' + n + '" name="rules[' + n + '][response]" rows="3" placeholder="The assistant\u2019s reply"></textarea>' +
                '</div>';
            document.getElementById('ruleRows').appendChild(row);
            renumberRules();
            var first = row.querySelector('.form-control');
            if (first) {
                first.focus();
                if (first.scrollIntoView) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-remove-rule]')) {
            var rule = e.target.closest('.chatbot-rule');
            if (rule) {
                rule.remove();
                renumberRules();
            }
        }
    });

    var navItems = Array.prototype.slice.call(document.querySelectorAll('.chatbot-settings-nav-item'));
    function setActive(label) {
        navItems.forEach(function (it) {
            var on = it.getAttribute('data-settings-nav-label') === label;
            it.classList.toggle('active', on);
            it.setAttribute('aria-current', on ? 'true' : 'false');
        });
    }
    navItems.forEach(function (it) {
        it.addEventListener('click', function () {
            var sec = document.getElementById(it.getAttribute('data-settings-nav'));
            if (sec && sec.scrollIntoView) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setActive(it.getAttribute('data-settings-nav-label'));
        });
    });
    var spyTimer = null;
    function spy() {
        spyTimer = null;
        var wrap = document.getElementById('chatbotSettingsWrap');
        if (!wrap || wrap.hasAttribute('hidden')) return;
        var top = wrap.getBoundingClientRect().top;
        var current = null;
        navItems.forEach(function (it) {
            var sec = document.getElementById(it.getAttribute('data-settings-nav'));
            if (!sec) return;
            var r = sec.getBoundingClientRect();
            if (r.top - top <= 140) current = it.getAttribute('data-settings-nav-label');
        });
        if (current) setActive(current);
    }
    window.addEventListener('scroll', function () { if (!spyTimer) spyTimer = setTimeout(spy, 80); }, { passive: true });
    var spyWrap = document.getElementById('chatbotSettingsWrap');
    if (spyWrap) spyWrap.addEventListener('scroll', function () { if (!spyTimer) spyTimer = setTimeout(spy, 80); }, { passive: true });
    setTimeout(spy, 350);
})();
</script>
@endpush