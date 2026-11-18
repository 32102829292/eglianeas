<?php $__env->startSection('title', 'Other Services — Billing Statements — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>Other Services &mdash; Billing Statements</h1>
            <p>One-off service requests outside the quarterly billing cycle.</p>
        </div>
        <div class="page-head-actions">
            <a href="<?php echo e(route('admin.other-services.settings')); ?>" class="btn btn-outline btn-sm">Manage service types</a>
            <a href="<?php echo e(route('admin.other-services.fill-up')); ?>" class="btn btn-primary">New service request</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['total'], 2)); ?></b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['paid'], 2)); ?></b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['outstanding'], 2)); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <span class="stat-label">Total requests</span>
            <b class="stat-value"><?php echo e($stats['count']); ?></b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="<?php echo e(route('admin.other-services.billing')); ?>">
            <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search business or contact&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Business</th>
                        <th>Service</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Status</th>
                        <th>Requested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-col="Business">
                                <div class="fw-semibold"><?php echo e($service->client?->business_name ?: $service->client?->name); ?></div>
                                <small class="muted"><?php echo e($service->client?->name); ?></small>
                            </td>
                            <td data-col="Service">
                                <div class="fw-semibold"><?php echo e($service->serviceName()); ?></div>
                                <?php if($service->notes): ?>
                                    <small class="muted"><?php echo e(Str::limit($service->notes, 50)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-col="Amount" class="text-end fw-semibold"><?php echo e($service->money()); ?></td>
                            <td data-col="Status" class="text-center">
                                <?php ($s = $service->status); ?>
                                <span class="badge <?php if($s==='paid'): ?> badge-success <?php elseif($s==='unpaid'): ?> badge-danger <?php elseif($s==='overdue'): ?> badge-danger <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($service->statusLabel()); ?></span>
                            </td>
                            <td data-col="Requested"><?php echo e($service->requested_at?->format('M j, Y') ?? '—'); ?></td>
                            <td data-col="Actions" class="text-end">
                                <a href="<?php echo e(route('admin.other-services.receipt', $service)); ?>" class="btn btn-outline btn-sm">View receipt</a>
                                <form method="POST" action="<?php echo e(route('admin.other-services.destroy', $service)); ?>" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this service record?', message: 'This service request and its payment record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-outline danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="empty-cell">No service requests yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cv-card">
                        <div class="cv-row"><span class="cv-label">Business</span><span class="cv-value"><?php echo e($service->client?->business_name ?: $service->client?->name); ?><br><small class="muted"><?php echo e($service->client?->name); ?></small></span></div>
                        <div class="cv-row"><span class="cv-label">Service</span><span class="cv-value"><?php echo e($service->serviceName()); ?><?php if($service->notes): ?><br><small class="muted"><?php echo e(Str::limit($service->notes, 50)); ?></small><?php endif; ?></span></div>
                        <div class="cv-row"><span class="cv-label">Amount</span><span class="cv-value"><?php echo e($service->money()); ?></span></div>
                        <div class="cv-row"><span class="cv-label">Status</span><span class="cv-value"><?php ($s = $service->status); ?><span class="badge <?php if($s==='paid'): ?> badge-success <?php elseif($s==='unpaid'): ?> badge-danger <?php elseif($s==='overdue'): ?> badge-danger <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($service->statusLabel()); ?></span></span></div>
                        <div class="cv-row"><span class="cv-label">Requested</span><span class="cv-value"><?php echo e($service->requested_at?->format('M j, Y') ?? '—'); ?></span></div>
                        <div class="cv-row cv-actions"><span class="cv-label">Actions</span><span class="cv-value"><a href="<?php echo e(route('admin.other-services.receipt', $service)); ?>" class="btn btn-outline btn-sm">View receipt</a> <form method="POST" action="<?php echo e(route('admin.other-services.destroy', $service)); ?>" class="d-inline" onsubmit="return egliane.confirm.form(this, { title: 'Delete this service record?', message: 'This service request and its payment record will be permanently deleted.', danger: true, confirmLabel: 'Delete' });"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="btn btn-outline danger btn-sm">Delete</button></form></span></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">No service requests yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($services->links('pagination.simple')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/other-services/billing.blade.php ENDPATH**/ ?>