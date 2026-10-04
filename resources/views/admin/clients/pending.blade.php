@extends('layouts.dashboard')

@section('title', 'Pending Accounts — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Pending Accounts</h1>
            <p>Approve or reject newly registered client accounts.</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('admin.clients.index') }}" class="btn btn-outline btn-sm">All Clients</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-head">
            <h2 class="card-title">Awaiting approval ({{ $clients->total() }})</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 team-table">
                <thead class="thead-muted">
                    <tr>
                        <th>Client</th>
                        <th>Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td data-col="Client">
                                <div class="member-cell">
                                    <span class="avatar avatar-md avatar-tint">{{ mb_strtoupper(mb_substr($client->business_name ?: $client->name, 0, 1)) }}</span>
                                    <span class="member-lines">
                                        <span class="member-name">{{ $client->business_name ?: $client->name }}</span>
                                        <span class="member-email">{{ $client->name }} &middot; <a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></span>
                                    </span>
                                </div>
                            </td>
                            <td data-col="Registered" class="muted">
                                {{ $client->created_at?->format('M j, Y g:i A') }}
                            </td>
                            <td class="text-end" data-col="Actions">
                                <div class="client-actions">
                                    <form method="POST" action="{{ route('admin.clients.approve', $client) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Approve {{ addslashes($client->business_name ?: $client->name) }}?', message: 'The client will be notified and will gain portal access.', confirmLabel: 'Approve' })">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-outline danger btn-sm" data-reject-toggle="{{ $client->id }}">Reject</button>
                                    <form method="POST" action="{{ route('admin.clients.reject', $client) }}" class="reject-form inline-form" id="reject-form-{{ $client->id }}" hidden onsubmit="return egliane.confirm.form(this, { title: 'Reject {{ addslashes($client->business_name ?: $client->name) }}?', message: 'A rejection reason is required and will be shown to the client.', danger: true, confirmLabel: 'Reject' })">
                                        @csrf
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (required)" maxlength="500" required>
                                        <button type="submit" class="btn btn-outline danger btn-sm">Confirm</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-cell">No accounts awaiting approval.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($clients as $client)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $client->business_name ?: $client->name }}</div>
                                <div class="cv-head-sub">{{ $client->name }} &middot; <a href="mailto:{{ $client->email }}" class="contact-link">{{ $client->email }}</a></div>
                            </div>
                            <span class="badge badge-warn">Pending</span>
                        </div>
                        <div class="cv-card-actions">
                            <form method="POST" action="{{ route('admin.clients.approve', $client) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Approve {{ addslashes($client->business_name ?: $client->name) }}?', message: 'The client will be notified and will gain portal access.', confirmLabel: 'Approve' })">@csrf<button type="submit" class="btn btn-primary btn-sm">Approve</button></form>
                            <button type="button" class="btn btn-outline danger btn-sm" data-reject-toggle="{{ $client->id }}">Reject</button>
                            <form method="POST" action="{{ route('admin.clients.reject', $client) }}" class="reject-form inline-form" id="reject-form-{{ $client->id }}" hidden onsubmit="return egliane.confirm.form(this, { title: 'Reject {{ addslashes($client->business_name ?: $client->name) }}?', message: 'A rejection reason is required and will be shown to the client.', danger: true, confirmLabel: 'Reject' })">
                                @csrf
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (required)" maxlength="500" required>
                                <button type="submit" class="btn btn-outline danger btn-sm">Confirm</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No accounts awaiting approval.</p>
                @endforelse
            </div>
            @if ($clients->hasPages())
                <div class="pagination-wrap mt-3">{{ $clients->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-reject-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = document.getElementById('reject-form-' + btn.dataset.rejectToggle);
            if (form) {
                form.hidden = !form.hidden;
            }
        });
    });
})();
</script>
@endpush