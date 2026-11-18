
<?php
    $bsmVariant = $variant ?? 'web';
    $bsmIsPdf = $bsmVariant === 'pdf';
    $bsmColumns = $matrix->columns();
    $bsmSpans = $matrix->groupSpans();
    $bsmRows = $matrix->rows();
    $bsmTotals = $matrix->columnTotals();
    $bsmColumnCount = count($bsmColumns);
    $bsmSubtotalCount = collect($bsmColumns)->where('subtotal', true)->count();
    $bsmRegularCount = $bsmColumnCount - 1 - $bsmSubtotalCount;
    $bsmClientWidth = 20;
    $bsmSubtotalWidth = 5.25;
    $bsmRegularWidth = $bsmRegularCount > 0
        ? (100 - $bsmClientWidth - ($bsmSubtotalCount * $bsmSubtotalWidth)) / $bsmRegularCount
        : 0;
?>

<table class="bsum <?php echo e($bsmIsPdf ? 'bsum-pdf' : 'bsum-web'); ?>">
    <colgroup>
        <col class="bsum-col-client" style="width: <?php echo e($bsmClientWidth); ?>%">
        <?php $__currentLoopData = $bsmColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bsmColumn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($bsmColumn['key'] !== 'client'): ?>
                <col class="<?php echo e($bsmColumn['subtotal'] ? 'bsum-col-sub' : 'bsum-col-num'); ?>" style="width: <?php echo e($bsmColumn['subtotal'] ? $bsmSubtotalWidth : $bsmRegularWidth); ?>%">
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </colgroup>
    <thead>
        
        <tr class="bsum-head-1">
            <th class="bsum-client-head" rowspan="2" scope="col">Client</th>
            <?php $__currentLoopData = $bsmSpans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $span): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($group === 'remittance'): ?>
                    <th class="bsum-group bsum-group-remit" colspan="<?php echo e($span); ?>" scope="colgroup">For Remittance</th>
                <?php elseif($group === 'fee'): ?>
                    <th class="bsum-group bsum-group-fee" colspan="<?php echo e($span); ?>" scope="colgroup">For Fee</th>
                <?php else: ?>
                    <th class="bsum-group bsum-group-other" colspan="<?php echo e($span); ?>" scope="colgroup"><?php echo e(ucfirst(str_replace('_', ' ', $group))); ?></th>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <th class="bsum-single bsum-single-total" rowspan="2" scope="col">Total</th>
            <th class="bsum-single bsum-single-grand" rowspan="2" scope="col">Grand Total</th>
        </tr>
        
        <tr class="bsum-head-2">
            <?php $__currentLoopData = $bsmColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bsmColumn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($bsmColumn['key'] === 'client' || $bsmColumn['group'] === null): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <th class="bsum-sub <?php echo e($bsmColumn['group'] === 'remittance' ? 'bsum-sub-remit' : 'bsum-sub-fee'); ?> <?php echo e($bsmColumn['subtotal'] ? 'bsum-subtotal' : ''); ?>"
                    scope="col"><?php echo e($bsmColumn['label']); ?></th>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tr>
    </thead>
    <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $bsmRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bsmRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <th class="bsum-client" scope="row">
                    <?php if($bsmRow['client'] === ''): ?>
                        <span class="bsum-orphan" title="This statement's client record was removed">Client removed</span>
                    <?php else: ?>
                        <?php echo e($bsmRow['client']); ?>

                    <?php endif; ?>
                </th>
                <?php $__currentLoopData = $bsmColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bsmColumn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($bsmColumn['key'] === 'client'): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php ($bsmIsSub = $bsmColumn['subtotal']); ?>
                    <td class="bsum-num <?php echo e($bsmColumn['key'] === 'remittance_subtotal' ? 'bsum-cell-remit' : ($bsmColumn['key'] === 'fee_subtotal' ? 'bsum-cell-fee' : ($bsmColumn['key'] === 'grand_total' ? 'bsum-cell-grand' : ($bsmColumn['key'] === 'total' ? 'bsum-cell-total' : '')))); ?>">
                        <?php echo e(number_format((float) ($bsmRow[$bsmColumn['key']] ?? 0), 2)); ?>

                    </td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td class="bsum-empty" colspan="<?php echo e($bsmColumnCount); ?>">No billing statements found for this period.</td>
            </tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="bsum-total-row">
            <th class="bsum-total-label" scope="row">Total</th>
            <?php $__currentLoopData = $bsmColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bsmColumn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($bsmColumn['key'] === 'client'): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php ($bsmIsGrand = $bsmColumn['key'] === 'grand_total'); ?>
                <td class="bsum-num <?php echo e($bsmColumn['key'] === 'remittance_subtotal' ? 'bsum-cell-remit' : ($bsmColumn['key'] === 'fee_subtotal' ? 'bsum-cell-fee' : ($bsmIsGrand ? 'bsum-cell-grand' : ''))); ?>">
                    <?php echo e(number_format((float) ($bsmTotals[$bsmColumn['key']] ?? 0), 2)); ?>

                </td>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tr>
    </tfoot>
</table>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/partials/summary-table.blade.php ENDPATH**/ ?>