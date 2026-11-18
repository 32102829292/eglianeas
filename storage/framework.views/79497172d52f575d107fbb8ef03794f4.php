<?php $__env->startSection('title', 'Clients — Egliane Accounting Services'); ?>

<?php
    $canManageCor = auth()->user()->isAdmin() || auth()->user()->isSupervisor();
    $maskTin = function (?string $value): string {
        if (! $value) { return ''; }
        $clean = preg_replace('/\D/', '', $value) ?? '';
        return str_repeat('X', max(strlen($clean) - 3, 0)).substr($clean, -3);
    };

    // Filter-preserving URLs for the active-filter chips. Changing a filter drops
    // the page number so the user lands on page 1 of the new result set.
    $resetUrl = route('admin.clients.index');
    $queryParams = request()->query();
    $searchRemoveUrl = route('admin.clients.index', array_diff_key($queryParams, ['q' => null, 'page' => null]));
    $birRemoveUrls = [];
    foreach ($selectedBirCodes as $selectedCode) {
        $params = array_diff_key($queryParams, ['bir_codes' => null, 'page' => null]);
        $remaining = array_values(array_diff($selectedBirCodes, [$selectedCode]));
        if ($remaining !== []) { $params['bir_codes'] = $remaining; }
        $birRemoveUrls[$selectedCode] = route('admin.clients.index', $params);
    }
    $birFilterSummary = $selectedBirCodes === [] ? 'All codes' : count($selectedBirCodes).' selected';

    // Admins and supervisors may create new BIR form codes; this mirrors the
    // authorization enforced by BirFormTypeController@store so the action is
    // never offered to a user the store endpoint would reject with a 403.
    $canManageBirCodes = (bool) auth()->user()?->canManageBirCodes();
