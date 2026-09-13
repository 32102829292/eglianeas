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
                    <button type="button" class="chatbot-settings-back" data-back-to-chat aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        Back to Chat
                    </button>
                    <div class="chatbot-settings-title-row">
                        <div class="chatbot-settings-avatar" aria-hidden="true">
                            <span class="admin-chat-avatar-ring"></span>
                            <span class="admin-chat-avatar-core">E</span>
                        </div>
                        <div>
                            <h2 class="chatbot-settings-title">Chatbot Settings</h2>
                            <p class="chatbot-settings-sub">Configure how the {{ $chatName }} responds to users.</p>
                        </div>
                    </div>
                </header>

                <form method="POST" action="{{ route('admin.chatbot.update') }}" id="chatbotSettingsForm">
                    @csrf
                    <div class="chatbot-settings-body">

                        <section class="chatbot-settings-section">
                            <header class="chatbot-settings-section-head">
                                <h3>General / Identity</h3>
                                <p>What messages the assistant uses when talking to people.</p>
                            </header>
                            <div class="chatbot-settings-fields">
                                <div class="form-group">
                                    <span class="form-label">Assistant name</span>
                                    <div class="chatbot-identity-row">
                                        <div class="chatbot-identity-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::substr($chatName, 0, 1) }}</div>
                                        <div>
                                            <div class="chatbot-identity-name">{{ $chatName }}</div>
                                            <div class="form-hint" style="margin-top:2px">Managed by Egliane. The name and brand are set automatically.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="chatbot_welcome">Welcome message</label>
                                    <textarea class="form-control" id="chatbot_welcome" name="chatbot_welcome" rows="2" maxlength="500">{{ old('chatbot_welcome', $chatWelcome) }}</textarea>
                                    <p class="form-hint">The first message shown when a visitor opens a chat.</p>
                                    @error('chatbot_welcome')<div class="form-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="chatbot_fallback">Fallback message</label>
                                    <textarea class="form-control" id="chatbot_fallback" name="chatbot_fallback" rows="2" maxlength="500">{{ old('chatbot_fallback', $chatFallback) }}</textarea>
                                    <p class="form-hint">Shown when the assistant cannot match a question to a rule.</p>
                                    @error('chatbot_fallback')<div class="form-error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </section>

                        <section class="chatbot-settings-section">
                            <header class="chatbot-settings-section-head">
                                <h3>Response rules</h3>
                                <p>Keywords (comma separated) that trigger a canned response. A message matches if it contains any keyword.</p>
                            </header>
                            <div id="ruleRows" class="chatbot-rule-list">
                                @foreach ($chatRules as $rule)
                                    <div class="chatbot-rule">
                                        <div class="chatbot-rule-fields">
                                            <label class="chatbot-rule-label" for="rule-keywords-{{ $loop->index }}">Keywords</label>
                                            <input class="form-control" id="rule-keywords-{{ $loop->index }}" name="rules[][keywords]" placeholder="e.g. price, rates, cost" value="{{ is_array($rule['keywords'] ?? null) ? implode(', ', $rule['keywords']) : ($rule['keywords'] ?? '') }}">
                                            <label class="chatbot-rule-label" for="rule-response-{{ $loop->index }}">Response</label>
                                            <textarea class="form-control" id="rule-response-{{ $loop->index }}" name="rules[][response]" rows="2" placeholder="The assistant&rsquo;s reply">{{ $rule['response'] ?? '' }}</textarea>
                                        </div>
                                        <button type="button" class="chatbot-rule-remove" data-remove-rule aria-label="Remove response rule">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="chatbot-rule-add" id="addRule">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                Add Response Rule
                            </button>
                        </section>

                        <section class="chatbot-settings-section">
                            <header class="chatbot-settings-section-head">
                                <h3>Availability</h3>
                                <p>Turn the assistant on or off across Egliane&rsquo;s chat points.</p>
                            </header>
                            <label class="checkbox-row">
                                <input type="checkbox" name="chatbot_enabled" value="1" @checked($chatbot_enabled === '1')>
                                <span>Enable chatbot</span>
                            </label>
                        </section>
                    </div>

                    <footer class="chatbot-settings-actions">
                        <button type="button" class="btn btn-outline" data-back-to-chat>Cancel</button>
                        <button type="submit" class="btn btn-primary chatbot-settings-save" id="chatbotSettingsSave">
                            Save Changes
                        </button>
                    </footer>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script src="/js/admin-chat.js?v=2" defer></script>
<script>
document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('#addRule');
    if (addBtn) {
        var n = document.querySelectorAll('.chatbot-rule').length;
        var row = document.createElement('div');
        row.className = 'chatbot-rule';
        row.innerHTML = '<div class="chatbot-rule-fields">' +
            '<label class="chatbot-rule-label" for="rule-keywords-new-' + n + '">Keywords</label>' +
            '<input class="form-control" id="rule-keywords-new-' + n + '" name="rules[][keywords]" placeholder="e.g. price, rates, cost">' +
            '<label class="chatbot-rule-label" for="rule-response-new-' + n + '">Response</label>' +
            '<textarea class="form-control" id="rule-response-new-' + n + '" name="rules[][response]" rows="2" placeholder="The assistant\u2019s reply"></textarea>' +
            '</div>' +
            '<button type="button" class="chatbot-rule-remove" data-remove-rule aria-label="Remove response rule">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>';
        document.getElementById('ruleRows').appendChild(row);
        var first = row.querySelector('.form-control');
        if (first) first.focus();
    }
    if (e.target.closest('[data-remove-rule]')) {
        var rule = e.target.closest('.chatbot-rule');
        if (rule) rule.remove();
    }
});
</script>
@endpush