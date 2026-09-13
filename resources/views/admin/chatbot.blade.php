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
        $chatWelcome = $chatbotConfig['welcome_message'] ?? config('chatbot.welcome_message');
        $chatFallback = $chatbotConfig['fallback_message'] ?? config('chatbot.fallback_message');
        $chatRules = $chatbotConfig['rules'] ?? config('chatbot.rules');
    @endphp

    <div class="chatbot-settings" id="chatbotSettingsWrap" hidden>
        <div class="card">
            <h3 class="card-title">Chatbot replies</h3>
            <p class="card-sub">The Egliane Assistant answers using these rules. Keywords match by any word.</p>
            <form method="POST" action="{{ route('admin.chatbot.update') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="chatbot_welcome">Welcome message</label>
                    <textarea class="form-control" id="chatbot_welcome" name="chatbot_welcome" rows="2" maxlength="500">{{ old('chatbot_welcome', $chatWelcome) }}</textarea>
                    @error('chatbot_welcome')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="chatbot_fallback">Fallback message</label>
                    <textarea class="form-control" id="chatbot_fallback" name="chatbot_fallback" rows="2" maxlength="500">{{ old('chatbot_fallback', $chatFallback) }}</textarea>
                    @error('chatbot_fallback')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Rules</label>
                    <div id="ruleRows">
                        @foreach ($chatRules as $rule)
                            <div class="rule-row">
                                <input class="form-control" name="rules[][keywords]" placeholder="keywords (comma separated)" value="{{ is_array($rule['keywords'] ?? null) ? implode(', ', $rule['keywords']) : ($rule['keywords'] ?? '') }}">
                                <input class="form-control" name="rules[][response]" placeholder="response" value="{{ $rule['response'] ?? '' }}">
                                <button type="button" class="btn btn-outline btn-sm danger" data-remove-rule>&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-outline btn-sm mt-2" id="addRule">+ Add rule</button>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" name="chatbot_enabled" value="1" @checked($chatbot_enabled === '1')>
                    <span>Enable chatbot</span>
                </label>
                <button type="submit" class="btn btn-primary mt-2">Save chatbot</button>
            </form>
        </div>
    </div>

    <section class="admin-chat" aria-label="Egliane Assistant conversation">
        <header class="admin-chat-head">
            <div class="avatar">E</div>
            <div class="admin-chat-identity">
                <div class="title">Egliane Assistant</div>
                <div class="sub">Ask questions about Egliane Accounting Services, services, documents, filing status, and more.</div>
            </div>
            <span class="admin-chat-status"><span class="status-dot"></span>Online &middot; replies instantly</span>
            <button type="button" class="admin-chat-settings-btn" id="chatbotSettingsToggle" aria-expanded="false">Chatbot Settings</button>
        </header>

        <div class="admin-chat-messages" id="adminChatMessages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Chat history"></div>

        <div class="admin-chat-quick" id="adminChatQuick">
            <button type="button" data-q="What services do you offer?">What services do you offer?</button>
            <button type="button" data-q="How can I check my filing status?">How can I check my filing status?</button>
            <button type="button" data-q="What documents do I need?">What documents do I need?</button>
            <button type="button" data-q="How do I contact Egliane?">How do I contact Egliane?</button>
            <button type="button" data-q="How much does your service cost?">How much does your service cost?</button>
        </div>

        <footer class="admin-chat-composer">
            <div class="typing-indicator" id="adminChatTyping" hidden role="status" aria-label="Egliane Assistant is typing">
                <div class="typing-bubble"><span class="typing-dots"><span></span><span></span><span></span></div>
                <span class="typing-note">Egliane Assistant is typing&hellip;</span>
            </div>
            <div class="admin-chat-input-row">
                <textarea id="adminChatInput" rows="1" placeholder="Ask about filings, documents, services&hellip;" autocomplete="off" aria-label="Your message"></textarea>
                <button type="button" id="adminChatSend" aria-label="Send message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
                </button>
            </div>
        </footer>
    </section>
@endsection

@push('scripts')
<script src="/js/admin-chat.js?v=1" defer></script>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('#addRule')) {
        var row = document.createElement('div');
        row.className = 'rule-row';
        row.innerHTML = '<input class="form-control" name="rules[][keywords]" placeholder="keywords (comma separated)">' +
            '<input class="form-control" name="rules[][response]" placeholder="response">' +
            '<button type="button" class="btn btn-outline btn-sm danger" data-remove-rule>&times;</button>';
        document.getElementById('ruleRows').appendChild(row);
    }
    if (e.target.closest('[data-remove-rule]')) {
        var row = e.target.closest('.rule-row');
        if (row) row.remove();
    }
});
</script>
@endpush