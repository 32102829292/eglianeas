@extends('layouts.dashboard')

@section('title', 'Edit Priority Item — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Edit Priority Item</h1>
            <p>Update the task, lesson, priority, assignment, deadline, and progress.</p>
        </div>
        <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="vertical-align:-2px"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.priority-items.update', $item) }}" novalidate data-priority-due-form>
            @csrf
            @method('PUT')

            <div>

                {{-- ---------------- Section 1 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">1</span> Task</span>
                    <div class="form-hint">What needs doing, and how it is categorised.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="task_lesson">Task / Lesson <span class="text-danger">*</span></label>
                            <input class="form-control" id="task_lesson" name="task_lesson" type="text" maxlength="500" required
                                   placeholder="Brief title of the task or lesson..."
                                   value="{{ old('task_lesson', $item->task_lesson) }}">
                            @error('task_lesson')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="type" name="type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $item->type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                {{-- ---------------- Section 2 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">2</span> Priority &amp; Assignment</span>
                    <div class="form-hint">How urgent it is, and who owns it.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
                            <select class="form-control" id="priority" name="priority" required data-priority-select>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', $item->priority) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('priority')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                            <select class="form-control" id="assigned_staff_id" name="assigned_staff_id">
                                <option value="">— Unassigned —</option>
                                @foreach ($staffAccounts as $staff)
                                    <option value="{{ $staff->id }}"
                                        @selected((string) old('assigned_staff_id', $item->assigned_staff_id) === (string) $staff->id)>{{ $staff->name }}</option>
                                @endforeach
                            </select>
                            @error('assigned_staff_id')<div class="form-error">{{ $message }}</div>@enderror
                            <div class="form-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                <span>Unassigned — visible to all staff &amp; supervisors. Assign a staff member to notify only that person.</span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ---------------- Section 3 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">3</span> Schedule &amp; Status</span>
                    <div class="form-hint">When it is due and how far along it is.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="due_date">Due Date</label>
                            <input class="form-control" id="due_date" name="due_date" type="date"
                                   value="{{ old('due_date', $item->due_date?->format('Y-m-d')) }}" data-due-date-input>
                            <div class="form-hint">Leave blank to keep the current deadline. A new value only applies when you save.</div>
                            @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                {{-- ---------------- Section 4 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">4</span> Details</span>
                    <div class="form-hint">Context for whoever picks this up.</div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" maxlength="5000"
                                  placeholder="Detailed description...">{{ old('description', $item->description) }}</textarea>
                        @error('description')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000"
                                  placeholder="Additional notes...">{{ old('notes', $item->notes) }}</textarea>
                        @error('notes')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.priority-items.show', $item) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

    @include('admin.priority-items._due-date-script')
@endsection
