{{--
    Shared field set for creating an Admin Concern.

    Used by BOTH entry points so they can never drift apart:
      * the dedicated "Create Kaizen Concern" page  (admin.kaizen-concerns.create)
      * the Create Concern modal on the Admin Concerns board

    Nothing was removed. Every field keeps the exact same name, type, required
    flag, maxlength, options and @error binding as the original form, so
    store() and its validation rules are untouched — only the presentation is
    compacted (tighter labels, tighter gaps, a two-column row for the short
    controls, and shorter textareas that the browser can still resize).

    Requires from the caller: $staffAccounts, $statuses.
--}}

<div class="kaizen-form-compact">
    {{-- 1. The concern itself, first and full width. --}}
    <div class="form-group">
        <label class="form-label" for="challenge">Challenge / Opportunity for Improvement <span class="text-danger">*</span></label>
        <textarea class="form-control" id="challenge" name="challenge" rows="3" maxlength="5000" required placeholder="Describe the challenge or opportunity for improvement in detail...">{{ old('challenge') }}</textarea>
        @error('challenge')<div class="form-error">{{ $message }}</div>@enderror
        <div class="form-hint">Be specific about the problem, its impact, and context.</div>
    </div>

    <div class="form-group">
        <label class="form-label" for="recommended_solution">Recommended Solution <span class="text-danger">*</span></label>
        <textarea class="form-control" id="recommended_solution" name="recommended_solution" rows="3" maxlength="5000" required placeholder="Describe the recommended solution and implementation approach...">{{ old('recommended_solution') }}</textarea>
        @error('recommended_solution')<div class="form-error">{{ $message }}</div>@enderror
        <div class="form-hint">Include steps, resources needed, and expected outcome.</div>
    </div>

    {{-- 2 & 3 + the remaining required fields: the short controls sit two per
         row so the form is two rows tall instead of four. Still 1fr 1fr at every
         breakpoint so nothing is squeezed too narrow to use. --}}
    <div class="form-grid two kz-short-grid">
        <div class="form-group">
            <label class="form-label" for="assigned_staff_id">Assigned To</label>
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
            <label class="form-label" for="date_identified">Date Identified</label>
            <input class="form-control" id="date_identified" name="date_identified" type="date" value="{{ old('date_identified', now()->format('Y-m-d')) }}" required>
            @error('date_identified')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
            <select class="form-control" id="status" name="status" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="form-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000" placeholder="Additional notes, context, or references...">{{ old('notes') }}</textarea>
        @error('notes')<div class="form-error">{{ $message }}</div>@enderror
    </div>
</div>
