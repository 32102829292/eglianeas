<?php $__env->startSection('title', 'Collections & Follow-ups — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>Collections &amp; Follow-ups</h1>
            <p>Track unpaid, pending, and overdue billings and follow up with clients.</p>
        </div>
        <a href="<?php echo e(route('admin.billing.create')); ?>" class="btn btn-primary">Create billing statement</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Outstanding</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['outstanding'], 2)); ?></b>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon stat-icon-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">Overdue bills</span>
            <b class="stat-value"><?php echo e($stats['overdueCount']); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="stat-label">Due within 7 days</span>
            <b class="stat-value"><?php echo e($stats['dueSoon']); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <span class="stat-label">Awaiting sales (pending)</span>
            <b class="stat-value"><?php echo e($stats['pendingCount']); ?></b>
        </div>
    </div>

    <div class="filter-bar">
        <form id="collections-filter" method="GET" action="<?php echo e(route('admin.collections.index')); ?>">
            <label class="toolbar-field">
                <span class="toolbar-label">Quarter</span>
                <select name="quarter" class="toolbar-select" aria-label="Filter by quarter">
                    <option value="">All quarters</option>
                    <?php $__currentLoopData = $availableQuarters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quarter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($quarter->key()); ?>" <?php if($activeQuarter?->equals($quarter)): echo 'selected'; endif; ?>><?php echo e($quarter->label()); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <label class="toolbar-field">
                <span class="toolbar-label">Status</span>
                <select name="status" class="toolbar-select" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <?php $__currentLoopData = ['pending' => 'Pending', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>" <?php if($activeStatus === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 collections-table">
                <colgroup>
                    <col class="col-business">
                    <col class="col-period">
                    <col class="col-total">
                    <col class="col-status">
                    <col class="col-due">
                    <col class="col-actions">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Business</th>
                        <th>Period</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th>Due date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $billings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $billing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-col="Business">
                                <div class="fw-semibold"><?php echo e($billing->client?->business_name ?: $billing->client?->name); ?></div>
                                <small class="muted"><?php echo e($billing->client?->name); ?></small>
                            </td>
                            <td data-col="Period">
                                <div class="fw-semibold"><?php echo e($billing->periodTitleUppercase()); ?> BILLING</div>
                                <small class="muted"><?php echo e($billing->period_label); ?></small>
                            </td>
                            <td class="text-end fw-semibold td-money" data-col="Total"><?php echo e($billing->money($billing->total)); ?></td>
                            <td class="text-center td-status" data-col="Status">
                                <span class="badge badge-<?php echo e($billing->status); ?>"><?php echo e($billing->statusLabel()); ?></span>
                            </td>
                            <td class="td-due" data-col="Due date">
                                <?php echo e($billing->due_date?->format('M j, Y') ?? '—'); ?>

                                <?php if($billing->status === 'overdue'): ?>
                                    <div><small class="text-danger"><?php echo e($billing->due_date?->diffForHumans()); ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions" data-col="Actions">
                                <?php echo $__env->make('admin.collections._row-actions', ['billing' => $billing, 'menuType' => 'table'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="empty-cell">Nothing to collect right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $billings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $billing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><?php echo e($billing->client?->business_name ?: $billing->client?->name); ?></div>
                                <div class="cv-head-sub"><?php echo e($billing->periodTitleUppercase()); ?> BILLING</div>
                            </div>
                            <span class="badge badge-<?php echo e($billing->status); ?>"><?php echo e($billing->statusLabel()); ?></span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Client</span><span class="cv-value"><?php echo e($billing->client?->name); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Total</span><span class="cv-value"><?php echo e($billing->money($billing->total)); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Due date</span><span class="cv-value"><?php echo e($billing->due_date?->format('M j, Y') ?? '—'); ?><?php echo e($billing->status === 'overdue' ? ' ('.$billing->due_date?->diffForHumans().')' : ''); ?></span></div>
                        </div>
                        <div class="cv-card-actions">
                            <?php echo $__env->make('admin.collections._row-actions', ['billing' => $billing, 'menuType' => 'card'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">Nothing to collect right now.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($billings->links('pagination.simple')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.getElementById('collections-filter').addEventListener('submit', function (e) {
        var quarter = this.elements.namedItem('quarter');
        if (quarter && !quarter.value) quarter.disabled = true;
        var status = this.elements.namedItem('status');
        if (status && !status.value) status.disabled = true;
    });

    (function () {
        var menus = Array.prototype.slice.call(document.querySelectorAll('.more-menu'));

        function closeAll(except) {
            menus.forEach(function (menu) {
                if (menu === except) return;
                menu.style.display = 'none';
                var btn = document.getElementById(menu.getAttribute('aria-labelledby'));
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        }

        function setPos(menu) {
            var rect = menu.getBoundingClientRect();
            menu.classList.toggle('dropdown-menu--up', rect.bottom > window.innerHeight - 8);
        }

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                if (!menu) return;
                var willOpen = menu.style.display !== 'block';
                closeAll(menu);
                if (willOpen) {
                    menu.style.display = 'block';
                    toggle.setAttribute('aria-expanded', 'true');
                    setPos(menu);
                } else {
                    menu.style.display = 'none';
                    toggle.setAttribute('aria-expanded', 'false');
                }
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });

        window.addEventListener('resize', function () {
            closeAll();
        });
    })();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/collections/index.blade.php ENDPATH**/ ?>