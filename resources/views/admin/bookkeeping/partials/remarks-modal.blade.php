{{--
    The remarks editor shared by the weekly, monthly and quarterly trackers.

    It posts a single `notes` field to the module's own target-update endpoint, so
    saving a remark can never move a target date, touch a balance, change an
    assignment or close a task. The endpoint re-checks permission server-side.

    Parameters:
      $updateRoute   the module target-update route name
      $bookkeeping   the plan the targets belong to
--}}
<div class="modal fade" id="bkRemarksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="bkRemarksForm" method="POST" action="{{ route($updateRoute, [$bookkeeping, '__BK_TARGET_ID__']) }}">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Task Remarks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="bk-remarks-target mb-3" id="bkRemarksTargetLabel"></div>
                    <div class="form-group">
                        <label class="form-label" for="bkRemarksTextarea">Remarks</label>
                        <textarea id="bkRemarksTextarea" name="notes" class="form-control" rows="4" maxlength="1000"
                                  placeholder="What happened during processing? Note anything the next person needs to know."></textarea>
                        <small class="form-text">Saved with this task and visible to your supervisors. 1000 characters max.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Remarks</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var modalEl = document.getElementById('bkRemarksModal');
    if (! modalEl || typeof bootstrap === 'undefined') { return; }

    var form = document.getElementById('bkRemarksForm');
    var textarea = document.getElementById('bkRemarksTextarea');
    var label = document.getElementById('bkRemarksTargetLabel');
    var placeholderAction = form.action;

    document.querySelectorAll('[data-bk-remarks-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            textarea.value = button.getAttribute('data-bk-remarks-value') || '';
            form.action = placeholderAction.replace('__BK_TARGET_ID__', button.getAttribute('data-bk-remarks-for'));
            if (label) {
                var row = button.closest('tr');
                var titleCell = row ? row.querySelector('[data-bk-task-title]') : null;
                label.textContent = titleCell ? titleCell.textContent.trim() : '';
            }
        });
    });

    form.addEventListener('submit', function () {
        var submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    });

    /* Returning focus to the button that opened the editor keeps the keyboard
       where the reader left it. */
    var opener = null;
    modalEl.addEventListener('show.bs.modal', function (event) {
        opener = event.relatedTarget;
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
        form.reset();
        if (opener && typeof opener.focus === 'function') { opener.focus(); }
        opener = null;
    });
})();
</script>
@endpush
