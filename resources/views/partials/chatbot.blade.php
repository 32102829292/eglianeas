<button type="button" class="chat-fab" id="chatFab" aria-label="Open chat with Egliane Assistant">
    <img src="{{ asset('chatbot-icon.png') }}" alt="" class="chat-fab-icon">
</button>

<div class="chat-widget" id="chatWidget" role="dialog" aria-label="Egliane chat assistant">
    <div class="chat-head">
        <div class="avatar">E</div>
        <div>
            <div class="title">Egliane Assistant</div>
            <div class="sub"><span class="status-dot"></span>Online &middot; replies instantly</div>
        </div>
        <button type="button" class="close-chat" aria-label="Close chat">&times;</button>
    </div>
    <div class="chat-messages"></div>
    <div class="chat-quick">
        <button type="button" data-q="What is my filing status?">Filing status</button>
        <button type="button" data-q="What documents do I need to submit?">Documents</button>
        <button type="button" data-q="What are your pricing plans?">Pricing</button>
        <button type="button" data-q="How do I book a consultation?">Book a consultation</button>
        <button type="button" data-q="What are your business hours?">Business hours</button>
    </div>
    <div class="chat-input-row">
        <input type="text" id="chatInput" placeholder="Ask about filings, documents, services…" autocomplete="off">
        <button type="button" id="chatSend" aria-label="Send message">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
        </button>
    </div>
</div>
