@php($menuId = 'coll-more-' . $menuType . '-' . $billing->id)
<div class="actions-compact">
    <a href="{{ route('admin.billing.receipt', $billing) }}" class="btn btn-outline btn-sm">View receipt</a>
    <div class="dropdown-wrap">
        <button type="button" class="more-btn"
                id="{{ $menuId }}-btn"
                data-dropdown="{{ $menuId }}"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="{{ $menuId }}"
                aria-label="More actions"
                title="More actions">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg>
        </button>
        <div class="dropdown-menu more-menu" id="{{ $menuId }}" role="menu" aria-labelledby="{{ $menuId }}-btn">
            <form method="POST" action="{{ route('admin.collections.remind', $billing) }}" role="none">
                @csrf
                <button type="submit" class="dropdown-item btn-item" role="menuitem">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Send reminder
                </button>
            </form>
            @if (auth()->user()->isAdmin())
                <div class="dropdown-divider" role="separator"></div>
                <form method="POST" action="{{ route('admin.billing.pay', $billing) }}" class="menu-pay" role="none">
                    @csrf
                    <input type="hidden" name="status" value="paid">
                    <label class="menu-pay-label" for="paid-{{ $menuId }}">Date paid</label>
                    <div class="menu-pay-row">
                        <input class="form-control" id="paid-{{ $menuId }}" type="date" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}" aria-label="Date paid">
                        <button type="submit" class="btn btn-primary btn-sm">Mark paid</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>