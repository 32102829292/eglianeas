<?php $__env->startSection('title', 'Team Journal Monitor'); ?>

<?php
    use Carbon\Carbon;
    $statusOptions = [
        '' => 'All Status',
        'submitted' => 'Submitted',
        'late' => 'Late',
        'missing' => 'Missing',
    ];
?>

<?php $__env->startSection('content'); ?>
<div class="page-head page-head-row">
    <div>
        <h1>Team Journal Monitor</h1>
        <p>View journal submissions for your team — <?php echo e($date->format('F j, Y')); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo e(route('admin.daily-journal.supervisor-monitor', ['date' => $date->copy()->subDay()->format('Y-m-d')])); ?>" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Previous
        </a>
        <a href="<?php echo e(route('admin.daily-journal.supervisor-monitor', ['date' => $date->copy()->addDay()->format('Y-m-d')])); ?>" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
            Next
        </a>
        <a href="<?php echo e(route('admin.daily-journal.supervisor-monitor')); ?>" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/></svg>
            Today
        </a>
    </div>
</div>

<form method="GET" action="<?php echo e(route('admin.daily-journal.supervisor-monitor')); ?>" class="filter-bar mb-4">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="date" value="<?php echo e($date->format('Y-m-d')); ?>">

    <div class="filter-group">
        <label for="status" class="form-label visually-hidden">Status</label>
        <select name="status" id="status" class="form-select filter-select">
            <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($value); ?>" <?php echo e($selectedStatus == $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="date" class="form-label visually-hidden">Date</label>
        <select name="date" id="date" class="form-select filter-select">
            <?php $__currentLoopData = $availableDates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableDate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($availableDate); ?>" <?php echo e($availableDate == $date->format('Y-m-d') ? 'selected' : ''); ?>>
                    <?php echo e(Carbon::parse($availableDate)->format('F j, Y')); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?php echo e(route('admin.daily-journal.supervisor-monitor', ['date' => $date->format('Y-m-d')])); ?>" class="btn btn-outline">Reset</a>
</form>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Role</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $journals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo e($journal->user->name); ?></div>
                        <small class="text-muted"><?php echo e($journal->user->email); ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo e($journal->user->role === 'supervisor' ? 'info' : 'secondary'); ?>">
                            <?php echo e(ucfirst($journal->user->role)); ?>

                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo e($journal->getStatusBadgeClass()); ?>">
                            <?php echo e($journal->getStatusIcon()); ?> <?php echo e($journal->getStatusLabel()); ?>

                        </span>
                    </td>
                    <td>
                        <?php if($journal->submitted_at): ?>
                            <?php echo e($journal->submitted_at->format('F j, Y g:i A')); ?>

                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo e(route('daily-journal.show', $journal)); ?>" class="btn btn-outline btn-sm" title="View details">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No journal entries found for this date.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if($journals->isNotEmpty()): ?>
    <div class="mt-4">
        <h4>Team Summary for <?php echo e($date->format('F j, Y')); ?></h4>
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card stat-ok">
                    <div class="stat-value"><?php echo e($journals->where('status', 'submitted')->count()); ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-warn">
                    <div class="stat-value"><?php echo e($journals->where('status', 'late')->count()); ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-danger">
                    <div class="stat-value"><?php echo e($journals->where('status', 'missing')->count()); ?></div>
                    <div class="stat-label">Missing</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-info">
                    <div class="stat-value"><?php echo e($journals->count()); ?></div>
                    <div class="stat-label">Total Team</div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
    background: var(--surface, #fff);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: 8px;
    padding: 1rem;
}
.filter-group {
    flex: 1;
    min-width: 180px;
}
.filter-select {
    font-size: 0.875rem;
}
.table td, .table th {
    vertical-align: middle;
}
.stat-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border-subtle, #e5e7eb);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--navy, #1e2a4a);
}
.stat-label {
    font-size: 0.875rem;
    color: var(--muted, #6b7280);
}
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/daily-journal/supervisor-monitor.blade.php ENDPATH**/ ?>