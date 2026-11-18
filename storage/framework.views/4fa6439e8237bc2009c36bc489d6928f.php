<?php ($menuId = 'coll-more-' . $menuType . '-' . $billing->id); ?>
<div class="coll-actions">
    <a href="<?php echo e(route('admin.billing.receipt', $billing)); ?>" class="btn btn-outline btn-sm">View receipt</a>
    <?php if(auth()->user()->isAdmin()): ?>
        <form method="POST" action="<?php echo e(route('admin.billing.pay', $billing)); ?>" class="coll-pay-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="status" value="paid">
            <label class="visually-hidden" for="paid-<?php echo e($menuId); ?>">Date paid</label>
            <div class="coll-pay-row">
                <input class="form-control" id="paid-<?php echo e($menuId); ?>" type="date" name="paid_at" value="<?php echo e(old('paid_at', now()->format('Y-m-d'))); ?>" aria-label="Date paid">
                <button type="submit" class="btn btn-primary btn-sm">Mark paid</button>
            </div>
        </form>
    <?php endif; ?>
    <div class="dropdown-wrap">
        <button type="button" class="more-btn"
                id="<?php echo e($menuId); ?>-btn"
                data-dropdown="<?php echo e($menuId); ?>"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="<?php echo e($menuId); ?>"
                aria-label="More actions"
                title="More actions">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg>
            <span class="more-btn-label">More</span>
        </button>
        <div class="dropdown-menu more-menu" id="<?php echo e($menuId); ?>" role="menu" aria-labelledby="<?php echo e($menuId); ?>-btn">
            <form method="POST" action="<?php echo e(route('admin.collections.remind', $billing)); ?>" role="none" data-submit-label="Sending…">
                <?php echo csrf_field(); ?>
                <button type="submit" class="dropdown-item btn-item" role="menuitem">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Send reminder
                </button>
            </form>
        </div>
    </div>
</div><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/collections/_row-actions.blade.php ENDPATH**/ ?>