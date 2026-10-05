{{--
    The Remarks cell shared by the weekly, monthly and quarterly trackers.

    Remarks reuse the existing `notes` column on every target table. This partial
    only decides how that text is presented and whether the editor is offered.

    The rule below is the single wording of the remarks workflow, and it mirrors
    the gate in the controllers' target-update endpoints:

      - a completed task is read-only for everybody
      - while the task is In Progress the assigned staff member may add and edit
        remarks, even after the actual work has been recorded
      - before the work starts, whoever may edit the task at all may set a remark
      - any other staff member sees the remark and no editor

    Parameters:
      $target  the bookkeeping target
--}}
@php
    $user = auth()->user();
    $isOversight = $user->isAdmin() || $user->isSupervisor();

    $canEditRemarks = ! $target->isCompleted()
        && ($isOversight
            || ($target->isAssignedTo($user) && ($target->isPending() || $target->isInProgress())));

    $hasRemarks = filled($target->notes);
@endphp
<div class="bk-remarks">
    @if ($hasRemarks)
        <div class="bk-remarks-text">{{ $target->notes }}</div>
        @if ($canEditRemarks)
            <button type="button" class="btn btn-outline btn-sm bk-remarks-edit" data-bs-toggle="modal"
                    data-bs-target="#bkRemarksModal"
                    data-bk-remarks-for="{{ $target->id }}"
                    data-bk-remarks-value="{{ $target->notes }}"
                    title="Edit remarks">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
        @endif
    @else
        @if ($canEditRemarks)
            <button type="button" class="btn btn-primary btn-sm bk-remarks-add" data-bs-toggle="modal"
                    data-bs-target="#bkRemarksModal"
                    data-bk-remarks-for="{{ $target->id }}"
                    data-bk-remarks-value=""
                    title="Add remarks">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Remarks
            </button>
        @else
            <span class="bk-remarks-none">No remarks</span>
        @endif
    @endif
</div>
