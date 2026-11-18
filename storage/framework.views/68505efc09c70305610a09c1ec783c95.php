<?php $__env->startSection('title', ($client->business_name ?: $client->name).' — Billing Statements — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <a href="<?php echo e(route('admin.billing.index')); ?>" class="back-link no-print">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Billing Statements
    </a>

    <div class="page-head page-head-row">
        <div>
            <h1><?php echo e($client->name); ?></h1>
            <p><?php echo e($selectedCompany?->company_name ?: $client->business_name); ?> &middot; <a href="mailto:<?php echo e($selectedCompany?->business_email ?: $client->email); ?>" class="contact-link"><?php echo e($selectedCompany?->business_email ?: $client->email); ?></a> &middot; <?php echo e($selectedCompany?->business_type ?: $client->profile?->business_type ?? '—'); ?></p>
            <?php if($companies->count() > 1): ?>
                <form method="GET" action="<?php echo e(route('admin.billing.show', $client)); ?>" class="inline-form">
                    <label class="visually-hidden" for="billing-company-select">Company / branch</label>
                    <select id="billing-company-select" name="client_company_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($company->id); ?>" <?php if($selectedCompany?->id === $company->id): echo 'selected'; endif; ?>><?php echo e($company->company_code); ?> — <?php echo e($company->company_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>
        <div class="btn-row">
            <select id="batchPaperSize" class="form-control form-control-sm batch-paper-select" aria-label="Paper size" hidden>
                <option value="a4" selected>A4</option>
                <option value="letter">Letter</option>
            </select>
            <button type="button" id="printBatchBtn" class="btn btn-outline" hidden>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print selected (<span id="printBatchCount">0</span>)
            </button>
            <a href="<?php echo e(route('admin.billing.clientCsv', $client)); ?>" class="btn btn-outline">Export CSV</a>
            <a href="<?php echo e(route('admin.billing.create', ['client' => $client->id, 'client_company_id' => $selectedCompany?->id])); ?>" class="btn btn-primary">New billing</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <span class="stat-label">Billing statement records</span>
            <b class="stat-value"><?php echo e($stats['count']); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['billed'], 2)); ?></b>
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
    </div>

    <?php $__empty_1 = true; $__currentLoopData = $billingsByYear; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year => $billings): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="section-gap">
            <h2 class="text-section"><?php echo e($year); ?></h2>
            <div class="card">
                <div class="table-wrap table-card-view">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="print-check-cell"><input type="checkbox" data-batch-check-all aria-label="Select all statements for <?php echo e($year); ?>"></th>
                                <th>Quarter</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Due date</th>
                                <th>Date paid</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $billings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $billing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="print-check-cell"><input type="checkbox" class="batch-print-check" value="<?php echo e($billing->id); ?>" aria-label="Select <?php echo e($billing->period_label); ?> for printing"></td>
                                    <td data-col="Quarter">
                                        <div class="cell-name"><?php echo e($billing->periodTitleUppercase()); ?> BILLING</div>
                                        <small class="muted"><?php echo e($billing->period_label); ?></small>
                                    </td>
                                    <td data-col="Total"><b><?php echo e($billing->money($billing->total)); ?></b></td>
                                    <td data-col="Status">
                                        <span class="badge badge-<?php echo e($billing->status); ?>"><?php echo e($billing->statusLabel()); ?></span>
                                    </td>
                                    <td data-col="Due date"><?php echo e($billing->due_date?->format('M j, Y') ?? '—'); ?></td>
                                    <td data-col="Date paid"><?php echo e($billing->paid_at?->format('M j, Y') ?? '—'); ?></td>
                                    <td class="actions-cell" data-col="Actions">
                                        <?php if($billing->isDraft()): ?>
                                            <form method="POST" action="<?php echo e(route('admin.billing.finalize', $billing)); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Finalize this billing statement?', message: 'This will make it active and visible to the client.', confirmLabel: 'Finalize' });">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="btn btn-primary btn-sm">Finalize</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?php echo e(route('admin.billing.receipt', $billing)); ?>" class="btn btn-outline btn-sm">View receipt</a>
                                        <?php endif; ?>
                                        <a href="<?php echo e(route('admin.billing.csv', $billing)); ?>" class="link">CSV</a>
                                        <?php if(! $billing->isPaid()): ?><a href="<?php echo e(route('admin.billing.edit', $billing)); ?>" class="link">Edit</a><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                    <div class="card-view-list">
                        <?php $__empty_2 = true; $__currentLoopData = $billings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $billing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <div class="cv-card">
                                <div class="cv-card-head">
                                    <div class="cv-head-main">
                                        <div class="cv-head-title"><?php echo e($billing->periodTitleUppercase()); ?> BILLING</div>
                                        <div class="cv-head-sub"><?php echo e($billing->period_label); ?></div>
                                    </div>
                                    <span class="badge badge-<?php echo e($billing->status); ?>"><?php echo e($billing->statusLabel()); ?></span>
                                </div>
                                <div class="cv-card-body">
                                    <div class="cv-pair"><span class="cv-label">Print</span><span class="cv-value"><input type="checkbox" class="batch-print-check" value="<?php echo e($billing->id); ?>" aria-label="Select <?php echo e($billing->period_label); ?> for printing"></span></div>
                                    <div class="cv-pair"><span class="cv-label">Total</span><span class="cv-value"><?php echo e($billing->money($billing->total)); ?></span></div>
                                    <div class="cv-pair"><span class="cv-label">Due date</span><span class="cv-value"><?php echo e($billing->due_date?->format('M j, Y') ?? '—'); ?></span></div>
                                    <div class="cv-pair"><span class="cv-label">Date paid</span><span class="cv-value"><?php echo e($billing->paid_at?->format('M j, Y') ?? '—'); ?></span></div>
                                </div>
                                <div class="cv-card-actions">
                                    <?php if($billing->isDraft()): ?><form method="POST" action="<?php echo e(route('admin.billing.finalize', $billing)); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Finalize this billing statement?', message: 'This will make it active and visible to the client.', confirmLabel: 'Finalize' });"><?php echo csrf_field(); ?> <button type="submit" class="btn btn-primary btn-sm">Finalize</button></form><?php else: ?><a href="<?php echo e(route('admin.billing.receipt', $billing)); ?>" class="btn btn-outline btn-sm">View receipt</a><?php endif; ?>
                                    <a href="<?php echo e(route('admin.billing.csv', $billing)); ?>" class="btn btn-outline btn-sm">CSV</a>
                                    <?php if(! $billing->isPaid()): ?><a href="<?php echo e(route('admin.billing.edit', $billing)); ?>" class="btn btn-outline btn-sm">Edit</a><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <p class="cv-card cv-empty">No billing statements for this client yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="empty-state">
            <p>No billing statements for this client yet.</p>
            <a href="<?php echo e(route('admin.billing.create', ['client' => $client->id, 'client_company_id' => $selectedCompany?->id])); ?>" class="btn btn-primary">Create the first billing</a>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        (function () {
            var printBtn = document.getElementById('printBatchBtn');
            var countEl = document.getElementById('printBatchCount');
            var paperSelect = document.getElementById('batchPaperSize');
            if (!printBtn || !countEl) return;

            function checkedBoxes() {
                return Array.prototype.slice.call(document.querySelectorAll('.batch-print-check:checked'));
            }

            function refresh() {
                var n = checkedBoxes().length;
                countEl.textContent = n;
                printBtn.hidden = n === 0;
                paperSelect.hidden = n === 0;
            }

            document.addEventListener('change', function (e) {
                if (e.target.matches('.batch-print-check')) {
                    refresh();
                    return;
                }
                if (e.target.matches('[data-batch-check-all]')) {
                    var table = e.target.closest('table');
                    if (!table) return;
                    table.querySelectorAll('.batch-print-check').forEach(function (cb) {
                        cb.checked = e.target.checked;
                    });
                    refresh();
                }
            });

            printBtn.addEventListener('click', function () {
                var ids = checkedBoxes().map(function (cb) { return cb.value; });
                if (!ids.length) return;
                var params = new URLSearchParams();
                ids.forEach(function (id) { params.append('ids[]', id); });
                params.append('paper', paperSelect && paperSelect.value === 'letter' ? 'letter' : 'a4');
                window.location.href = '<?php echo e(route("admin.billing.printBatch")); ?>?' + params.toString();
            });
        })();
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/show.blade.php ENDPATH**/ ?>