@if ($item->canAssignStaff(auth()->user()))
    <form method="POST" action="{{ route('admin.priority-items.assign-staff', $item) }}" class="inline-assign-form">
        @csrf
        <select class="form-control form-control-sm" name="assigned_staff_id" aria-label="Assign staff to this Priority item">
            <option value="">&mdash; Clear assignment &mdash;</option>
            @foreach ($staffAccounts as $staff)
                <option value="{{ $staff->id }}" @selected((int) $item->assigned_staff_id === (int) $staff->id)>{{ $staff->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
@endif
