<?php $__env->startSection('title', 'Billing Statements — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>Billing Statements</h1>
            <p>Create and manage quarterly billing statements per client.</p>
        </div>
        <div class="page-head-actions">
            <a href="<?php echo e(route('admin.billing.create')); ?>" class="btn btn-primary">Create billing statement</a>
        </div>
    </div>

    <div class="stat-grid billing-summary">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total billed</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['billed'], 2)); ?></b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Collected</span>
            <b class="stat-value">₱<?php echo e(number_format($stats['collected'], 2)); ?></b>
        </div>
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
            <b class="stat-value"><?php echo e($stats['overdue']); ?></b>
        </div>
    </div>

    <p class="summary-scope">
        Showing billing summary for <strong><?php echo e($activeQuarter ? $activeQuarter->label() : 'all quarters'); ?></strong>
    </p>

    <div class="page-toolbar">
        <form id="billing-search" class="toolbar-form" method="GET" action="<?php echo e(route('admin.billing.index')); ?>" role="search">
            <div class="search-combo">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search business or contact&hellip;" aria-label="Search billing statements">
            </div>
            <label class="toolbar-field">
                <span class="toolbar-label">Summary period</span>
                <select name="quarter" class="toolbar-select" aria-label="Filter billing summary by period">
                    <option value="">All quarters</option>
                    <?php $__currentLoopData = $availableQuarters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quarter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($quarter->key()); ?>" <?php if($activeQuarter?->equals($quarter)): echo 'selected'; endif; ?>><?php echo e($quarter->label()); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <label class="toolbar-field">
                <span class="toolbar-label">Billing amount</span>
                <select name="amount_order" class="toolbar-select" aria-label="Sort by billing amount">
                    <option value="">Default order</option>
                    <option value="asc" <?php if($amountOrder === 'asc'): echo 'selected'; endif; ?>>Smallest → Largest</option>
                    <option value="desc" <?php if($amountOrder === 'desc'): echo 'selected'; endif; ?>>Largest → Smallest</option>
                </select>
            </label>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
        <div class="page-toolbar-group">
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-outline btn-sm dropdown-toggle" data-dropdown="billing-download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Billing Summary
                </button>
                <div class="dropdown-menu billing-download-panel" id="billing-download-menu">
                    <p class="dl-hint">Download the billing summary for <strong><?php echo e($activeQuarter ? $activeQuarter->label() : 'all quarters'); ?></strong>:</p>
                    <div class="billing-download-row">
                        <label class="form-label" for="dl-quarter">Quarter</label>
                        <select class="form-control form-control-sm" id="dl-quarter">
                            <option value="">All Quarters</option>
                            <option value="1" <?php if($activeQuarter !== null && $activeQuarter->quarter === 1): echo 'selected'; endif; ?>>1st Quarter</option>
                            <option value="2" <?php if($activeQuarter !== null && $activeQuarter->quarter === 2): echo 'selected'; endif; ?>>2nd Quarter</option>
                            <option value="3" <?php if($activeQuarter !== null && $activeQuarter->quarter === 3): echo 'selected'; endif; ?>>3rd Quarter</option>
                            <option value="4" <?php if($activeQuarter !== null && $activeQuarter->quarter === 4): echo 'selected'; endif; ?>>4th Quarter</option>
                        </select>
                    </div>
                    <div class="billing-download-row">
                        <label class="form-label" for="dl-year">Year</label>
                        <select class="form-control form-control-sm" id="dl-year">
                            <?php $__currentLoopData = $downloadYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($y); ?>" <?php if($y === $defaultDownloadYear): echo 'selected'; endif; ?>><?php echo e($y); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="billing-download-btns">
                        <a href="#" id="dl-xlsx" class="btn btn-outline btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            XLSX — Summary
                        </a>
                        <a href="#" id="dl-pdf" class="btn btn-outline danger btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            PDF — Summary
                        </a>
                    </div>
                </div>
            </div>
            <a href="<?php echo e(route('admin.billing.paymentSettings')); ?>" class="btn btn-outline btn-sm">Payment Settings</a>
            <a href="<?php echo e(route('admin.billing.settings')); ?>" class="btn btn-outline btn-sm">Fee Presets</a>
        </div>
    </div>

    <?php
        $selYear = $activeQuarter?->year ?? $defaultDownloadYear;
        $selParams = ['year' => $selYear];
        if ($activeQuarter) {
            $selParams['quarter'] = $activeQuarter->quarter;
        }
        if ($q !== '') {
            $selParams['q'] = $q;
        }
        $printAllUrl = route('admin.billing.printBatch', $selParams ?: null);
        $exportAllUrl = route('admin.billing.exportSummaryXlsx', $selParams ?: null);
    ?>
    <div class="billing-select-bar">
        <div class="billing-select-summary">
            <label class="billing-select-all-label">
                <input type="checkbox" id="billingSelectAll" data-billing-select-all aria-label="Select all matching clients on this page">
                Select all
            </label>
            <span><strong id="billingSelectCount">0</strong> of <?php echo e($entries->total()); ?> matching clients selected</span>
        </div>
        <p class="billing-select-helper">No clients selected &mdash; all matching billing receipts will be included.</p>
        <div class="billing-select-actions">
            <button type="button" id="printSelectedBtn" class="btn btn-outline btn-sm" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Selected (<span id="printSelectedCount">0</span>)
            </button>
            <a href="<?php echo e($printAllUrl); ?>" id="printAllBtn" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print All
            </a>
            <a href="<?php echo e($exportAllUrl); ?>" id="exportXlsxBtn" class="btn btn-outline btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Export XLSX
            </a>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 finance-table">
                <colgroup>
                    <col class="print-check-cell">
                    <col class="col-business">
                    <col class="col-contact">
                    <col class="col-bir">
                    <col class="col-bills">
                    <col class="col-total">
                    <col class="col-outstanding">
                    <col class="col-status">
                    <col class="col-actions">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th class="print-check-cell">
                            <input type="checkbox" data-billing-select-all aria-label="Select all matching clients on this page">
                        </th>
                        <th>Business</th>
                        <th>Contact</th>
                        <th class="text-center">BIR Forms</th>
                        <th class="text-center">Bills</th>
                        <th class="text-end">Total billed</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($client = $entry['user']); ?>
                        <tr>
                            <td class="print-check-cell">
                                <input type="checkbox" class="billing-select-check" value="<?php echo e($client->id); ?>" aria-label="Select <?php echo e($client->business_name ?: $client->name); ?> for printing">
                            </td>
                            <td data-col="Business">
                                <div class="fw-semibold"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <small class="muted"><?php echo e($client->profile?->line_of_business ?? '—'); ?></small>
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold"><?php echo e($client->name); ?></div>
                                <small class="muted"><a href="mailto:<?php echo e($client->email); ?>" class="contact-link contact-email"><?php echo e($client->email); ?></a></small>
                            </td>
                            <td class="text-center td-bir" data-col="BIR Forms">
                                <?php if($entry['bir_ready']): ?>
                                    <span class="badge badge-success">BIR Forms Ready</span>
                                <?php else: ?>
                                    <a href="<?php echo e(route('admin.bir-forms.index', ['client_id' => $client->id])); ?>" class="btn btn-outline btn-sm row-action">Add BIR Forms</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center td-bills" data-col="Bills"><?php echo e($entry['billing_count']); ?></td>
                            <td class="text-end fw-semibold td-money" data-col="Total billed"><?php echo e('₱'.number_format($entry['total_billed'], 2)); ?></td>
                            <td class="text-end td-money" data-col="Outstanding">
                                <?php if($entry['outstanding'] > 0): ?>
                                    <span class="text-danger fw-semibold">₱<?php echo e(number_format($entry['outstanding'], 2)); ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center td-status" data-col="Status">
                                <?php if($entry['status'] === 'none'): ?>
                                    <span class="badge badge-neutral">No billing statements</span>
                                <?php else: ?>
                                    <?php ($s = $entry['status']); ?>
                                    <span class="badge badge-<?php echo e($s); ?>"><?php echo e(App\Models\Billing::STATUSES[$s] ?? ucfirst($s)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end col-actions" data-col="Actions">
                                <a href="<?php echo e(route('admin.billing.show', $client)); ?>" class="btn btn-outline btn-sm row-action">Open billing statement</a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="9" class="empty-cell">No clients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php ($client = $entry['user']); ?>
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <div class="cv-head-sub"><?php echo e($client->profile?->line_of_business ?? '—'); ?><?php echo e($client->client_code ? ' · '.$client->client_code : ''); ?></div>
                            </div>
                            <?php if($entry['status'] === 'none'): ?>
                                <span class="badge badge-neutral">No billing statements</span>
                            <?php else: ?>
                                <?php ($s = $entry['status']); ?>
                                <span class="badge badge-<?php echo e($s); ?>"><?php echo e(App\Models\Billing::STATUSES[$s] ?? ucfirst($s)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Select</span><span class="cv-value"><input type="checkbox" class="billing-select-check" value="<?php echo e($client->id); ?>" aria-label="Select <?php echo e($client->business_name ?: $client->name); ?> for printing"></span></div>
                            <div class="cv-pair"><span class="cv-label">Contact</span><span class="cv-value"><?php echo e($client->name); ?><br><a href="mailto:<?php echo e($client->email); ?>" class="contact-link contact-email"><?php echo e($client->email); ?></a></span></div>
                            <div class="cv-pair"><span class="cv-label">BIR forms</span><span class="cv-value">
                                <?php if($entry['bir_ready']): ?>
                                    <span class="badge badge-success">BIR Forms Ready</span>
                                <?php else: ?>
                                    <a href="<?php echo e(route('admin.bir-forms.index', ['client_id' => $client->id])); ?>" class="btn btn-outline btn-sm row-action">Add BIR Forms</a>
                                <?php endif; ?>
                            </span></div>
                            <div class="cv-pair"><span class="cv-label">Bills</span><span class="cv-value"><?php echo e($entry['billing_count']); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Total billed</span><span class="cv-value"><?php echo e('₱'.number_format($entry['total_billed'], 2)); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Outstanding</span><span class="cv-value"><?php if($entry['outstanding'] > 0): ?><span class="text-danger fw-semibold">₱<?php echo e(number_format($entry['outstanding'], 2)); ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></span></div>
                        </div>
<div class="cv-card-actions">
                                <?php if(! $entry['bir_ready']): ?>
                                    <a href="<?php echo e(route('admin.bir-forms.index', ['client_id' => $client->id])); ?>" class="btn btn-outline btn-sm row-action">Add BIR Forms</a>
                                <?php endif; ?>
                                <a href="<?php echo e(route('admin.billing.show', $client)); ?>" class="btn btn-primary btn-sm row-action">Open billing statement</a>
                            </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">No clients found.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($entries->links('pagination.simple')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown]');
        if (toggle) {
            var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
            if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            return;
        }
        if (e.target.closest('.dropdown-menu')) return;
        document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
    });
    (function () {
        var quarterSelect = document.getElementById('dl-quarter');
        var yearSelect = document.getElementById('dl-year');
        var xlsxLink = document.getElementById('dl-xlsx');
        var pdfLink = document.getElementById('dl-pdf');
        var exportXlsxBtn = document.getElementById('exportXlsxBtn');
        var printAllBtn = document.getElementById('printAllBtn');
        var printSelectedBtn = document.getElementById('printSelectedBtn');
        var selectCountEl = document.getElementById('billingSelectCount');
        var printSelectedCountEl = document.getElementById('printSelectedCount');
        var xlsxBase = <?php echo \Illuminate\Support\Js::from(route('admin.billing.exportSummaryXlsx'))->toHtml() ?>;
        var pdfBase = <?php echo \Illuminate\Support\Js::from(route('admin.billing.exportSummaryPdf'))->toHtml() ?>;
        var exportAllBase = <?php echo \Illuminate\Support\Js::from($exportAllUrl)->toHtml() ?>;
        var printAllBase = <?php echo \Illuminate\Support\Js::from($printAllUrl)->toHtml() ?>;
        var selected = [];

        function withClients(url, ids) {
            if (!ids.length) return url;
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            return url + sep + ids.map(function (id) {
                return 'clients[]=' + encodeURIComponent(id);
            }).join('&');
        }

        function updateAllLinks() {
            var params = new URLSearchParams();
            if (quarterSelect.value) params.set('quarter', quarterSelect.value);
            if (yearSelect.value) params.set('year', yearSelect.value);
            var qs = params.toString();
            var base = qs ? '?' + qs : '';
            xlsxLink.href = withClients(xlsxBase + base, selected);
            pdfLink.href = withClients(pdfBase + base, selected);
            exportXlsxBtn.href = withClients(exportAllBase, selected);
            printAllBtn.href = printAllBase;
            printSelectedBtn.disabled = selected.length === 0;
            selectCountEl.textContent = selected.length;
            printSelectedCountEl.textContent = selected.length;
        }

        function refreshSelection() {
            var raw = Array.prototype.map.call(
                document.querySelectorAll('.billing-select-check:checked'),
                function (cb) { return cb.value; }
            );
            // The same client appears once per visible view (table + cards);
            // dedupe so export/print URLs never repeat a client_id.
            selected = Array.from(new Set(raw));
            updateAllLinks();
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.billing-select-check')) {
                refreshSelection();
                return;
            }
            if (e.target.matches('[data-billing-select-all]')) {
                var checked = e.target.checked;
                document.querySelectorAll('[data-billing-select-all]').forEach(function (sel) {
                    sel.checked = checked;
                });
                document.querySelectorAll('.billing-select-check').forEach(function (cb) {
                    cb.checked = checked;
                });
                refreshSelection();
            }
        });

        quarterSelect.addEventListener('change', updateAllLinks);
        yearSelect.addEventListener('change', updateAllLinks);

        printSelectedBtn.addEventListener('click', function () {
            if (!selected.length) return;
            window.open(withClients(printAllBase, selected), '_blank', 'noopener');
        });

        updateAllLinks();
    })();
    var billingSearch = document.getElementById('billing-search');
    billingSearch.addEventListener('submit', function (e) {
        var q = this.elements.namedItem('q');
        if (q && !q.value.trim()) q.disabled = true;
        var quarter = this.elements.namedItem('quarter');
        if (quarter && !quarter.value) quarter.disabled = true;
    });
    // Changing the summary period applies the GET filter immediately so the
    // summary cards and the client table both update. The Filter button still
    // re-applies the search text (and any selected period).
    var summaryPeriod = billingSearch.elements.namedItem('quarter');
    summaryPeriod.addEventListener('change', function () {
        var q = billingSearch.elements.namedItem('q');
        if (q && !q.value.trim()) q.disabled = true;
        if (!this.value) this.disabled = true;
        billingSearch.submit();
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/index.blade.php ENDPATH**/ ?>