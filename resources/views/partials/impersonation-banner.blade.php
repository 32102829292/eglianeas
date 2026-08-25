@if ($isImpersonating)
    <div class="impersonation-banner" style="
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 99999;
        background: #f59e0b;
        color: #1B1B3A;
        text-align: center;
        padding: 8px 16px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        <span>
            You are viewing as <strong>{{ $impersonator->name ?? 'Admin' }}</strong>&rsquo;s client: <strong>{{ auth()->user()->business_name ?: auth()->user()->name }}</strong>
        </span>
        <form method="POST" action="{{ route('admin.impersonate.stop') }}" style="margin:0;">
            @csrf
            <button type="submit" style="
                background: #1B1B3A;
                color: #f59e0b;
                border: none;
                padding: 4px 14px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                white-space: nowrap;
            ">Exit to Admin</button>
        </form>
    </div>
    <div style="height: 40px;"></div>
@endif
