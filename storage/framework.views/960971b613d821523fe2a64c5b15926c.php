<?php ($client = $billing->client); ?>
<?php ($company = $billing->company); ?>
<?php ($clientName = $company?->company_name ?: $client?->business_name ?: $client?->name); ?>
<div class="copy-tag"><?php echo e($copyLabel); ?></div>
<table class="stmt <?php echo e($density ?? 'normal'); ?>">
    <tr class="head-row">
        <td>
            <span class="brand">EGLIANE ACCOUNTING SERVICES</span><br>
            <span class="client"><?php echo e($clientName); ?></span><br>
            <?php if($client?->client_code): ?><span class="note">Client ID: <?php echo e($client->client_code); ?></span><br><?php endif; ?>
            <?php if($company?->company_code): ?><span class="note">Company/Branch ID: <?php echo e($company->company_code); ?></span><?php endif; ?>
        </td>
        <td class="amount">
            <?php if($billing->isPaid()): ?>
                <span class="paid-stamp">PAID</span><br>
            <?php endif; ?>
            <span class="period">BILLING STATEMENT<br><?php echo e(mb_strtoupper($billing->period_label)); ?></span>
        </td>
    </tr>
    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($items = $billing->lineItems->where('category', $category)->filter(fn ($i) => (float) $i->amount != 0.0)); ?>
        <?php if($items->isNotEmpty()): ?>
            <tr><td colspan="2" class="section-title"><?php echo e($title); ?></td></tr>
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="item-row">
                    <td><?php echo e($item->label); ?></td>
                    <td class="amount"><?php echo e($peso($item->amount)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <tr class="total-row">
        <td>TOTAL AMOUNT</td>
        <td class="amount"><?php echo e($peso($billing->total)); ?></td>
    </tr>
    <tr class="sign-row">
        <td>
            <span class="signer">HARRIS EGLIANE, CPA</span>
            <?php if($billing->isPaid()): ?>
                <span class="note"> &middot; Paid <?php echo e($billing->paid_at?->format('M j, Y')); ?></span>
            <?php elseif($billing->due_date): ?>
                <span class="note"> &middot; Due <?php echo e($billing->due_date->format('M j, Y')); ?></span>
            <?php endif; ?>
        </td>
        <td class="amount note">Ref #<?php echo e(str_pad((string) $billing->id, 5, '0', STR_PAD_LEFT)); ?></td>
    </tr>
</table>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/partials/statement-cell.blade.php ENDPATH**/ ?>