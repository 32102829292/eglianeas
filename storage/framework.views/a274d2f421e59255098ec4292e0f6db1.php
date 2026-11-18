<?php $__env->startSection('title', 'BIR Forms — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>BIR Forms</h1>
            <p>Select the company or branch whose BIR forms apply. Parent taxpayer details remain on the client account.</p>
        </div>
        <div class="page-head-actions">
            <?php if($highlightClientId): ?>
                <a href="<?php echo e(route('admin.billing.create', ['client_id' => $highlightClientId, 'client_company_id' => $selectedCompanyId])); ?>" class="btn btn-primary">Continue to Billing</a>
            <?php endif; ?>
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="bir-download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download BIR Forms Summary
                </button>
                <div class="dropdown-menu" id="bir-download-menu">
                    <a href="<?php echo e(route('admin.bir-forms.exportXlsx', array_filter(['q' => $q, 'client_company_id' => $selectedCompanyId]))); ?>" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as XLSX
                    </a>
                    <a href="<?php echo e(route('admin.bir-forms.exportPdf', array_filter(['q' => $q, 'client_company_id' => $selectedCompanyId]))); ?>" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="<?php echo e(route('admin.bir-forms.index')); ?>">
            <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search business, contact, or client code&hellip;">
            <?php if($highlightClientId): ?><input type="hidden" name="client_id" value="<?php echo e($highlightClientId); ?>"><?php endif; ?>
            <select name="client_company_id" class="form-control form-control-sm" aria-label="Company or branch">
                <option value="">All companies / branches</option>
                <?php $__currentLoopData = $companyOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option->id); ?>" <?php if($selectedCompanyId === $option->id): echo 'selected'; endif; ?>><?php echo e($option->company_code); ?> — <?php echo e($option->company_name ?: $option->client?->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <?php if(session('status')): ?>
        <div class="alert alert-success"><?php echo e(session('status')); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-muted">
                    <tr>
                        <th>Client ID</th>
                        <th>Company / branch</th>
                        <th>Contact</th>
                        <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="text-center" style="font-size:11px;white-space:nowrap;"><?php echo e($ft); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($company = $entry['company']); ?>
                        <?php ($client = $company->client); ?>
                        <?php ($statuses = $entry['statuses']); ?>
                        <tr id="company-<?php echo e($company->id); ?>" <?php if($selectedCompanyId === $company->id): ?> class="bir-flash" <?php endif; ?>>
                            <td>
                                <span class="badge badge-navy"><?php echo e($client->client_code ?? '—'); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo e($company->company_name ?: 'Unnamed company'); ?></div>
                                <small class="muted"><?php echo e($client->profile?->line_of_business ?? '—'); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo e($client->name); ?></div>
                                <small class="muted"><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></small>
                            </td>
                            <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php ($isOn = $statuses[$ft] ?? false); ?>
                                <td class="text-center">
                                    <form method="POST" action="<?php echo e(route('admin.bir-forms.toggle', $client)); ?>" class="inline-form bir-toggle-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="form_type" value="<?php echo e($ft); ?>">
                                        <input type="hidden" name="client_company_id" value="<?php echo e($company->id); ?>">
                                        <button type="submit" class="bir-toggle <?php echo e($isOn ? 'bir-toggle-on' : ''); ?>" title="<?php echo e($ft); ?>: <?php echo e($isOn ? 'Applicable' : 'Not applicable'); ?>" aria-pressed="<?php echo e($isOn ? 'true' : 'false'); ?>">
                                            <svg class="bir-ico-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                            <svg class="bir-ico-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </form>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <td class="text-center">
                                <span class="badge bir-count-badge <?php if($entry['applicableCount'] > 0): ?> badge-success <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($entry['applicableCount']); ?>/<?php echo e($entry['totalForms']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="<?php echo e(4 + count($formTypes)); ?>" class="empty-cell">No clients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php ($company = $entry['company']); ?>
                    <?php ($client = $company->client); ?>
                    <?php ($statuses = $entry['statuses']); ?>
                    <div class="cv-card" id="company-<?php echo e($company->id); ?>" <?php if($selectedCompanyId === $company->id): ?> data-bir-flash="1" <?php endif; ?>>
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <div class="cv-head-sub"><?php echo e($client->name); ?><?php echo e($client->client_code ? ' · '.$client->client_code : ''); ?></div>
                            </div>
                            <span class="badge bir-count-badge <?php if($entry['applicableCount'] > 0): ?> badge-success <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($entry['applicableCount']); ?>/<?php echo e($entry['totalForms']); ?></span>
                        </div>
                        <div class="cv-card-body bir-card-body">
                            <?php $__currentLoopData = $formTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php ($isOn = $statuses[$ft] ?? false); ?>
                                <div class="cv-pair">
                                    <span class="cv-label"><?php echo e($ft); ?></span>
                                    <span class="cv-value">
                                        <form method="POST" action="<?php echo e(route('admin.bir-forms.toggle', $client)); ?>" class="inline-form bir-toggle-form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="form_type" value="<?php echo e($ft); ?>">
                                            <input type="hidden" name="client_company_id" value="<?php echo e($company->id); ?>">
                                            <button type="submit" class="bir-toggle <?php echo e($isOn ? 'bir-toggle-on' : ''); ?>" title="<?php echo e($ft); ?>: <?php echo e($isOn ? 'Applicable' : 'Not applicable'); ?>" aria-pressed="<?php echo e($isOn ? 'true' : 'false'); ?>">
                                                <svg class="bir-ico-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                                                <svg class="bir-ico-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </form>
                                    </span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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

<?php if($selectedCompanyId): ?>
<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    var el = document.getElementById('company-<?php echo e($selectedCompanyId); ?>');
    if (!el) return;
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    window.setTimeout(function () {
        el.classList.remove('bir-flash');
        el.removeAttribute('data-bir-flash');
    }, 3400);
})();
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>

(function () {
    function closeOpen() {
        document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
            menu.classList.remove('show');
            var owner = document.querySelector('[data-dropdown="' + menu.id + '"]');
            if (owner) owner.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown]');
        if (toggle) {
            var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
            if (menu) {
                var open = menu.classList.toggle('show');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            return;
        }

        closeOpen();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeOpen();
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/bir-forms/index.blade.php ENDPATH**/ ?>