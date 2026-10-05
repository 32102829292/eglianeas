@extends('layouts.dashboard')

@section('title', 'Edit Kaizen Concern — Egliane Accounting Services')

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>Edit Employee Suggestion</h1>
            <p>Update the suggestion, assignment, target date, and implementation outcome.</p>
        </div>
        <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="vertical-align:-2px"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.kaizen-concerns.update', $concern) }}" novalidate>
            @csrf
            @method('PUT')

            <div>

                {{-- ---------------- Section 1 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">1</span> Concern Details</span>
                    <div class="form-hint">When it was identified, when it should be resolved, and where it stands.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="date_identified">Date Identified <span class="text-danger">*</span></label>
                            <input class="form-control" id="date_identified" name="date_identified" type="date"
                                   value="{{ old('date_identified', $concern->date_identified?->format('Y-m-d')) }}" required>
                            @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="target_date">Target Date</label>
                            <input class="form-control" id="target_date" name="target_date" type="date"
                                   value="{{ old('target_date', $concern->target_date?->format('Y-m-d')) }}">
                            @error('target_date')<div class="form-error">{{ $message }}</div>@enderror
                            <div class="form-hint">The date this concern is expected to be resolved.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-control" id="status" name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $concern->effectiveStatus()) === $value)>
                                        {{ $value === \App\Models\KaizenConcern::STATUS_IMPLEMENTED ? '✓ '.$label : $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')<div class="form-error">{{ $message }}</div>@enderror
                            <div class="form-hint">The status is worked out from the implementation itself, so you do not have to pick it: leaving this alone keeps the suggestion where the implementation workflow put it. Choosing Implemented finalises the suggestion and fills in the implementation date when one is not set.</div>
                        </div>
                    </div>
                </section>

                {{-- ---------------- Section 2 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">2</span> Assignment</span>
                    <div class="form-hint">Who owns this concern right now.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                            <select class="form-control" id="assigned_staff_id" name="assigned_staff_id">
                                <option value="">— Unassigned —</option>
                                @foreach ($staffAccounts as $staff)
                                    <option value="{{ $staff->id }}"
                                        @selected((string) old('assigned_staff_id', $concern->assigned_staff_id) === (string) $staff->id)>{{ $staff->name }}</option>
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
                    <span class="form-section-title"><span class="badge badge-info">3</span> Improvement</span>
                    <div class="form-hint">What is going wrong, and what to do about it.</div>

                    <div class="form-group">
                        <label class="form-label" for="challenge">Challenge / Opportunity for Improvement <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="challenge" name="challenge" rows="4" maxlength="5000" required
                                  placeholder="Describe the challenge or opportunity for improvement in detail...">{{ old('challenge', $concern->challenge) }}</textarea>
                        @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="recommended_solution">Recommended Solution <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="4" maxlength="5000" required
                                  placeholder="Describe the recommended solution and implementation approach...">{{ old('recommended_solution', $concern->recommended_solution) }}</textarea>
                        @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </section>

                {{-- ---------------- Section 4 ---------------- --}}
                <section class="form-section">
                    <span class="form-section-title"><span class="badge badge-info">4</span> Implementation</span>
                    <div class="form-hint">Progress record and supporting notes.</div>

                    <div class="form-grid two">
                        <div class="form-group">
                            <label class="form-label" for="implementation_date">Implementation Date</label>
                            <input class="form-control" id="implementation_date" name="implementation_date" type="date"
                                   value="{{ old('implementation_date', $concern->implementation_date?->format('Y-m-d')) }}">
                            @error('implementation_date')<div class="form-error">{{ $message }}</div>@enderror
                            <div class="form-hint">Shown as "Implemented on" once the suggestion is marked Implemented.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000"
                                  placeholder="Additional notes, context, or references...">{{ old('notes', $concern->notes) }}</textarea>
                        @error('notes')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.kaizen-concerns.show', $concern) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
