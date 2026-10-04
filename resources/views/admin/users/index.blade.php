@extends('layouts.dashboard')

@section('title', 'Team Accounts — Egliane Accounting Services')

@section('content')
    @php
        $editing = $editing ?? null;
        $initials = function (string $name): string {
            $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            return strtoupper(($parts[0][0] ?? '?').($parts[1][0] ?? ''));
        };
        $firstWord = function (string $name): string {
            $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            return $parts[0] ?? '';
        };
        $restOfName = function (string $name): string {
            $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            array_shift($parts);
            return implode(' ', $parts);
        };
        $members = $accounts->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'first' => $firstWord($a->name),
            'last' => $restOfName($a->name),
            'email' => $a->email,
            'role' => $a->role,
            'position' => $a->position ?? '',
            'contact_no' => $a->contact_no ?? '',
            'photo' => $a->photoUrl(),
        ])->values();
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
            <p>Manage staff, supervisor, and admin accounts, roles, and access.</p>
        </div>
        <div class="page-head-actions">
            <button type="button" class="btn btn-primary" id="addTeamBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add team member
            </button>
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
                        <th>Details</th>
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
                                    <span class="avatar avatar-md {{ $account->photoUrl() ? 'avatar-photo' : 'avatar-tint' }}">
                                        @if ($account->photoUrl())
                                            <img src="{{ $account->photoUrl() }}" alt="{{ $account->name }}" loading="lazy">
                                        @else
                                            {{ $initials($account->name) }}
                                        @endif
                                    </span>
                                    <span class="member-lines">
                                        <span class="member-name">{{ $account->name }} @if ($account->id === auth()->id())<small class="muted">(You)</small>@endif</span>
                                        <span class="member-email">{{ $account->email }}</span>
                                        @if ($account->position)<span class="member-sub">{{ $account->position }}</span>@endif
                                    </span>
                                </div>
                            </td>
                            <td data-col="Role">
                                <span class="badge @if ($account->isAdmin()) badge-admin @elseif ($account->isSupervisor()) badge-supervisor @else badge-staff @endif">{{ ucfirst($account->role) }}</span>
                            </td>
                            <td data-col="Details" class="muted">
                                @if ($account->contact_no)
                                    <span>{{ $account->contact_no }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
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
                                <div class="member-actions">
                                    <button type="button" class="btn btn-outline btn-sm" data-team-edit="{{ $account->id }}">Edit</button>
                                    @if (auth()->user()->isAdmin() && $account->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">No admin or staff accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-view-list">
                @forelse ($accounts as $account)
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <span class="avatar avatar-md {{ $account->photoUrl() ? 'avatar-photo' : 'avatar-tint' }}">
                                @if ($account->photoUrl())
                                    <img src="{{ $account->photoUrl() }}" alt="{{ $account->name }}" loading="lazy">
                                @else
                                    {{ $initials($account->name) }}
                                @endif
                            </span>
                            <div class="cv-head-main">
                                <div class="cv-head-title">{{ $account->name }} @if ($account->id === auth()->id())<small class="muted">(You)</small>@endif</div>
                                <div class="cv-head-sub">{{ $account->email }}</div>
                            </div>
                            @if ($lastActiveAt->has($account->id))<span class="badge badge-success">Active</span>@else<span class="badge badge-neutral">Inactive</span>@endif
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Role</span><span class="cv-value"><span class="badge @if ($account->isAdmin()) badge-admin @elseif ($account->isSupervisor()) badge-supervisor @else badge-staff @endif">{{ ucfirst($account->role) }}</span></span></div>
                            <div class="cv-pair"><span class="cv-label">Position</span><span class="cv-value">{{ $account->position ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Contact</span><span class="cv-value">{{ $account->contact_no ?? '—' }}</span></div>
                            <div class="cv-pair"><span class="cv-label">Last active</span><span class="cv-value">{{ $lastActiveAt[$account->id] ?? '' ? \Carbon\Carbon::parse($lastActiveAt[$account->id])->format('M j, Y') : '—' }}</span></div>
                        </div>
                        <div class="cv-card-actions">
                            <button type="button" class="btn btn-outline btn-sm" data-team-edit="{{ $account->id }}">Edit</button>
                            @if (auth()->user()->isAdmin() && $account->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Delete {{ addslashes($account->name) }}?', message: 'This {{ $account->role }} account can be restored by support.', danger: true, confirmLabel: 'Delete' })">@csrf @method('DELETE')<button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="cv-card cv-empty">No admin or staff accounts yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <script id="teamMembersData" type="application/json">@json($members)</script>

    <div class="modal fade team-modal" id="teamModal" tabindex="-1" role="dialog"
         aria-labelledby="teamModalTitle" aria-hidden="true"
         data-store-url="{{ route('admin.users.store') }}"
         data-update-base="{{ url('/admin/users') }}"
         data-initial-edit="{{ $editing?->id ?? '' }}">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable team-modal-dialog" role="document">
            <div class="modal-content team-modal-content">
                <button type="button" class="btn-close team-modal-close" data-team-close aria-label="Close"></button>
                <div class="team-modal-head">
                    <div>
                        <h3 class="team-modal-title" id="teamModalTitle">Add Team Member</h3>
                        <p class="team-modal-sub" id="teamModalSub">Create a new team account and assign their role.</p>
                    </div>
                </div>
                <form id="teamForm" novalidate>
                    <div class="team-modal-body">
                        <div class="team-photo-row">
                            <span class="team-photo-frame" id="teamPhotoFrame">
                                <img id="teamPhotoImg" alt="Team member photo">
                                <span class="team-photo-initials" id="teamPhotoInitials"></span>
                            </span>
                            <div class="team-photo-actions">
                                <label class="btn btn-outline btn-sm team-upload-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    Upload photo
                                    <input type="file" id="teamPhotoInput" name="profile_image" accept="image/jpeg,image/png,image/webp" hidden>
                                </label>
                                <p class="team-photo-hint" id="teamPhotoHint">JPG, JPEG, PNG or WEBP &mdash; up to 2 MB.</p>
                            </div>
                        </div>
                        <div class="form-group" data-field="profile_image">
                            <div class="form-error team-field-error" id="teamPhotoError" hidden></div>
                        </div>

                        <div class="form-grid two team-form-grid">
                            <div class="form-group" data-field="first_name">
                                <label class="form-label" for="teamFirstName">First name <span class="team-req">*</span></label>
                                <input type="text" class="form-control" id="teamFirstName" name="first_name" maxlength="255" autocomplete="off">
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                            <div class="form-group" data-field="last_name">
                                <label class="form-label" for="teamLastName">Last name <span class="team-req">*</span></label>
                                <input type="text" class="form-control" id="teamLastName" name="last_name" maxlength="255" autocomplete="off">
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                            <div class="form-group" data-field="email">
                                <label class="form-label" for="teamEmail">Email <span class="team-req">*</span></label>
                                <input type="email" class="form-control" id="teamEmail" name="email" maxlength="255" autocomplete="off">
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                            <div class="form-group" data-field="contact_no">
                                <label class="form-label" for="teamContact">Contact number <span class="team-opt">(optional)</span></label>
                                <input type="text" class="form-control" id="teamContact" name="contact_no" maxlength="20" autocomplete="off" placeholder="e.g. 0917 123 4567">
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                            <div class="form-group" data-field="position">
                                <label class="form-label" for="teamPosition">Position <span class="team-opt">(optional)</span></label>
                                <input type="text" class="form-control" id="teamPosition" name="position" maxlength="100" autocomplete="off" placeholder="e.g. Senior Accountant">
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                            <div class="form-group" data-field="role">
                                <label class="form-label" for="teamRole">Role <span class="team-req">*</span></label>
                                <select class="form-control" id="teamRole" name="role">
                                    <option value="staff">Staff</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <div class="form-error team-field-error" hidden></div>
                            </div>
                        </div>
                        <input type="hidden" name="name" id="teamName">
                    </div>
                    <div class="team-modal-foot">
                        <button type="button" class="btn btn-outline" id="teamCancelBtn" data-team-cancel>Cancel</button>
                        <button type="submit" class="btn btn-primary team-save-btn" id="teamSaveBtn"><span id="teamSaveLabel">Save Team Member</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Team member modal — scoped to defeat app.css's generic `.modal` overlay
       (flex, always visible) on the Bootstrap modal scaffold. */
    .team-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100%;
        z-index: 1060;
        overflow-x: hidden;
        overflow-y: auto;
        outline: 0;
        padding: 0 !important;
        margin: 0;
        background: transparent;
        align-items: normal;
        justify-content: normal;
    }

    .team-modal .team-modal-dialog {
        margin: 2.5vh auto;
        max-width: 600px;
        width: calc(100% - 32px);
    }

    .team-modal .team-modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(27, 27, 58, 0.28);
        overflow: hidden;
        background: #fff;
    }

    .team-modal .team-modal-content::before {
        content: "";
        display: block;
        height: 4px;
        background: linear-gradient(90deg, #1B1B3A, rgba(90, 179, 240, 0.55));
    }

    .team-modal .team-modal-close {
        position: absolute;
        top: 16px;
        right: 16px;
        opacity: 0.55;
        border-radius: 50%;
        z-index: 10;
    }
    .team-modal .team-modal-close:hover { opacity: 1; }

    .team-modal .team-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 26px 16px;
        background: #fff;
    }
    .team-modal .team-modal-head.has-close { padding-right: 56px; }

    .team-modal .team-modal-title {
        font-family: var(--font-head, 'Space Grotesk', sans-serif);
        font-weight: 700;
        font-size: 1.2rem;
        color: #1B1B3A;
        margin: 0 0 3px;
        letter-spacing: -0.01em;
    }
    .team-modal .team-modal-sub {
        margin: 0;
        color: #6b7280;
        font-size: 0.9rem;
        line-height: 1.45;
    }

    .team-modal .team-modal-body {
        overflow-y: auto;
        padding: 20px 26px 4px;
    }

    .team-modal .team-photo-row {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 16px;
    }
    .team-modal .team-photo-frame {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: rgba(90, 179, 240, 0.14);
        color: var(--sky-deep, #2b6ca3);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex: 0 0 68px;
        font-weight: 700;
        font-family: var(--font-head, 'Space Grotesk', sans-serif);
        font-size: 22px;
        border: 2px solid rgba(90, 179, 240, 0.35);
    }
    .team-modal .team-photo-frame img {
        display: none;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .team-modal .team-photo-frame.has-img img {
        display: block;
    }
    .team-modal .team-photo-frame.has-img .team-photo-initials {
        display: none;
    }
    .team-modal .team-photo-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .team-modal .team-photo-hint {
        margin: 0;
        font-size: 12px;
        color: #6b7280;
        line-height: 1.4;
    }
    .team-modal .team-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        cursor: pointer;
    }
    .team-modal .team-req {
        color: var(--danger, #dc3545);
        font-weight: 700;
    }
    .team-modal .team-opt {
        color: #9ca3af;
        font-weight: 500;
    }

    .team-modal .team-form-grid .form-group {
        margin-bottom: 14px;
    }
    .team-modal .team-form-grid .form-group .form-label {
        margin-bottom: 5px;
    }
    .team-modal .form-control:focus {
        border-color: var(--sky, #5ab3f0);
        box-shadow: 0 0 0 3px rgba(90, 179, 240, 0.18);
    }
    .team-modal .form-group[data-field="profile_image"] {
        margin-bottom: 0;
    }
    .team-modal .team-field-error {
        font-size: 12.5px;
    }

    .team-modal .team-modal-foot {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 26px 22px;
        border-top: 1px solid rgba(229, 231, 235, 0.9);
        background: #fff;
    }
    .team-modal .team-modal-foot .btn {
        min-width: 120px;
        border-radius: 10px;
        font-weight: 600;
    }

    @media (max-width: 520px) {
        .team-modal .team-modal-dialog {
            margin: 0;
            width: 100%;
            max-width: 100%;
            height: 100%;
            max-height: 100%;
        }
        .team-modal .modal-content {
            border-radius: 0;
            min-height: 100%;
        }
        .team-modal .team-modal-head {
            padding: 18px 52px 14px 20px;
        }
        .team-modal .team-modal-close {
            top: 14px;
            right: 14px;
        }
        .team-modal .team-modal-body {
            padding: 18px 20px 2px;
        }
        .team-modal .team-modal-foot {
            padding: 13px 20px calc(18px + env(safe-area-inset-bottom, 0px));
        }
        .team-modal .team-modal-foot .btn {
            flex: 1;
            min-width: 0;
        }
        .team-modal .team-photo-row {
            gap: 14px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    function teamModalInit() {
    var modalEl = document.getElementById('teamModal');
    if (!modalEl) return;

    var members = [];
    var dataEl = document.getElementById('teamMembersData');
    if (dataEl) { try { members = JSON.parse(dataEl.textContent); } catch (e) {} }

    if (typeof bootstrap === 'undefined') return;
    var modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });

    var titleEl = document.getElementById('teamModalTitle');
    var subEl = document.getElementById('teamModalSub');
    var form = document.getElementById('teamForm');
    var firstName = document.getElementById('teamFirstName');
    var lastName = document.getElementById('teamLastName');
    var email = document.getElementById('teamEmail');
    var contact = document.getElementById('teamContact');
    var position = document.getElementById('teamPosition');
    var role = document.getElementById('teamRole');
    var photoInput = document.getElementById('teamPhotoInput');
    var photoImg = document.getElementById('teamPhotoImg');
    var photoInitials = document.getElementById('teamPhotoInitials');
    var photoFrame = document.getElementById('teamPhotoFrame');
    var photoHint = document.getElementById('teamPhotoHint');
    var saveBtn = document.getElementById('teamSaveBtn');
    var saveLabel = document.getElementById('teamSaveLabel');
    var cancelBtn = document.getElementById('teamCancelBtn');

    var csrfInput = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfInput ? csrfInput.getAttribute('content') : '';

    var state = { mode: 'add', id: null, photo: null, objectUrl: null, submitting: false };

    function findMember(id) {
        for (var i = 0; i < members.length; i++) if (members[i].id === id) return members[i];
        return null;
    }

    function fieldError(name) {
        var f = form.querySelector('[data-field="' + name + '"]');
        return f ? f.querySelector('.team-field-error') : null;
    }

    function setFieldError(name, msg) {
        var el = fieldError(name);
        if (!el) return;
        el.textContent = msg || '';
        el.hidden = !msg;
    }

    function resetErrors() {
        var errs = form.querySelectorAll('.team-field-error');
        for (var i = 0; i < errs.length; i++) { errs[i].textContent = ''; errs[i].hidden = true; }
    }

    function setInitialsPreview() {
        var f = firstName.value.trim();
        var l = lastName.value.trim();
        var text = ((f ? f.charAt(0) : '') + (l ? l.charAt(0) : '')).toUpperCase();
        photoInitials.textContent = text || '?';
    }

    function renderPhotoPreview() {
        if (state.objectUrl) {
            photoImg.setAttribute('src', state.objectUrl);
            photoFrame.classList.add('has-img');
            return;
        }
        if (state.mode === 'edit' && state.photo) {
            photoImg.setAttribute('src', state.photo);
            photoFrame.classList.add('has-img');
            return;
        }
        photoImg.removeAttribute('src');
        photoFrame.classList.remove('has-img');
        setInitialsPreview();
    }

    function saveLabelForMode() {
        return state.mode === 'edit' ? 'Save Changes' : 'Save Team Member';
    }

    function restoreButtons() {
        state.submitting = false;
        saveBtn.disabled = false;
        cancelBtn.disabled = false;
        saveLabel.textContent = saveLabelForMode();
    }

    var openRequested = false;

    function resetForm() {
        firstName.value = '';
        lastName.value = '';
        email.value = '';
        contact.value = '';
        position.value = '';
        role.value = 'staff';
        if (photoInput.files && photoInput.files.length) photoInput.value = '';
        if (state.objectUrl) { try { URL.revokeObjectURL(state.objectUrl); } catch (e) {} }
        state.objectUrl = null;
        state.photo = null;
        state.submitting = false;
        resetErrors();
        renderPhotoPreview();
    }

    function openAdd() {
        resetForm();
        state.mode = 'add';
        state.id = null;
        titleEl.textContent = 'Add Team Member';
        subEl.textContent = 'Create a new team account and assign their role.';
        photoHint.textContent = 'JPG, JPEG, PNG or WEBP — up to 2 MB.';
        saveLabel.textContent = 'Save Team Member';
        saveBtn.disabled = false;
        cancelBtn.disabled = false;
        openRequested = true;
        modal.show();
        setTimeout(function () { firstName.focus(); }, 200);
    }

    function openEdit(id) {
        var m = findMember(id);
        if (!m) return;
        resetForm();
        state.mode = 'edit';
        state.id = id;
        state.photo = m.photo || null;
        firstName.value = m.first;
        lastName.value = m.last;
        email.value = m.email;
        contact.value = m.contact_no || '';
        position.value = m.position || '';
        role.value = m.role;
        titleEl.textContent = 'Edit Team Member';
        subEl.textContent = 'Update the details and access for ' + m.name + '.';
        photoHint.textContent = m.photo ? 'Current photo is shown. Upload a new one to replace it.' : 'JPG, JPEG, PNG or WEBP — up to 2 MB.';
        saveLabel.textContent = 'Save Changes';
        saveBtn.disabled = false;
        cancelBtn.disabled = false;
        renderPhotoPreview();
        openRequested = true;
        modal.show();
        setTimeout(function () { firstName.focus(); }, 200);
    }

    function validate() {
        resetErrors();
        var ok = true;
        var f = firstName.value.trim();
        var l = lastName.value.trim();
        if (!f) { setFieldError('first_name', "Please enter the member's first name."); ok = false; }
        if (!l) { setFieldError('last_name', "Please enter the member's last name."); ok = false; }
        var em = email.value.trim();
        if (!em) { setFieldError('email', 'Please enter an email address.'); ok = false; }
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { setFieldError('email', 'Please enter a valid email address.'); ok = false; }
        if (!role.value) { setFieldError('role', 'Please choose a role for this member.'); ok = false; }
        var c = contact.value.trim();
        if (c && !/^(?:\+63|0)[\d\s\-()]{7,17}$/.test(c)) { setFieldError('contact_no', 'Enter a valid Philippine mobile number, e.g. 0917 123 4567.'); ok = false; }
        if (position.value.trim().length > 100) { setFieldError('position', 'Position can be up to 100 characters.'); ok = false; }
        var pf = photoInput.files && photoInput.files[0];
        if (pf) {
            var types = ['image/jpeg', 'image/png', 'image/webp'];
            if (types.indexOf(pf.type) === -1 || pf.size > 2 * 1024 * 1024) {
                setFieldError('profile_image', 'Photo must be JPG, JPEG, PNG or WEBP and up to 2 MB.');
                ok = false;
            }
        }
        return ok;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (state.submitting) return;
        if (!validate()) return;

        state.submitting = true;
        saveBtn.disabled = true;
        cancelBtn.disabled = true;
        saveLabel.textContent = 'Saving...';

        var fd = new FormData();
        var f = firstName.value.trim();
        var l = lastName.value.trim();
        fd.append('first_name', f);
        fd.append('last_name', l);
        fd.append('name', (f + ' ' + l).replace(/\s+/g, ' ').trim());
        fd.append('email', email.value.trim());
        fd.append('role', role.value);
        if (position.value.trim()) fd.append('position', position.value.trim());
        if (contact.value.trim()) fd.append('contact_no', contact.value.trim());
        if (photoInput.files && photoInput.files[0]) fd.append('profile_image', photoInput.files[0]);

        var method = 'POST';
        if (state.mode === 'edit') fd.append('_method', 'PUT');
        var url = state.mode === 'edit'
            ? modalEl.getAttribute('data-update-base') + '/' + state.id
            : modalEl.getAttribute('data-store-url');

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd
        }).then(function (res) {
            if (res.status === 422) {
                return res.json().then(function (body) {
                    var errs = body && body.errors ? body.errors : {};
                    Object.keys(errs).forEach(function (k) {
                        var msgs = errs[k];
                        setFieldError(k === 'name' ? 'first_name' : k, msgs && msgs[0] ? msgs[0] : 'Please check this field.');
                    });
                    restoreButtons();
                });
            }
            if (res.status === 419 || res.status === 401) { window.location.reload(); return; }
            /* Server replies with a 302 redirect carrying a `status` flash.
               With `redirect: 'manual'` the fetch resolves +opaque (status 0);
               reload once so the flashed success alert renders on the page. */
            if (res.status === 0 || (res.status >= 200 && res.status < 300)) {
                window.location.reload();
                return;
            }
            restoreButtons();
            setFieldError('profile_image', 'Something went wrong while saving. Please try again.');
        }).catch(function () {
            restoreButtons();
            setFieldError('profile_image', 'Something went wrong while saving. Please try again.');
        });
    });

    var addBtn = document.getElementById('addTeamBtn');
    if (addBtn) addBtn.addEventListener('click', openAdd);

    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-team-edit]') : null;
        if (el) openEdit(Number(el.getAttribute('data-team-edit')));
    });

    var closeEls = form.querySelectorAll('[data-team-cancel]');
    for (var i = 0; i < closeEls.length; i++) {
        closeEls[i].addEventListener('click', function () { modal.hide(); });
    }
    var closeBtn = modalEl.querySelector('.team-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { modal.hide(); });

    modalEl.addEventListener('hidden.bs.modal', function () {
        /* A hide() is async; its `hidden` event can fire after the modal has
           already been reopened (Close -> Edit fast sequence). When a new open
           was requested, skip the reset so the fresh prefill survives. */
        if (openRequested) { openRequested = false; return; }
        resetForm();
    });

    var liveFields = [
        { el: firstName, name: 'first_name' },
        { el: lastName, name: 'last_name' },
        { el: email, name: 'email' },
        { el: contact, name: 'contact_no' },
        { el: position, name: 'position' },
        { el: role, name: 'role' }
    ];
    for (var j = 0; j < liveFields.length; j++) {
        (function (el, name) {
            el.addEventListener('input', function () { setFieldError(name, ''); if (name === 'first_name' || name === 'last_name') setInitialsPreview(); });
            el.addEventListener('change', function () { setFieldError(name, ''); });
        })(liveFields[j].el, liveFields[j].name);
    }

    photoInput.addEventListener('change', function () {
        var pf = photoInput.files && photoInput.files[0];
        if (state.objectUrl) { try { URL.revokeObjectURL(state.objectUrl); } catch (e) {} state.objectUrl = null; }
        if (pf) {
            var types = ['image/jpeg', 'image/png', 'image/webp'];
            if (types.indexOf(pf.type) !== -1 && pf.size <= 2 * 1024 * 1024) {
                state.objectUrl = URL.createObjectURL(pf);
                setFieldError('profile_image', '');
            }
        }
        renderPhotoPreview();
    });

    var initial = modalEl.getAttribute('data-initial-edit');
    if (initial) openEdit(Number(initial));
    }

    /* The app loads bootstrap.bundle.js with `defer`, so it runs only after the
       document has been parsed. This inline block executes during parsing, so
       wait for DOMContentLoaded (a.k.a. deferred scripts) before wiring the
       modal; fall back to `load` if bootstrap is somehow still not available. */
    function boot() {
      if (typeof bootstrap === 'undefined') { window.addEventListener('load', boot); return; }
      teamModalInit();
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
})();
</script>
@endpush