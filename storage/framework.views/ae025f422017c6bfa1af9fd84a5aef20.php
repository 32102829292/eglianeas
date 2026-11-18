<?php $__env->startSection('title', 'My Dashboard — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row page-head-dash">
        <div class="dash-headline">
            <span class="dash-headline-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <div class="dash-headline-text">
                <span class="dash-headline-label">Account Overview</span>
                <h1>Client Dashboard</h1>
                <p>View your account information, track your services, and stay updated.</p>
            </div>
        </div>
    </div>

    <div class="status-banner status-<?php echo e($profile->status); ?>">
        <span class="status-dot" aria-hidden="true"></span>
        <span class="status-label">Account Status:</span>
        <span class="status-value"><?php echo e($profile->statusLabel()); ?></span>
        <?php if($profile->status !== \App\Models\ClientProfile::STATUS_CURRENT): ?>
            <span class="status-note"><?php echo e(\App\Models\ClientProfile::STATUS_NOTES[$profile->status] ?? ''); ?></span>
        <?php endif; ?>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
            <span class="stat-label">Income (<?php echo e(now()->year); ?>)</span>
            <b class="stat-value">&#8369; <?php echo e(number_format($stats['income'], 2)); ?></b>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
            </div>
            <span class="stat-label">Expenses (<?php echo e(now()->year); ?>)</span>
            <b class="stat-value">&#8369; <?php echo e(number_format($stats['expenses'], 2)); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Transactions</span>
            <b class="stat-value"><?php echo e($stats['transactions']); ?></b>
        </div>
        <div class="stat-card <?php echo e($stats['pendingFilings'] > 0 ? 'stat-warn' : 'stat-ok'); ?>">
            <div class="stat-icon <?php echo e($stats['pendingFilings'] > 0 ? 'stat-icon-warn' : 'stat-icon-ok'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Pending filings</span>
            <b class="stat-value"><?php echo e($stats['pendingFilings']); ?></b>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent transactions</h3>
            </div>
            <div class="table-wrap table-card-view">
                <table class="table">
                    <thead><tr><th>Title</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td>
                                    <div class="cell-name"><?php echo e($tx->title); ?></div>
                                    <small class="muted"><?php echo e($tx->category ?? '—'); ?></small>
                                </td>
                                <td>
                                    <span class="<?php echo e($tx->type === 'income' ? 'amount-income' : 'amount-expense'); ?>"><?php echo e($tx->type === 'income' ? '+' : '−'); ?> &#8369; <?php echo e(number_format($tx->amount, 2)); ?></span>
                                </td>
                                <td class="muted"><?php echo e($tx->transaction_date->format('M j, Y')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="3" class="empty-cell">No transactions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="card-view-list">
                    <?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="cv-card">
                            <div class="cv-row"><span class="cv-label">Title</span><span class="cv-value"><?php echo e($tx->title); ?><br><small class="muted"><?php echo e($tx->category ?? '—'); ?></small></span></div>
                            <div class="cv-row"><span class="cv-label">Amount</span><span class="cv-value"><span class="<?php echo e($tx->type === 'income' ? 'amount-income' : 'amount-expense'); ?>"><?php echo e($tx->type === 'income' ? '+' : '−'); ?> &#8369; <?php echo e(number_format($tx->amount, 2)); ?></span></span></div>
                            <div class="cv-row"><span class="cv-label">Date</span><span class="cv-value"><?php echo e($tx->transaction_date->format('M j, Y')); ?></span></div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <p class="cv-card cv-empty">No transactions recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Recent filings</h3>
            </div>
            <ul class="list">
                <?php $__empty_1 = true; $__currentLoopData = $recentFilings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <li>
                        <div>
                            <b><?php echo e($filing->type); ?></b>
                            <small class="muted"><?php echo e($filing->period); ?> &middot; due <?php echo e($filing->due_date?->format('M j, Y') ?? '—'); ?></small>
                        </div>
                        <span class="badge badge-<?php echo e($filing->status); ?>"><?php echo e(\App\Models\Filing::STATUSES[$filing->status]); ?></span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <li class="empty-cell">No filings yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/client/dashboard.blade.php ENDPATH**/ ?>