<?php $__env->startSection('title', 'Edit Billing Statement — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <a href="<?php echo e(route('admin.billing.index')); ?>" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head">
        <h1>Edit billing statement</h1>
        <p><?php echo e($billing->period_label); ?></p>
    </div>

    <?php if(! $billing->isDraft()): ?>
        <div class="alert-strip">
            This billing has already been finalized — changes made now may not match what the client has seen.
        </div>
    <?php endif; ?>

    <?php echo $__env->make('admin.billing._form', ['formMode' => 'edit'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/edit.blade.php ENDPATH**/ ?>