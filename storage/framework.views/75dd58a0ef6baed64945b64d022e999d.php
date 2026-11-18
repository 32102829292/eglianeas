<?php $__env->startSection('title', 'Activity Logs — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head">
        <h1>Activity logs</h1>
        <p>Every important action across the portal.</p>
    </div>

    <form method="GET" action="<?php echo e(route('admin.activity-logs')); ?>" class="filter-bar log-search-bar">
        <input class="form-control log-search-input" type="search" name="q" value="<?php echo e($query); ?>" placeholder="Search action or details…">
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if($query): ?>
            <a href="<?php echo e(route('admin.activity-logs')); ?>" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-col="User" class="cell-name"><?php echo e($log->user?->name ?? 'Guest'); ?></td>
                            <td data-col="Action"><code class="code-pill"><?php echo e($log->action); ?></code></td>
                            <td data-col="Details" class="muted"><?php echo e($log->description); ?></td>
                            <td data-col="IP" class="muted"><?php echo e($log->ip_address); ?></td>
                            <td data-col="When" class="muted"><?php echo e($log->created_at->diffForHumans()); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" class="empty-cell">No activity found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><code class="code-pill"><?php echo e($log->action); ?></code></div>
                                <div class="cv-head-sub"><?php echo e($log->user?->name ?? 'Guest'); ?></div>
                            </div>
                            <span class="badge badge-neutral"><?php echo e($log->created_at->diffForHumans()); ?></span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair cv-full"><span class="cv-label">Details</span><span class="cv-value"><?php echo e($log->description); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">IP</span><span class="cv-value"><?php echo e($log->ip_address); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">When</span><span class="cv-value"><?php echo e($log->created_at->diffForHumans()); ?></span></div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">No activity found.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($logs->links('pagination.simple')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/activity-logs.blade.php ENDPATH**/ ?>