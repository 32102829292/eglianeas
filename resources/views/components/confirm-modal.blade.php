{{-- Reusable branded confirmation modal (Bootstrap 5.3.3, singleton).
    Markup is boosted by /js/confirm.js; pages use the `window.egliane.confirm`
    helper (form(), button(), action()) instead of native confirm(). --}}

<style>
    /* app.css defines a custom `.modal` overlay (flex, always visible) that would
       override Bootstrap's base modal. Restore correct Bootstrap modal behaviour for
       this component only; Bootstrap toggles it via inline `display:block` on show. */
    .confirm-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1055;
        overflow-x: hidden;
        overflow-y: auto;
        outline: 0;
        padding: 0;
        margin: 0;
        background: transparent;
        align-items: normal;
        justify-content: normal;
    }

    .confirm-modal .modal-dialog {
        margin: 1.75rem auto;
        max-width: 400px;
    }

    .confirm-modal .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(27, 27, 58, 0.28);
        overflow: hidden;
    }

    .confirm-modal .modal-body {
        text-align: center;
        padding: 34px 28px 6px;
    }

    .confirm-modal .confirm-modal-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(90, 179, 240, 0.12);
        color: #1B1B3A;
    }

    .confirm-modal.confirm-modal-danger .confirm-modal-icon {
        background: rgba(231, 76, 60, 0.12);
        color: #E74C3C;
    }

    .confirm-modal .confirm-modal-title {
        font-family: var(--font-head, 'Space Grotesk', sans-serif);
        font-weight: 700;
        font-size: 1.15rem;
        color: #1B1B3A;
        margin: 0;
    }

    .confirm-modal .confirm-modal-message {
        color: #6b7280;
        font-size: 0.92rem;
        line-height: 1.5;
        margin: 10px 0 0;
    }

    .confirm-modal .modal-footer {
        justify-content: center;
        border: none;
        gap: 10px;
        padding: 18px 22px 24px;
    }

    .confirm-modal .modal-footer .btn {
        min-width: 116px;
        border-radius: 10px;
        font-weight: 600;
    }

    .confirm-modal .confirm-modal-close {
        position: absolute;
        top: 14px;
        right: 14px;
        opacity: 0.55;
        border-radius: 50%;
        z-index: 10;
    }
    .confirm-modal .confirm-modal-close:hover { opacity: 1; }

    @media (max-width: 480px) {
        .confirm-modal .modal-dialog { margin: 0.5rem auto; }
        .confirm-modal .modal-content { border-radius: 14px; }
        .confirm-modal .modal-body { padding: 28px 20px 4px; }
        .confirm-modal .modal-footer { padding: 14px 16px 20px; }
        .confirm-modal .modal-footer .btn { min-width: 104px; }
    }
</style>

<div class="modal fade confirm-modal" id="eglianeConfirmModal" tabindex="-1" role="dialog"
     aria-labelledby="eglianeConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close confirm-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body">
                <div class="confirm-modal-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h5 class="confirm-modal-title" id="eglianeConfirmTitle">Are you sure?</h5>
                <p class="confirm-modal-message" id="eglianeConfirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="eglianeConfirmCancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="eglianeConfirmOk">Confirm</button>
            </div>
        </div>
    </div>
</div>