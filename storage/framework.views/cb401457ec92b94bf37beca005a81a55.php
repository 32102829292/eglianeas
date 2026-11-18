<?php $__env->startSection('title', 'Document Distribution — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head">
        <h1>Document Distribution</h1>
        <p>BIR form checklists, delivery logs, and softcopy management for each client.</p>
    </div>

    <div class="filter-bar">
        <form method="GET" action="<?php echo e(route('admin.distribution.index')); ?>">
            <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search business, contact, or client code&hellip;">
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Client Code</th>
                        <th>Business</th>
                        <th>Contact</th>
                        <th class="text-center">BIR Forms</th>
                        <th class="text-center">Softcopies</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($client = $entry['user']); ?>
                        <tr>
                            <td data-col="Client Code">
                                <span class="badge badge-navy"><?php echo e($client->client_code ?? '—'); ?></span>
                            </td>
                            <td data-col="Business">
                                <div class="fw-semibold"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <small class="muted"><?php echo e($client->profile?->line_of_business ?? '—'); ?></small>
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold"><?php echo e($client->name); ?></div>
                                <small class="muted"><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></small>
                            </td>
                            <td class="text-center" data-col="BIR Forms">
                                <span class="badge <?php if($entry['filed'] > 0): ?> badge-success <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($entry['filed']); ?>/<?php echo e($entry['total']); ?></span>
                            </td>
                            <td class="text-center" data-col="Softcopies"><?php echo e($entry['softcopies']); ?></td>
                            <td class="text-end" data-col="Actions">
                                <a href="<?php echo e(route('admin.distribution.show', $client)); ?>" class="btn btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="empty-cell">No clients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php ($client = $entry['user']); ?>
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <div class="cv-head-sub"><?php echo e($client->profile?->line_of_business ?? '—'); ?><?php echo e($client->client_code ? ' · '.$client->client_code : ''); ?></div>
                            </div>
                            <span class="badge <?php if($entry['filed'] > 0): ?> badge-success <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($entry['filed']); ?>/<?php echo e($entry['total']); ?></span>
                        </div>
                        <div class="cv-card-body wide">
                            <div class="cv-pair"><span class="cv-label">Contact</span><span class="cv-value"><?php echo e($client->name); ?><br><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></span></div>
                            <div class="cv-pair"><span class="cv-label">BIR Forms</span><span class="cv-value"><?php echo e($entry['filed']); ?>/<?php echo e($entry['total']); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Softcopies</span><span class="cv-value"><?php echo e($entry['softcopies']); ?></span></div>
                        </div>
                        <div class="cv-card-actions">
                            <a href="<?php echo e(route('admin.distribution.show', $client)); ?>" class="btn btn-primary btn-sm">Open</a>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">No clients found.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($clients->links('pagination.simple')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/distribution/index.blade.php ENDPATH**/ ?>