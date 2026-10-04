@extends('layouts.dashboard')

@section('title', 'Create Priority Item — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Create Priority Item</h1>
        <p>Add a new priority task, to-do, or lesson learned.</p>
    </div>

    <div class="card card-narrow">
        <form method="POST" action="{{ route('admin.priority-items.store') }}" data-priority-due-form>
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="task_lesson">Task / Lesson <span class="text-danger">*</span></label>
                    <input class="form-control" id="task_lesson" name="task_lesson" type="text" maxlength="500" required placeholder="Brief title of the task or lesson..." value="{{ old('task_lesson') }}">
                    @error('task_lesson')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="">Select type...</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
                    <select class="form-control" id="priority" name="priority" required data-priority-select>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                    <select class="form-control" id="assigned_staff_id" name="assigned_staff_id">
                        <option value="">— Unassigned (visible to all staff & supervisors) —</option>
                        @foreach ($staffAccounts as $staff)
                            <option value="{{ $staff->id }}" @selected(old('assigned_staff_id') == $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_staff_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="due_date">Due Date</label>
                    <input class="form-control" id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" data-due-date-input>
                    <div class="form-hint">Leave blank to use the priority default (Urgent: today, High: tomorrow, Medium: 3 days, Low: 1 week).</div>
                    @error('due_date')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" maxlength="5000" placeholder="Detailed description of the task, to-do, or lesson learned...">{{ old('description') }}</textarea>
                @error('description')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Additional notes...">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Create Item</button>
                <a href="{{ route('admin.priority-items.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

    @include('admin.priority-items._due-date-script')
@endsection