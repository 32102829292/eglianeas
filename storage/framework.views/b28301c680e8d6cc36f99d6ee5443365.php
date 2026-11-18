<?php if(! empty($payments['has'])): ?>
    <div class="cell-payments">
        <div class="cell-payments-title">Payment Details</div>
        <table class="cell-pmethod">
            <tr>
                <td class="cell-pinfo">
                    <?php if($gcashNumber !== ''): ?>
                        <div class="cell-pline"><b>GCash</b> · <?php echo e($gcashNumber); ?></div>
                    <?php endif; ?>
                    <?php $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="cell-pline"><?php echo e($bank['label']); ?></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </td>
                <td class="cell-pqr">
                    <?php if($gcashQr !== null): ?>
                        <img src="<?php echo e($gcashQr); ?>" alt="GCash QR Code">
                    <?php endif; ?>
                    <?php $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(! empty($bank['qr'])): ?>
                            <img src="<?php echo e($bank['qr']); ?>" alt="<?php echo e($bank['bank_name'] ?: 'Bank'); ?> QR Code">
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </td>
            </tr>
        </table>
        <?php if(in_array($billing->id, $overflowIds, true)): ?>
            <div class="cell-oversize">NOTE: this statement is taller than one page and continues onto the next page.</div>
        <?php endif; ?>
    </div>
<?php endif; ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/admin/billing/partials/payment-details.blade.php ENDPATH**/ ?>