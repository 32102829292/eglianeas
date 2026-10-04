@extends('layouts.dashboard')

@section('title', 'Create Kaizen Concern — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Create Kaizen Concern</h1>
        <p>Add a new workplace challenge or opportunity for improvement.</p>
    </div>

    <div class="card card-narrow">
        <form method="POST" action="{{ route('admin.kaizen-concerns.store') }}">
            @csrf
            <div class="form-grid two">
                <div class="form-group">
                    <label class="form-label" for="date_identified">Date Identified</label>
                    <input class="form-control" id="date_identified" name="date_identified" type="date" value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
                    @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
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
                    <label class="form-label" for="target_date">Target Date</label>
                    <input class="form-control" id="target_date" name="target_date" type="date" value="{{ old('target_date') }}">
                    @error('target_date')<div class="form-error">{{ $message }}</div>@enderror
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
                <label class="form-label" for="challenge">Challenge / Opportunity for Improvement <span class="text-danger">*</span></label>
                <textarea class="form-control" id="challenge" name="challenge" rows="4" maxlength="5000" required placeholder="Describe the challenge or opportunity for improvement in detail...">{{ old('challenge') }}</textarea>
                @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
                <div class="form-hint">Be specific about the problem, its impact, and context.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="recommended_solution">Recommended Solution <span class="text-danger">*</span></label>
                <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="4" maxlength="5000" required placeholder="Describe the recommended solution and implementation approach...">{{ old('recommended_solution') }}</textarea>
                @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
                <div class="form-hint">Include steps, resources needed, and expected outcome.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Additional notes, context, or references...">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Create Concern</button>
                <a href="{{ route('admin.kaizen-concerns.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection