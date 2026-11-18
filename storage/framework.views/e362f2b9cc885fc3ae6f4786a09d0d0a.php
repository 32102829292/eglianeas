<?php $__env->startSection('title', 'Service Tracker — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-head page-head-row">
        <div>
            <h1>Service Tracker</h1>
            <p>Track service completion across all clients and staff.</p>
        </div>
        <div class="page-head-actions">
            <?php if(auth()->user()->isAdmin()): ?>
                <a href="<?php echo e(route('admin.service-tracker.summary')); ?>" class="btn btn-outline btn-sm">Summary</a>
                <a href="<?php echo e(route('admin.service-tracker.create')); ?>" class="btn btn-primary">New instance</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-grid cols-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <span class="stat-label">Total Services</span>
            <b class="stat-value"><?php echo e($stats['total']); ?></b>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-9-9"/><path d="M21 3v6h-6"/></svg>
            </div>
            <span class="stat-label">In Progress</span>
            <b class="stat-value"><?php echo e($stats['inProgress']); ?></b>
        </div>
        <div class="stat-card stat-warn">
            <div class="stat-icon stat-icon-warn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-label">On Hold</span>
            <b class="stat-value"><?php echo e($stats['onHold']); ?></b>
        </div>
        <div class="stat-card stat-ok">
            <div class="stat-icon stat-icon-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <span class="stat-label">Completed</span>
            <b class="stat-value"><?php echo e($stats['done']); ?></b>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="<?php echo e(route('admin.service-tracker.index')); ?>" class="filter-bar-form">
            <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search client or service&hellip;">
            <select name="service_id" onchange="this.form.submit()">
                <option value="">All services</option>
                <?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($st->id); ?>" <?php if($activeServiceId == $st->id): echo 'selected'; endif; ?>><?php echo e($st->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="todo" <?php if($activeStatus === 'todo'): echo 'selected'; endif; ?>>To Do</option>
                <option value="in_progress" <?php if($activeStatus === 'in_progress'): echo 'selected'; endif; ?>>In Progress</option>
                <option value="on_hold" <?php if($activeStatus === 'on_hold'): echo 'selected'; endif; ?>>On Hold</option>
                <option value="done" <?php if($activeStatus === 'done'): echo 'selected'; endif; ?>>Done</option>
            </select>
            <select name="staff" onchange="this.form.submit()">
                <option value="">All staff</option>
                <?php $__currentLoopData = $allStaff; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s); ?>" <?php if($activeStaff === $s): echo 'selected'; endif; ?>><?php echo e($s); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card">
        <?php $__errorArgs = ['action'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="form-error" style="margin-bottom:12px;"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <div class="table-wrap table-card-view">
            <table class="table table-hover align-middle mb-0 tracker-table">
                <colgroup>
                    <col style="width:13%">
                    <col style="width:12%">
                    <col style="width:9%">
                    <col style="width:15%">
                    <col style="width:9%">
                    <col style="width:9%">
                    <col style="width:9%">
                    <col style="width:24%">
                </colgroup>
                <thead class="thead-muted">
                    <tr>
                        <th>Service</th>
                        <th>Client</th>
                        <th class="text-center">Status</th>
                        <th>Assigned Staff</th>
                        <th>Started</th>
                        <th>Due</th>
                        <th>Completed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $instances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $instance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-col="Service">
                                <div class="fw-semibold"><?php echo e($instance->service?->name); ?></div>
                                <?php if($instance->notes): ?>
                                    <div class="cell-note" title="<?php echo e($instance->notes); ?>"><?php echo e($instance->notes); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-col="Client">
                                <div class="fw-semibold"><?php echo e($instance->client?->business_name ?: $instance->client?->name); ?></div>
                                <?php if($instance->client?->business_name && $instance->client?->name): ?>
                                    <small class="muted"><?php echo e($instance->client?->name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-col="Status" class="text-center">
                                <?php ($s = $instance->status); ?>
                                <span class="badge <?php echo e($badgeClasses[$s] ?? 'badge-neutral'); ?>"><?php echo e($instance->statusLabel()); ?></span>
                                <?php if($instance->assignments->count()): ?>
                                    <div><small class="muted"><?php echo e($instance->completionPercent()); ?>%</small></div>
                                <?php endif; ?>
                            </td>
                            <td data-col="Assigned Staff">
                                <?php $__empty_2 = true; $__currentLoopData = $instance->assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                    <form method="POST" action="<?php echo e(route('admin.service-tracker.toggle-assignment', $assignment)); ?>" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="staff-chip <?php echo e($assignment->completed ? 'is-done' : ''); ?>" title="Toggle assignment status">
                                            <?php echo e($assignment->displayName()); ?> <?php echo e($assignment->completed ? '✓' : '○'); ?>

                                        </button>
                                    </form>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="Started" class="td-date"><?php echo e($instance->date_started?->format('M j, Y') ?? '—'); ?></td>
                            <td data-col="Due" class="td-date"><?php echo e($instance->otherService?->due_date?->format('M j, Y') ?? '—'); ?></td>
                            <td data-col="Completed" class="td-date"><?php echo e($instance->date_completed?->format('M j, Y') ?? '—'); ?></td>
                            <td data-col="Actions" class="text-end">
                                <div class="dropdown-wrap tracker-more">
                                    <button type="button" class="more-btn"
                                            id="tmore-<?php echo e($instance->id); ?>-btn"
                                            data-dropdown="tmore-<?php echo e($instance->id); ?>"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="tmore-<?php echo e($instance->id); ?>"
                                            aria-label="More actions"
                                            title="More actions">
                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg>
                                    </button>
                                    <div class="dropdown-menu more-menu tracker-more-menu" id="tmore-<?php echo e($instance->id); ?>" role="menu" aria-labelledby="tmore-<?php echo e($instance->id); ?>-btn">
                                        <?php if($instance->status === 'todo'): ?>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.start', $instance)); ?>" role="none">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="dropdown-item btn-item" role="menuitem">Start</button>
                                            </form>
                                        <?php elseif($instance->status === 'in_progress'): ?>
                                            <div class="tracker-hold">
                                                <button type="button" class="dropdown-item btn-item" data-tracker-hold-open role="menuitem">Hold</button>
                                                <form method="POST" action="<?php echo e(route('admin.service-tracker.hold', $instance)); ?>" class="tracker-hold-form" data-tracker-hold-form hidden role="none">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="text" name="reason" class="form-control form-control-sm hold-input" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason">
                                                    <div class="tracker-hold-actions">
                                                        <button type="button" class="btn btn-link btn-sm" data-tracker-hold-cancel>Cancel</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">Hold</button>
                                                    </div>
                                                </form>
                                            </div>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.complete', $instance)); ?>" role="none">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="dropdown-item btn-item" role="menuitem">Complete</button>
                                            </form>
                                        <?php elseif($instance->status === 'on_hold'): ?>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.resume', $instance)); ?>" role="none">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="dropdown-item btn-item" role="menuitem">Resume</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if(auth()->user()->isAdmin()): ?>
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item btn-item"
                                                    data-reassign-open="<?php echo e($instance->id); ?>"
                                                    data-service="<?php echo e($instance->service?->name); ?>"
                                                    data-client="<?php echo e($instance->client?->business_name ?: $instance->client?->name); ?>"
                                                    data-current="<?php echo e($instance->assignments->first()?->displayName()); ?>"
                                                    data-current-id="<?php echo e($instance->assignments->first()?->staff_id); ?>"
                                                    data-action="<?php echo e(route('admin.service-tracker.update-assignment', $instance)); ?>"
                                                    role="menuitem">Change Staff</button>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="<?php echo e(route('admin.service-tracker.show', $instance)); ?>" class="dropdown-item" role="menuitem">History</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="8" class="empty-cell">No service instances yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="card-view-list">
                <?php $__empty_1 = true; $__currentLoopData = $instances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $instance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cv-card">
                        <div class="cv-card-head">
                            <div class="cv-head-main">
                                <div class="cv-head-title"><?php echo e($instance->service?->name); ?></div>
                                <div class="cv-head-sub"><?php echo e($instance->client?->business_name ?: $instance->client?->name); ?></div>
                            </div>
                            <?php ($s = $instance->status); ?>
                            <span class="badge <?php echo e($badgeClasses[$s] ?? 'badge-neutral'); ?>"><?php echo e($instance->statusLabel()); ?></span>
                        </div>
                        <div class="cv-card-body">
                            <div class="cv-pair cv-full"><span class="cv-label">Assigned Staff</span><span class="cv-value"><?php $__empty_2 = true; $__currentLoopData = $instance->assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><form method="POST" action="<?php echo e(route('admin.service-tracker.toggle-assignment', $assignment)); ?>" class="d-inline"><?php echo csrf_field(); ?><button type="submit" class="staff-chip <?php echo e($assignment->completed ? 'is-done' : ''); ?>" title="Toggle assignment status"><?php echo e($assignment->displayName()); ?> <?php echo e($assignment->completed ? '✓' : '○'); ?></button></form><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><span class="muted">—</span><?php endif; ?> <?php if($instance->assignments->count()): ?><small class="muted">&middot; <?php echo e($instance->completionPercent()); ?>%</small><?php endif; ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Started</span><span class="cv-value"><?php echo e($instance->date_started?->format('M j, Y') ?? '—'); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Due</span><span class="cv-value"><?php echo e($instance->otherService?->due_date?->format('M j, Y') ?? '—'); ?></span></div>
                            <div class="cv-pair"><span class="cv-label">Completed</span><span class="cv-value"><?php echo e($instance->date_completed?->format('M j, Y') ?? '—'); ?></span></div>
                            <?php if($instance->notes): ?>
                                <div class="cv-pair cv-full"><span class="cv-label">Notes</span><span class="cv-value"><?php echo e($instance->notes); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="cv-card-actions">
                                <div class="dropdown-wrap tracker-more">
                                    <button type="button" class="more-btn"
                                            id="cmore-<?php echo e($instance->id); ?>-btn"
                                            data-dropdown="cmore-<?php echo e($instance->id); ?>"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="cmore-<?php echo e($instance->id); ?>"
                                            aria-label="More actions"
                                            title="More actions">
                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg>
                                    </button>
                                    <div class="dropdown-menu more-menu tracker-more-menu" id="cmore-<?php echo e($instance->id); ?>" role="menu" aria-labelledby="cmore-<?php echo e($instance->id); ?>-btn">
                                        <?php if($instance->status === 'todo'): ?>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.start', $instance)); ?>" role="none"><?php echo csrf_field(); ?><button type="submit" class="dropdown-item btn-item" role="menuitem">Start</button></form>
                                        <?php elseif($instance->status === 'in_progress'): ?>
                                            <div class="tracker-hold">
                                                <button type="button" class="dropdown-item btn-item" data-tracker-hold-open role="menuitem">Hold</button>
                                                <form method="POST" action="<?php echo e(route('admin.service-tracker.hold', $instance)); ?>" class="tracker-hold-form" data-tracker-hold-form hidden role="none"><?php echo csrf_field(); ?><input type="text" name="reason" class="form-control form-control-sm hold-input" placeholder="Hold reason&hellip;" required maxlength="500" aria-label="Hold reason"><div class="tracker-hold-actions"><button type="button" class="btn btn-link btn-sm" data-tracker-hold-cancel>Cancel</button><button type="submit" class="btn btn-primary btn-sm">Hold</button></div></form>
                                            </div>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.complete', $instance)); ?>" role="none"><?php echo csrf_field(); ?><button type="submit" class="dropdown-item btn-item" role="menuitem">Complete</button></form>
                                        <?php elseif($instance->status === 'on_hold'): ?>
                                            <form method="POST" action="<?php echo e(route('admin.service-tracker.resume', $instance)); ?>" role="none"><?php echo csrf_field(); ?><button type="submit" class="dropdown-item btn-item" role="menuitem">Resume</button></form>
                                        <?php endif; ?>
                                        <?php if(auth()->user()->isAdmin()): ?>
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item btn-item"
                                                    data-reassign-open="<?php echo e($instance->id); ?>"
                                                    data-service="<?php echo e($instance->service?->name); ?>"
                                                    data-client="<?php echo e($instance->client?->business_name ?: $instance->client?->name); ?>"
                                                    data-current="<?php echo e($instance->assignments->first()?->displayName()); ?>"
                                                    data-current-id="<?php echo e($instance->assignments->first()?->staff_id); ?>"
                                                    data-action="<?php echo e(route('admin.service-tracker.update-assignment', $instance)); ?>"
                                                    role="menuitem">Change Staff</button>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="<?php echo e(route('admin.service-tracker.show', $instance)); ?>" class="dropdown-item" role="menuitem">History</a>
                                    </div>
                                </div>
                            </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="cv-card cv-empty">No service instances yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php echo e($instances->links('pagination.simple')); ?>

    </div>

    <?php if(auth()->user()->isAdmin()): ?>
        <div id="reassignModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="reassignModalTitle">
            <div class="modal-card reassign-card">
                <h3 id="reassignModalTitle">Change assigned staff</h3>
                <p>Choose the new staff member for this service. Service status, dates, progress, notes and history are preserved.</p>
                <form id="reassignForm" method="POST"
                      onsubmit="return (window.egliane && egliane.confirm.form(this, { title: 'Change assigned staff?', message: 'The assigned staff member will be updated for this service.', confirmLabel: 'Change Staff' })) !== false;">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <input type="hidden" name="instance_id" id="reassignInstanceId">
                    <div class="form-group">
                        <span class="form-label">Service</span>
                        <div class="reassign-static" id="reassignService"></div>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Client</span>
                        <div class="reassign-static" id="reassignClient"></div>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Current assigned staff</span>
                        <div class="reassign-static" id="reassignCurrent"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reassignStaff">New assigned staff</label>
                        <select class="form-control" name="staff_id" id="reassignStaff" required>
                            <option value="">&hellip;select staff&hellip;</option>
                        </select>
                        <div class="form-error hidden" id="reassignError"></div>
                    </div>
                    <div class="btn-group-row">
                        <button type="button" class="btn btn-outline" data-reassign-cancel>Cancel</button>
                        <button type="submit" class="btn btn-primary" id="reassignSubmitBtn">Change Staff</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    'use strict';

    var menus = Array.prototype.slice.call(document.querySelectorAll('.tracker-more .dropdown-menu'));

    function closeAll(except) {
        menus.forEach(function (menu) {
            if (menu === except) return;
            menu.style.display = 'none';
            var btn = document.querySelector('[data-dropdown="' + menu.id + '"]');
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

    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-tracker-hold-open]');
        var cancel = e.target.closest('[data-tracker-hold-cancel]');
        if (open) {
            var panel = open.closest('.tracker-hold');
            open.hidden = true;
            var form = panel.querySelector('[data-tracker-hold-form]');
            form.hidden = false;
            setTimeout(function () { form.querySelector('input').focus(); }, 0);
            return;
        }
        if (cancel) {
            var f = cancel.closest('[data-tracker-hold-form]');
            f.hidden = true;
            var p = f.closest('.tracker-hold');
            p.querySelector('[data-tracker-hold-open]').hidden = false;
        }
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php if(auth()->user()->isAdmin()): ?>
<?php $__env->startPush('styles'); ?>
<style>
    .reassign-card { max-width: 440px; }
    .reassign-card p { margin-bottom: 14px; }
    .reassign-static { font-size: 14px; font-weight: 600; color: var(--navy, #1B1B3A); padding-top: 6px; word-break: break-word; }
    .reassign-card .form-group { margin-bottom: 14px; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    'use strict';

    var STAFF_ACCOUNTS = <?php echo json_encode($staffAccounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(), 512) ?>;

    var modal = document.getElementById('reassignModal');
    if (!modal) return;

    var form = document.getElementById('reassignForm');
    var serviceEl = document.getElementById('reassignService');
    var clientEl = document.getElementById('reassignClient');
    var currentEl = document.getElementById('reassignCurrent');
    var staffSelect = document.getElementById('reassignStaff');
    var errorEl = document.getElementById('reassignError');
    var submitBtn = document.getElementById('reassignSubmitBtn');

    function renderStaffOptions(excludeId) {
        staffSelect.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '\u2026select staff\u2026';
        staffSelect.appendChild(placeholder);
        STAFF_ACCOUNTS.forEach(function (s) {
            if (excludeId !== null && String(s.id) === String(excludeId)) return;
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            staffSelect.appendChild(o);
        });
    }

    function closeModal() {
        modal.classList.add('hidden');
        form.dataset.submitting = '0';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Change Staff';
        errorEl.classList.add('hidden');
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-reassign-open]');
        if (openBtn) {
            document.getElementById('reassignInstanceId').value = openBtn.dataset.reassignOpen;
            serviceEl.textContent = openBtn.dataset.service || '\u2014';
            clientEl.textContent = openBtn.dataset.client || '\u2014';
            currentEl.textContent = openBtn.dataset.current || '\u2014';
            form.action = openBtn.dataset.action;
            renderStaffOptions(openBtn.dataset.currentId ? openBtn.dataset.currentId : null);
            staffSelect.value = '';
            form.dataset.submitting = '0';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Change Staff';
            errorEl.classList.add('hidden');
            modal.classList.remove('hidden');
            staffSelect.focus();
            var rowMenu = openBtn.closest('.dropdown-menu');
            if (rowMenu) rowMenu.style.display = 'none';
            return;
        }
        if (e.target.closest('[data-reassign-cancel]') || e.target.id === 'reassignModal') {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    form.addEventListener('submit', function () {
        if (form.dataset.submitting === '1') return false;
        if (!staffSelect.value) {
            errorEl.textContent = 'Please select a new staff member.';
            errorEl.classList.remove('hidden');
            staffSelect.focus();
            return false;
        }
        form.dataset.submitting = '1';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving\u2026';
        return true;
    });
})();
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/service-tracker/index.blade.php ENDPATH**/ ?>