?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>Clients</h1>
            <p>Manage client accounts, their information, and account status.</p>
        </div>
        <div class="page-head-actions">
            <?php if(auth()->user()->canManageClients()): ?>
                <a href="<?php echo e(route('admin.clients.create')); ?>" class="btn btn-primary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Client
                </a>
            <?php endif; ?>
            <form method="GET" action="<?php echo e(route('admin.clients.index')); ?>" class="page-search" id="client-filter-form">
                <?php if($sort !== 'business_name'): ?>
                    <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
                <?php endif; ?>
                <?php if($direction !== 'asc'): ?>
                    <input type="hidden" name="direction" value="<?php echo e($direction); ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search name, business, email, or TIN&hellip;" data-live-filter>
                <div class="dropdown-wrap">
                    <button type="button" class="btn btn-outline btn-sm dropdown-toggle bir-filter-toggle" data-dropdown="bir-code-filter" aria-haspopup="true" aria-expanded="false">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18l-7 8v7l-4 2v-9L3 4z"/></svg>
                        BIR Codes
                        <span class="bir-filter-count" data-bir-summary><?php echo e($birFilterSummary); ?></span>
                    </button>
                    <div class="dropdown-menu bir-filter-menu" id="bir-code-filter">
                        <div class="bir-filter-head">Show clients assigned these BIR codes</div>
                        <div class="bir-filter-grid">
                            <?php $__empty_1 = true; $__currentLoopData = $birCodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $birCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php ($birChecked = in_array($birCode, $selectedBirCodes, true)); ?>
                                <label class="bir-form-check <?php echo e($birChecked ? 'is-checked' : ''); ?>">
                                    <input type="checkbox" name="bir_codes[]" value="<?php echo e($birCode); ?>" <?php if($birChecked): echo 'checked'; endif; ?> data-bir-code>
                                    <span><?php echo e($birCode); ?></span>
                                </label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <p class="bir-filter-empty">No BIR codes are configured yet.</p>
                            <?php endif; ?>
                        </div>
                        <div class="bir-filter-foot">
                            <button type="button" class="btn btn-link btn-sm" data-bir-clear>Clear</button>
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        </div>
                    </div>
                </div>
                <?php if($canManageBirCodes): ?>
                    <button type="button" class="btn btn-outline btn-sm bir-add-code-btn" data-bs-toggle="modal" data-bs-target="#addBirCodeModal" title="Create a new BIR form code">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add BIR Code
                    </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </form>
            <div class="dropdown-wrap">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-dropdown="download-menu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Masterlist
                </button>
                <div class="dropdown-menu" id="download-menu">
                    <a href="<?php echo e(route('admin.clients.exportXlsx', ['q' => $q])); ?>" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as XLSX
                    </a>
                    <a href="<?php echo e(route('admin.clients.exportPdf', ['q' => $q])); ?>" class="dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-data">
        <div class="card-head">
            <span class="card-title"><?php echo e($hasActiveFilters ? 'Matching Clients' : 'All Clients'); ?> <span class="count-pill"><?php echo e($clients->total()); ?></span></span>
            <div class="sort-controls" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <label class="form-label mb-0" style="font-size: 12px;">Sort by:</label>
                <form method="GET" action="<?php echo e(route('admin.clients.index')); ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                    <?php $__currentLoopData = ['q' => $q]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php $__currentLoopData = $selectedBirCodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $selectedCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <input type="hidden" name="bir_codes[]" value="<?php echo e($selectedCode); ?>">
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <select class="form-control form-control-sm" name="sort" style="width: auto; min-width: 160px;" onchange="this.form.submit()">
                        <option value="business_name" <?php echo e($sort === 'business_name' ? 'selected' : ''); ?>>Business Name</option>
                        <option value="name" <?php echo e($sort === 'name' ? 'selected' : ''); ?>>Client Name</option>
                        <option value="client_code" <?php echo e($sort === 'client_code' ? 'selected' : ''); ?>>Client ID</option>
                        <option value="email" <?php echo e($sort === 'email' ? 'selected' : ''); ?>>Email</option>
                        <option value="contact_no" <?php echo e($sort === 'contact_no' ? 'selected' : ''); ?>>Contact Number</option>
                        <option value="business_type" <?php echo e($sort === 'business_type' ? 'selected' : ''); ?>>Type of Business</option>
                        <option value="line_of_business" <?php echo e($sort === 'line_of_business' ? 'selected' : ''); ?>>Line of Business</option>
                        <option value="date_started" <?php echo e($sort === 'date_started' ? 'selected' : ''); ?>>Date Started</option>
                        <option value="created_at" <?php echo e($sort === 'created_at' ? 'selected' : ''); ?>>Date Registered</option>
                    </select>
                    <select class="form-control form-control-sm" name="direction" style="width: auto; min-width: 100px;" onchange="this.form.submit()">
                        <option value="asc" <?php echo e($direction === 'asc' ? 'selected' : ''); ?>>A–Z / Oldest–Newest</option>
                        <option value="desc" <?php echo e($direction === 'desc' ? 'selected' : ''); ?>>Z–A / Newest–Oldest</option>
                    </select>
                </form>
            </div>
        </div>
        <?php if($hasActiveFilters): ?>
            <div class="active-filter-row">
                <span class="active-filter-label">Active filters:</span>
                <?php if($q !== ''): ?>
                    <a href="<?php echo e($searchRemoveUrl); ?>" class="filter-chip" title="Remove the search filter">
                        Search: <?php echo e($q); ?><span class="filter-chip-x" aria-hidden="true">&times;</span>
                    </a>
                <?php endif; ?>
                <?php $__currentLoopData = $selectedBirCodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $selectedCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e($birRemoveUrls[$selectedCode]); ?>" class="filter-chip" title="Remove the <?php echo e($selectedCode); ?> filter">
                        BIR: <?php echo e($selectedCode); ?><span class="filter-chip-x" aria-hidden="true">&times;</span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($resetUrl); ?>" class="btn btn-outline btn-sm">Clear filters</a>
            </div>
        <?php endif; ?>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 clients-table">
                <colgroup>
                    <col class="col-business">
                    <col class="col-bir">
                    <col class="col-cor">
                    <col class="col-contact">
                    <col class="col-status">
                    <col class="col-payment">
                    <col class="col-outstanding">
                    <col class="col-since">
                    <col class="col-actions">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Business</th>
                        <th>BIR Codes</th>
                        <th>COR</th>
                        <th>Contact</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Payment</th>
                        <th class="text-end">Outstanding</th>
                        <th>Since</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($client = $entry['user']); ?>
                        <tr data-filter-row>
                            <td data-col="Business">
                                <div class="fw-semibold"><?php echo e($client->business_name ?: $client->name); ?></div>
                                <small class="muted"><?php echo e($entry['profile']?->line_of_business ?? $entry['profile']?->business_type ?? '—'); ?></small>
                                <?php if($entry['profile']?->tin_no): ?>
                                    <small class="muted d-block">TIN <?php echo e($maskTin($entry['profile']->tin_no)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-col="BIR Codes">
                                <?php if($entry['bir_codes']): ?>
                                    <div class="bir-chips bir-chips-list">
                                        <?php $__currentLoopData = $entry['bir_codes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entryBirCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <span class="bir-chip bir-chip-<?php echo e($entryBirCode['status']); ?>" title="<?php echo e($entryBirCode['label']); ?>"><?php echo e($entryBirCode['code']); ?></span>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="Certificate of Registration">
                                <?php if($entry['has_cor']): ?>
                                    <span class="badge badge-success">Available</span>
                                    <?php if($canManageCor): ?>
                                        <a href="<?php echo e(route('admin.clients.cor.view', $client)); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm ms-1">View</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">Not uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="Contact">
                                <div class="fw-semibold"><?php echo e($client->name); ?></div>
                                <small class="muted"><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a></small>
                                <?php if($entry['profile']?->contact_no_tel): ?>
                                    <small class="muted d-block"><a href="tel:<?php echo e($entry['profile']->contact_no_tel); ?>" class="contact-link"><?php echo e($entry['profile']->contact_no); ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" data-col="Status">
                                <?php ($s = $entry['status']); ?>
                                <span class="badge <?php if($s==='current'): ?> badge-success <?php elseif($s==='delinquent'): ?> badge-warn <?php elseif($s==='critical'): ?> badge-danger <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($statuses[$s] ?? $s); ?></span>
                            </td>
                            <td class="text-center" data-col="Payment">
                                <?php if($entry['payment_status']): ?>
                                    <?php ($p = $entry['payment_status']); ?>
                                    <span class="badge <?php if($p==='paid'): ?> badge-success <?php elseif($p==='unpaid'): ?> badge-danger <?php elseif($p==='partial'): ?> badge-warn <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e(ucfirst($p)); ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" data-col="Outstanding">
                                <?php if($entry['outstanding'] > 0): ?>
                                    <span class="text-danger fw-semibold">₱<?php echo e(number_format($entry['outstanding'], 2)); ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="Since"><?php echo e($entry['profile']?->date_started?->format('M j, Y') ?? '—'); ?></td>
                            <td class="text-end" data-col="Actions">
                                <div class="client-actions">
                                    <a href="<?php echo e(route('admin.clients.show', $client)); ?>" class="btn btn-primary btn-sm">Open client</a>
                                    <?php if(auth()->user()->canManageClients()): ?>
                                        <a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="btn btn-outline btn-sm">Edit</a>
                                        <span class="actions-divider"></span>
                                        <div class="dropdown-wrap">
                                            <button type="button" class="btn btn-outline btn-sm icon-btn" data-dropdown="client-more-<?php echo e($client->id); ?>-t" aria-label="More actions" title="More actions">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                            </button>
                                            <div class="dropdown-menu" id="client-more-<?php echo e($client->id); ?>-t">
                                                <form method="POST" action="<?php echo e(route('admin.clients.impersonate', $client)); ?>" onsubmit="return egliane.confirm.form(this, { title: 'View application as <?php echo e(addslashes($client->business_name ?: $client->name)); ?>?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item btn-item">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                        Login as client
                                                    </button>
                                                </form>
                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="<?php echo e(route('admin.clients.destroy', $client)); ?>" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit" class="dropdown-item btn-item dropdown-item-danger">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                        Delete client
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                                <td colspan="9" class="empty-cell">
                                    <?php if($hasActiveFilters): ?>
                                        No clients match the current filters.
                                        <a href="<?php echo e($resetUrl); ?>" class="btn btn-outline btn-sm">Clear filters</a>
                                    <?php else: ?>
                                        No clients found.
                                    <?php endif; ?>
                                </td>
                            </tr>
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
                                <div class="cv-head-sub"><?php echo e($entry['profile']?->line_of_business ?? $entry['profile']?->business_type ?? '—'); ?><?php echo e($entry['profile']?->tin_no ? ' · TIN '.$maskTin($entry['profile']->tin_no) : ''); ?></div>
                            </div>
                            <?php ($s = $entry['status']); ?>
                            <span class="badge <?php if($s==='current'): ?> badge-success <?php elseif($s==='delinquent'): ?> badge-warn <?php elseif($s==='critical'): ?> badge-danger <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e($statuses[$s] ?? $s); ?></span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair"><span class="cv-label">Contact</span><span class="cv-value"><?php echo e($client->name); ?><br><a href="mailto:<?php echo e($client->email); ?>" class="contact-link"><?php echo e($client->email); ?></a><?php if($entry['profile']?->contact_no_tel): ?><br><a href="tel:<?php echo e($entry['profile']->contact_no_tel); ?>" class="contact-link"><?php echo e($entry['profile']->contact_no); ?></a><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">BIR Codes</span><span class="cv-value"><?php if($entry['bir_codes']): ?><div class="bir-chips"><?php $__currentLoopData = $entry['bir_codes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entryBirCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="bir-chip bir-chip-<?php echo e($entryBirCode['status']); ?>" title="<?php echo e($entryBirCode['label']); ?>"><?php echo e($entryBirCode['code']); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php else: ?><span class="muted">&mdash;</span><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Payment</span><span class="cv-value"><?php if($entry['payment_status']): ?><?php ($p = $entry['payment_status']); ?><span class="badge <?php if($p==='paid'): ?> badge-success <?php elseif($p==='unpaid'): ?> badge-danger <?php elseif($p==='partial'): ?> badge-warn <?php else: ?> badge-neutral <?php endif; ?>"><?php echo e(ucfirst($p)); ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Certificate of Registration</span><span class="cv-value"><?php if($entry['has_cor']): ?><span class="badge badge-success">Available</span><?php if($canManageCor): ?><a href="<?php echo e(route('admin.clients.cor.view', $client)); ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm ms-1">View</a><?php endif; ?> <?php else: ?><span class="muted">Not uploaded</span><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Outstanding</span><span class="cv-value"><?php if($entry['outstanding'] > 0): ?><span class="text-danger fw-semibold">₱<?php echo e(number_format($entry['outstanding'], 2)); ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Since</span><span class="cv-value"><?php echo e($entry['profile']?->date_started?->format('M j, Y') ?? '—'); ?></span></div>
                        </div>
                        <div class="cv-card-actions">
                            <a href="<?php echo e(route('admin.clients.show', $client)); ?>" class="btn btn-primary btn-sm">Open client</a>
                            <?php if(auth()->user()->canManageClients()): ?>
                                <a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="btn btn-outline btn-sm">Edit</a>
                                <span class="actions-divider"></span>
                                <div class="dropdown-wrap">
                                    <button type="button" class="btn btn-outline btn-sm icon-btn" data-dropdown="client-more-<?php echo e($client->id); ?>-c" aria-label="More actions" title="More actions">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                    </button>
                                    <div class="dropdown-menu" id="client-more-<?php echo e($client->id); ?>-c">
                                        <form method="POST" action="<?php echo e(route('admin.clients.impersonate', $client)); ?>" onsubmit="return egliane.confirm.form(this, { title: 'View application as <?php echo e(addslashes($client->business_name ?: $client->name)); ?>?', message: 'You can exit anytime from the top banner.', confirmLabel: 'Login as Client' });">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="dropdown-item btn-item">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                Login as client
                                            </button>
                                        </form>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="<?php echo e(route('admin.clients.destroy', $client)); ?>" onsubmit="return egliane.confirm.form(this, { title: 'Delete this client?', message: 'Are you sure you want to delete this client? This action can be undone by support.', danger: true, confirmLabel: 'Delete' });">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="dropdown-item btn-item dropdown-item-danger">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                Delete client
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">
                        <?php if($hasActiveFilters): ?>
                            No clients match the current filters. <a href="<?php echo e($resetUrl); ?>" class="btn btn-outline btn-sm">Clear filters</a>
                        <?php else: ?>
                            No clients found.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($clients->links('pagination.simple')); ?>

    </div>

    <?php if($canManageBirCodes): ?>
        
        <div class="modal fade" id="addBirCodeModal" tabindex="-1" aria-labelledby="addBirCodeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="<?php echo e(route('admin.bir-form-types.store')); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="addBirCodeModalLabel">Add BIR Code</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="form-hint">Creates a new BIR form code that can be assigned to clients and used on billing statements. It does not change any existing statement.</p>
                            <div class="form-group">
                                <label class="form-label" for="bir_code_code">Form Code <span class="text-danger">*</span></label>
                                <input class="form-control" id="bir_code_code" name="code" type="text" maxlength="30" required placeholder="e.g. 1800">
                                <div class="form-hint">Unique code/number for the BIR form (e.g., 1701, 2551Q, 1800).</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="bir_code_name">Form Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="bir_code_name" name="name" type="text" maxlength="255" required placeholder="e.g. BIR Form 1800">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="bir_code_description">Description</label>
                                <textarea class="form-control" id="bir_code_description" name="description" rows="3" maxlength="1000" placeholder="Brief description of this form"></textarea>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="bir_code_active" name="active" value="1" checked>
                                <label class="form-check-label" for="bir_code_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add BIR Code</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('input', function (e) {
            if (!e.target.matches('[data-live-filter]')) return;
            var term = e.target.value.toLowerCase();
            document.querySelectorAll('[data-filter-row]').forEach(function (row) {
                row.hidden = term !== '' && row.textContent.toLowerCase().indexOf(term) === -1;
            });
        });
        // Keep aria-expanded truthful for every dropdown on the page, whichever
        // branch above opened or closed the menu.
        var syncDropdowns = function () {
            document.querySelectorAll('[data-dropdown]').forEach(function (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                toggle.setAttribute('aria-expanded', menu && menu.style.display === 'block' ? 'true' : 'false');
            });
        };
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-dropdown]');
            if (toggle) {
                var menu = document.getElementById(toggle.getAttribute('data-dropdown'));
                document.querySelectorAll('.dropdown-menu').forEach(function (m) {
                    if (m !== menu) m.style.display = 'none';
                });
                if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                syncDropdowns();
                return;
            }
            if (e.target.closest('.dropdown-menu')) return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
            syncDropdowns();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.dropdown-menu').forEach(function (m) { m.style.display = 'none'; });
            syncDropdowns();
        });

        // BIR code multi-select. Checkboxes do not auto-submit so several codes
        // can be ticked in one pass; Apply sends them with the rest of the form.
        var filterForm = document.getElementById('client-filter-form');
        if (filterForm) {
            var summary = filterForm.querySelector('[data-bir-summary]');
            var syncSummary = function () {
                if (!summary) return;
                var count = filterForm.querySelectorAll('[data-bir-code]:checked').length;
                summary.textContent = count === 0 ? 'All codes' : count + ' selected';
            };
            filterForm.addEventListener('change', function (e) {
                if (e.target.matches('[data-bir-code]')) syncSummary();
            });
            var clearBir = filterForm.querySelector('[data-bir-clear]');
            if (clearBir) {
                clearBir.addEventListener('click', function () {
                    filterForm.querySelectorAll('[data-bir-code]').forEach(function (cb) { cb.checked = false; });
                    syncSummary();
                    filterForm.submit();
                });
            }
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/clients/index.blade.php ENDPATH**/ ?>