@extends('layouts.dashboard')

@section('title', 'Chatbot — Egliane Accounting Services')

@section('content')
    @php
        $chatbotConfig = $chatbot_rules ? json_decode($chatbot_rules, true) : null;
        $chatWelcome = $chatbotConfig['welcome_message'] ?? config('chatbot.welcome_message');
        $chatFallback = $chatbotConfig['fallback_message'] ?? config('chatbot.fallback_message');
        $chatRules = $chatbotConfig['rules'] ?? config('chatbot.rules');
    @endphp

    <div class="page-head">
        <h1>Chatbot</h1>
        <p>Welcome message, fallback message, and keyword-matching rules for the Egliane Assistant.</p>
    </div>

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
@endsection

@push('scripts')
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
