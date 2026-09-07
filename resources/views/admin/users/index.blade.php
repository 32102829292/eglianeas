@extends('layouts.dashboard')

@section('title', 'Team Accounts — Egliane Accounting Services')

@section('content')
    @php
        $initials = function (string $name): string {
            $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            return strtoupper(($parts[0][0] ?? '?').($parts[1][0] ?? ''));
        };
        $lastActiveAt = \App\Models\ActivityLog::query()
            ->whereIn('user_id', $accounts->pluck('id'))
            ->selectRaw('user_id, MAX(created_at) as last_at')
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id');
        $activeCount = $accounts->filter(fn ($a) => $lastActiveAt->has($a->id))->count();
        $adminCount = $accounts->where('role', 'admin')->count();
    @endphp

    <div class="page-head page-head-row">
        <div>
            <h1>Team Accounts</h1>
            <p>Manage staff accounts, roles, and access.</p>
        </div>
        <div class="page-head-actions">
            <a href="#team-create" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add team member
            </a>
        </div>
    </div>

    <div class="stat-grid cols-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <span class="stat-label">Total members</span>
            <b class="stat-value">{{ $accounts->count() }}</b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Active</span>
            <b class="stat-value">{{ $activeCount }}</b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">Inactive</span>
            <b class="stat-value">{{ $accounts->count() - $activeCount }}</b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <span class="stat-label">Admins</span>
            <b class="stat-value">{{ $adminCount }}</b>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-head">
            <h2 class="card-title">Team members</h2>
        </div>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 team-table">
                <thead class="thead-muted">
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th class="text-center">Status</th>
                        <th>Last active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td data-col="Name">
                                <div class="member-cell">
                                    <span class="avatar avatar-md avatar-tint">{{ $initials($account->name) }}</span>
                                    <span class="member-lines">
                                        <span class="member-name">{{ $account->name }} @if ($account->id === auth()->id())<small class="muted">(You)</small>@endif</span>
                                        <span class="member-email">{{ $account->email }}</span>
                                    </span>
                                </div>
                            </td>
                            <td data-col="Role">
                                <span class="badge @if ($account->isAdmin()) badge-admin @else badge-staff @endif">{{ ucfirst($account->role) }}</span>
                            </td>
                            <td class="text-center" data-col="Status">
                                @if ($lastActiveAt->has($account->id))
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-neutral">Inactive</span>
                                @endif
                            </td>
                            <td data-col="Last active" class="muted">
                                {{ $lastActiveAt[$account->id] ?? '' ? \Carbon\Carbon::parse($lastActiveAt[$account->id])->format('M j, Y') : '—' }}
                            </td>
                            <td class="text-end" data-col="Actions">
                                @if (auth()->user()->isAdmin() && $account->id !== auth()->id())
                                    <div class="member-actions">
                                        <form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No admin or staff accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($accounts as $account)
                    <div class="cv-card">
                        <div class="cv-member">
                            <span class="avatar avatar-md avatar-tint">{{ $initials($account->name) }}</span>
                            <div class="cv-member-lines">
                                <div class="member-name">{{ $account->name }} @if ($account->id === auth()->id())<small class="muted">(You)</small>@endif</div>
                                <div class="member-email">{{ $account->email }}</div>
                            </div>
                        </div>
                        <div class="cv-row"><span class="cv-label">Role</span><span class="cv-value"><span class="badge @if ($account->isAdmin()) badge-admin @else badge-staff @endif">{{ ucfirst($account->role) }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value">@if ($lastActiveAt->has($account->id))<span class="badge badge-success">Active</span>@else<span class="badge badge-neutral">Inactive</span>@endif</span></div>
                        <div class="cv-row"><span class="cv-label">Last active</span><span class="cv-value">{{ $lastActiveAt[$account->id] ?? '' ? \Carbon\Carbon::parse($lastActiveAt[$account->id])->format('M j, Y') : '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value">@if (auth()->user()->isAdmin() && $account->id !== auth()->id())<form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>@else<span class="muted">—</span>@endif</span></div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No admin or staff accounts yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card card-narrow" id="team-create">
        <div class="card-head">
            <h2 class="card-title">Add a team member</h2>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required placeholder="Full name">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required placeholder="name@example.com">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="staff" @selected(old('role') === 'staff')>Staff</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                    </select>
                    @error('role')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="btn-group-row">
                <button type="submit" class="btn btn-primary">Create account</button>
            </div>
        </form>
    </div>
@endsection