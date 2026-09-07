@if ($isImpersonating)
    <div class="impersonation-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <span>
            You are viewing as <strong>{{ $impersonator->name ?? 'Admin' }}</strong>&rsquo;s client: <strong>{{ auth()->user()->business_name ?: auth()->user()->name }}</strong>
        </span>
        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Exit to Admin</button>
        </form>
    </div>
    <div class="impersonation-spacer"></div>
@endif