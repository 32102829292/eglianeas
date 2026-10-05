@extends('layouts.dashboard')

@section('title', 'Kaizen Strategy — Employee Improvement Board — Egliane Accounting Services')

@section('content')
    @php
        $isStaffView = auth()->user()->isStaff();
        $isOperational = auth()->user()->isOperational();
    @endphp
    <div class="page-head page-head-row">
        <div>
            <h1>Kaizen Strategy</h1>
            <p>Employee Improvement Board — Track suggestions, progress, and implemented improvements.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($isOperational)
                <a href="{{ route('admin.kaizen-concerns.submit') }}" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="margin-right: 6px; vertical-align: -3px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Submit Improvement
                </a>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="#create-concern-form" class="btn btn-outline" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="create-concern-form">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right: 6px; vertical-align: -2px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Admin: Create Concern
                </a>
            @endif
        </div>
    </div>

    {{-- Admin create concern form (collapsible) --}}
    @if (auth()->user()->isAdmin())
    <div class="card collapse" id="create-concern-form">
        <div class="card-head">
            <h2 class="card-title">Create Kaizen Concern (Admin)</h2>
        </div>
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
                <label class="form-label" for="challenge">Challenge / Opportunity for Improvement</label>
                <textarea class="form-control" id="challenge" name="challenge" rows="3" maxlength="5000" required placeholder="Describe the challenge or opportunity...">{{ old('challenge') }}</textarea>
                @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="recommended_solution">Recommended Solution</label>
                <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="3" maxlength="5000" required placeholder="Describe the recommended solution...">{{ old('recommended_solution') }}</textarea>
                @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000" placeholder="Additional notes...">{{ old('notes') }}</textarea>
                @error('notes')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Create Concern</button>
        </form>
    </div>
    @endif

    {{-- The board itself is shared with the Admin Concerns board; only the type
         it selects and the presentation labels differ. --}}
    @include('admin.kaizen-concerns.partials.board', [
        'boardRoute' => 'admin.kaizen-concerns.index',
        'recordLabel' => 'Employee Suggestion',
        'boardHeading' => 'Improvement Suggestions',
        'pluralUnit' => 'suggestion',
        'showAssigned' => false,
        'emptyMessage' => 'No improvement suggestions yet. Use "Submit Improvement" to add the first one.',
        'filteredEmpty' => 'No suggestions match your current filters.',
    ])
@endsection
