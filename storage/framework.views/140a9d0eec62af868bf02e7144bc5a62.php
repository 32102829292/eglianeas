<?php $__env->startSection('title', 'Notifications'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head">
        <h1>Notifications</h1>
        <p>Your recent updates from Egliane.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">All notifications</h2>
            <?php if($notifications->total() > 0): ?>
                <form method="POST" action="<?php echo e(route('notifications.read-all')); ?>" class="inline-form">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-outline btn-sm">Mark all read</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="list">
            <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a href="<?php echo e(route('notifications.open', $notification)); ?>" class="list-item <?php if($notification->isUnread()): ?> unread <?php endif; ?>">
                    <div class="list-item-main">
                        <div class="list-item-title">
                            <?php echo e($notification->title); ?>

                            <?php if($notification->isUnread()): ?>
                                <span class="ok-dot" title="Unread"></span>
                            <?php endif; ?>
                        </div>
                        <?php if($notification->body): ?>
                            <div class="list-item-sub"><?php echo e($notification->body); ?></div>
                        <?php endif; ?>
                        <?php if(($notification->reminder_count ?? 1) > 1): ?>
                            <div class="reminder-count">Reminded <?php echo e($notification->reminder_count); ?> times</div>
                        <?php endif; ?>
                        <div class="list-item-meta"><?php echo e($notification->created_at->format('M j, Y g:i A')); ?></div>
                    </div>
                    <span class="link">Open</span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="empty-cell">You have no notifications yet.</div>
            <?php endif; ?>
        </div>
        <?php if($notifications->hasPages()): ?>
            <div class="pagination">
                <?php echo e($notifications->links()); ?>

            </div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/notifications/index.blade.php ENDPATH**/ ?>