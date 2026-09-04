@extends('layouts.dashboard')

@section('title', 'Team Accounts — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Team accounts</h1>
            <p>Manage admin and staff portal accounts.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">Create an account</h2>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
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

    <div class="card mt-4">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td data-col="Name">
                                <div class="fw-semibold">{{ $account->name }}</div>
                                @if ($account->id === auth()->id())
                                    <small class="text-muted">You</small>
                                @endif
                            </td>
                            <td data-col="Email">{{ $account->email }}</td>
                            <td data-col="Role">
                                <span class="badge @if($account->isAdmin()) badge-warn @else badge-neutral @endif">{{ ucfirst($account->role) }}</span>
                            </td>
                            <td data-col="Created" class="text-muted">{{ $account->created_at?->format('M j, Y') ?? '—' }}</td>
                            <td data-col="Actions">
                                @if (auth()->user()->isAdmin() && $account->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $account) }}" style="display:inline;" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No admin or staff accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($accounts as $account)
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Name</span><span class="cv-value">{{ $account->name }} @if($account->id === auth()->id()) <small class="text-muted">(You)</small> @endif</span></div>
                        <div class="cv-row"><span class="cv-label">Email</span><span class="cv-value">{{ $account->email }}</span></div>
                        <div class="cv-row"><span class="cv-label">Role</span><span class="cv-value"><span class="badge @if($account->isAdmin()) badge-warn @else badge-neutral @endif">{{ ucfirst($account->role) }}</span></span></div>
                        <div class="cv-row"><span class="cv-label">Created</span><span class="cv-value">{{ $account->created_at?->format('M j, Y') ?? '—' }}</span></div>
                        <div class="cv-row"><span class="cv-label">Actions</span><span class="cv-value">@if (auth()->user()->isAdmin() && $account->id !== auth()->id())<form method="POST" action="{{ route('admin.users.destroy', $account) }}" style="display:inline;" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>@else<span class="text-muted">—</span>@endif</span></div>
                    </div>
                @empty
                    <p class="cv-card" style="text-align:center;color:var(--text-muted);">No admin or staff accounts yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection