@extends('layouts.dashboard')

@section('title', 'Edit Priority Item — Egliane Accounting Services')

@section('content')
    <header class="eas-page-head">
        <div class="eas-head-main">
            <div class="eas-head-left">
                <span class="eas-eyebrow">Priority List</span>
                <h1 class="eas-head-title">Edit Priority Item</h1>
                <p class="eas-head-sub">Update the task, priority, assignment, deadline, and progress.</p>
            </div>
        </div>
        <div class="eas-head-actions">
            <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Item
            </a>
        </div>
    </header>

    <div class="eas-priority-create-wrap">
        <div class="eas-priority-create-card">
            <form method="POST" action="{{ route('admin.priority-items.update', $item) }}" novalidate data-priority-due-form>
                @csrf
                @method('PUT')

                <div class="eas-form-grid eas-form-grid-two">
                    <div class="eas-form-group">
                        <label class="eas-form-label" for="task_lesson">Task / Lesson <span class="text-danger">*</span></label>
                        <input class="eas-form-control" id="task_lesson" name="task_lesson" type="text" maxlength="500" required
                               placeholder="Brief title of the task or lesson..."
                               value="{{ old('task_lesson', $item->task_lesson) }}">
                        @error('task_lesson')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="eas-form-group">
                        <label class="eas-form-label" for="type">Type <span class="text-danger">*</span></label>
                        <select class="eas-form-control" id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $item->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="eas-form-group">
                        <label class="eas-form-label" for="priority">Priority <span class="text-danger">*</span></label>
                        <select class="eas-form-control" id="priority" name="priority" required data-priority-select>
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $item->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="eas-form-group">
                        <label class="eas-form-label" for="assigned_staff_id">Assigned Staff</label>
                        <select class="eas-form-control" id="assigned_staff_id" name="assigned_staff_id">
                            <option value="">— Unassigned —</option>
                            @foreach ($staffAccounts as $staff)
                                <option value="{{ $staff->id }}"
                                    @selected((string) old('assigned_staff_id', $item->assigned_staff_id) === (string) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_staff_id')<div class="eas-form-error">{{ $message }}</div>@enderror
                        <div class="eas-form-hint">Unassigned — visible to all staff &amp; supervisors. Assign a staff member to notify only that person.</div>
                    </div>

                    <div class="eas-form-group">
                        <label class="eas-form-label" for="due_date">Due Date</label>
                        <input class="eas-form-control" id="due_date" name="due_date" type="date"
                               value="{{ old('due_date', $item->due_date?->format('Y-m-d')) }}" data-due-date-input>
                        <div class="eas-form-hint">Leave blank to keep the current deadline. A new value only applies when you save.</div>
                        @error('due_date')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="eas-form-group">
                        <label class="eas-form-label" for="status">Status <span class="text-danger">*</span></label>
                        <select class="eas-form-control" id="status" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="eas-form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="eas-form-group eas-form-full">
                    <label class="eas-form-label" for="description">Description</label>
                    <textarea class="eas-form-control" id="description" name="description" rows="4" maxlength="5000"
                              placeholder="Detailed description...">{{ old('description', $item->description) }}</textarea>
                    @error('description')<div class="eas-form-error">{{ $message }}</div>@enderror
                </div>

                <div class="eas-form-group eas-form-full">
                    <label class="eas-form-label" for="notes">Notes</label>
                    <textarea class="eas-form-control" id="notes" name="notes" rows="3" maxlength="2000"
                              placeholder="Additional notes...">{{ old('notes', $item->notes) }}</textarea>
                    @error('notes')<div class="eas-form-error">{{ $message }}</div>@enderror
                </div>

                <div class="eas-form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @include('admin.priority-items._due-date-script')

    @push('styles')
    <style>
    /* ============================================================
       Priority List - Edit Page
       ============================================================ */

    .eas-priority-create-wrap {
        display: flex;
        justify-content: center;
        padding: 24px 0;
    }

    .eas-priority-create-card {
        max-width: 1050px;
        width: 100%;
    }

    /* ---------- Form controls (scoped to this page) ---------- */
    .eas-form-grid {
        display: grid;
        gap: 16px;
    }

    .eas-form-grid-two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .eas-form-full {
        grid-column: 1 / -1;
    }

    .eas-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .eas-form-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--muted-text);
    }

    .eas-form-control {
        width: 100%;
        min-height: 42px;
        box-sizing: border-box;
        border: 1px solid #D6DDE6;
        border-radius: 10px;
        padding: 9px 14px;
        font-family: var(--font-body);
        font-size: 14px;
        line-height: 1.35;
        color: var(--text);
        background: #fff;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .eas-form-control:focus {
        border-color: var(--sky);
        box-shadow: 0 0 0 3px rgba(90, 179, 240, 0.18);
    }

    .eas-form-control::placeholder {
        color: #9AA4B2;
    }

    textarea.eas-form-control {
        min-height: 130px;
        resize: vertical;
    }

    .eas-form-hint {
        font-size: 12px;
        color: var(--muted-text);
        margin-top: 3px;
    }

    .eas-form-error {
        font-size: 12px;
        color: #dc2626;
        margin-top: 4px;
    }

    .eas-form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        padding-top: 18px;
        border-top: 1px solid var(--border-subtle);
    }

    /* ---------- Page Header ---------- */
    .eas-page-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 28px;
        padding-bottom: 22px;
        border-bottom: 1px solid var(--border-subtle);
    }

    .eas-head-main {
        display: flex;
        align-items: flex-start;
        gap: 24px;
        flex-wrap: wrap;
    }

    .eas-head-left {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .eas-eyebrow {
        margin: 0;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted-text);
    }

    .eas-head-title {
        margin: 0 0 6px;
        font-family: var(--font-head);
        font-size: 27px;
        font-weight: 700;
        color: var(--navy);
        letter-spacing: -0.02em;
    }

    .eas-head-sub {
        margin: 0;
        font-size: 14.5px;
        color: var(--muted-text);
        line-height: 1.5;
    }

    .eas-head-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .eas-head-actions .btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .eas-head-actions .btn svg {
        width: 17px;
        height: 17px;
        flex: none;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 640px) {
        .eas-page-head {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .eas-head-main {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .eas-page-head h1 {
            font-size: 23px;
        }

        .eas-form-grid-two {
            grid-template-columns: 1fr;
        }

        .eas-form-actions {
            flex-direction: column;
        }

        .eas-form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
    @endpush
@endsection