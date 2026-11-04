@if ($concern->canAssignStaff(auth()->user()))
    <form method="POST" action="{{ route('admin.kaizen-concerns.assign-staff', $concern) }}" class="inline-assign-form">
        @csrf
        <select class="form-control form-control-sm" name="assigned_staff_id" aria-label="Assign staff to this Kaizen concern">
            <option value="">&mdash; Clear assignment &mdash;</option>
            @foreach ($staffAccounts as $staff)
                <option value="{{ $staff->id }}" @selected((int) $concern->assigned_staff_id === (int) $staff->id)>{{ $staff->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
@endif
