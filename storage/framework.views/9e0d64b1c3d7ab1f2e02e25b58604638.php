<?php
    $grouped = $billing->lineItems->groupBy('category');
    $remittances = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BIR_REMITTANCE, collect());
    $professionalFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_PROFESSIONAL_FEE, collect());
    $bookkeepingFees = $grouped->get(\App\Models\BillingLineItem::CATEGORY_BOOKKEEPING_FEE, collect());
    $postClosingTb = $grouped->get(\App\Models\BillingLineItem::CATEGORY_POST_CLOSING_TB, collect());
    $inventoryList = $grouped->get(\App\Models\BillingLineItem::CATEGORY_INVENTORY_LIST, collect());
    $otherAttachment = $grouped->get(\App\Models\BillingLineItem::CATEGORY_OTHER_ATTACHMENT, collect());
    $dataEntry = $grouped->get(\App\Models\BillingLineItem::CATEGORY_DATA_ENTRY, collect());
    $gcashNumber = \App\Models\Setting::get('gcash_number', '');
    $gcashQrCode = \App\Models\Setting::get('gcash_qr_code', '');
    $bankAccounts = \App\Models\Setting::get('bank_accounts', []);
    $hasPaymentInfo = $gcashNumber || $gcashQrCode || count($bankAccounts) > 0;
?>

<div class="statement">
    <?php ($statementCompany = $billing->company); ?>
    <?php ($statementClient = $billing->client); ?>
    <?php if($billing->isPaid()): ?>
        <div class="paid-stamp" aria-hidden="true">
            <div class="paid-stamp-line1">PAID</div>
            <div class="paid-stamp-line2"><?php echo e($billing->money($billing->total)); ?></div>
        </div>
    <?php endif; ?>

    <div class="statement-client">
        <?php echo e($statementCompany?->company_name ?: $billing->client?->business_name ?: $billing->client?->name); ?>

    </div>
    <div class="statement-head">
        <div class="statement-brand">EGLIANE ACCOUNTING SERVICES</div>
        <div class="statement-period"><?php echo e($billing->period_label); ?><?php if($statementClient?->client_code): ?> · Client ID <?php echo e($statementClient->client_code); ?><?php endif; ?>
            <?php if($statementCompany?->company_code): ?> · Company/Branch ID <?php echo e($statementCompany->company_code); ?><?php endif; ?></div>
    </div>
    <div class="statement-client-details">
        <div><strong>Surname, Name:</strong> <?php echo e($statementClient?->name); ?></div>
        <div><strong>Company Name:</strong> <?php echo e($statementCompany?->company_name ?: $statementClient?->business_name); ?></div>
        <div><strong>Email:</strong> <?php echo e($statementCompany?->business_email ?: $statementClient?->email); ?></div>
        <div><strong>Type of Business:</strong> <?php echo e($statementCompany?->business_type ?: $statementClient?->profile?->business_type); ?></div>
    </div>

    <?php if($remittances->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">BIR REMITTANCES</div>
            <?php $__currentLoopData = $remittances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($professionalFees->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">PROFESSIONAL FEES (FILING)</div>
            <?php $__currentLoopData = $professionalFees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($bookkeepingFees->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">BOOKKEEPING</div>
            <?php $__currentLoopData = $bookkeepingFees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($postClosingTb->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">POST-CLOSING TRIAL BALANCE</div>
            <?php $__currentLoopData = $postClosingTb; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($inventoryList->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">INVENTORY LIST (NOTARIZED)</div>
            <?php $__currentLoopData = $inventoryList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($otherAttachment->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">OTHER ATTACHMENT</div>
            <?php $__currentLoopData = $otherAttachment; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <?php if($dataEntry->isNotEmpty()): ?>
        <div class="statement-block">
            <div class="statement-block-title">DATA ENTRY</div>
            <?php $__currentLoopData = $dataEntry; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="statement-row">
                    <span><?php echo e($item->label); ?></span>
                    <span><?php echo e($billing->money($item->amount)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <div class="statement-total">
        <span>TOTAL AMOUNT:</span>
        <span><?php echo e($billing->money($billing->total)); ?></span>
    </div>

    <div class="statement-sign">HARRIS EGLIANE, CPA</div>

    <?php if($billing->due_date && ! $billing->isPaid()): ?>
        <div class="statement-paid-note">Due date: <?php echo e($billing->due_date->format('F j, Y')); ?></div>
    <?php endif; ?>

    <?php if($billing->isPaid()): ?>
        <div class="statement-paid-note">Date paid: <?php echo e($billing->paid_at?->format('F j, Y') ?? '—'); ?></div>
    <?php endif; ?>

    <?php if($hasPaymentInfo): ?>
        <div class="statement-divider"></div>
        <div class="statement-block-title">PAYMENT DETAILS</div>

        <?php if($gcashNumber || $gcashQrCode): ?>
            <div class="payment-method">
                <div class="payment-method-info">
                    <div class="payment-method-label">GCash</div>
                    <?php if($gcashNumber): ?>
                        <div class="payment-method-number"><a href="tel:<?php echo e($gcashNumber); ?>" class="contact-link"><?php echo e($gcashNumber); ?></a></div>
                    <?php endif; ?>
                </div>
                <?php if($gcashQrCode): ?>
                    <img src="<?php echo e(route('payment.image', 'gcash')); ?>" alt="GCash QR Code" class="payment-qr">
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(! empty($bank['bank_name']) || ! empty($bank['account_number'])): ?>
                <div class="payment-method">
                    <div class="payment-method-info">
                        <?php if(! empty($bank['bank_name'])): ?>
                            <div class="payment-method-label"><?php echo e($bank['bank_name']); ?></div>
                        <?php endif; ?>
                        <?php if(! empty($bank['account_name'])): ?>
                            <div class="payment-method-detail"><?php echo e($bank['account_name']); ?></div>
                        <?php endif; ?>
                        <?php if(! empty($bank['account_number'])): ?>
                            <div class="payment-method-number"><?php echo e($bank['account_number']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if(! empty($bank['bank_qr_code'])): ?>
                        <img src="<?php echo e(route('payment.image', ['type' => 'bank', 'index' => $i])); ?>" alt="<?php echo e($bank['bank_name'] ?? 'Bank'); ?> QR Code" class="payment-qr">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/partials/billing-statement.blade.php ENDPATH**/ ?>