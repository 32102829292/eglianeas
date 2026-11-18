<?php $__env->startSection('title', 'Verify your email — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <h1 class="auth-title">Verify your email</h1>

    <?php if(session('status')): ?>
        <div class="alert alert-success"><?php echo e(session('status')); ?></div>
    <?php endif; ?>

    <?php if(! empty($noPending)): ?>
        <p class="auth-sub">We couldn&rsquo;t find a pending registration. Please create an account first.</p>
        <a href="<?php echo e(route('register')); ?>" class="btn btn-primary btn-block mt-2">Create an account</a>
    <?php elseif(! empty($alreadyVerified)): ?>
        <p class="auth-sub">Your email is already verified.</p>
        <a href="<?php echo e(route('login')); ?>" class="btn btn-primary btn-block mt-2">Log in</a>
    <?php else: ?>
        <p class="auth-sub">We sent a 6-digit code to <b><?php echo e($email); ?></b>. Enter it below to activate your account.</p>

        <form method="POST" action="<?php echo e(route('verify.account')); ?>" id="verifyCodeForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="code" id="codeValue">
            <div class="code-inputs">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <input class="code-input" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit <?php echo e($i + 1); ?>">
                <?php endfor; ?>
            </div>
            <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="form-error text-center"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </form>

        <?php if(app()->environment('local') && ! empty($devCode)): ?>
            <button type="button" class="btn btn-outline btn-sm mt-2" id="devCodeBtn" data-code="<?php echo e($devCode); ?>">Use demo code (<?php echo e($devCode); ?>)</button>
        <?php endif; ?>

        <div class="resend-row">
            <span>Didn&rsquo;t get the code?</span>
            <button type="button" class="link" id="resendBtn" data-url="<?php echo e(route('verify.resend')); ?>">Resend</button>
            <span id="resendCountdown"></span>
        </div>

        <a href="<?php echo e(route('register')); ?>" class="btn btn-outline btn-block mt-2">Start over</a>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.body.setAttribute('data-cooldown-until', '<?php echo e($cooldownUntil ?? 0); ?>');
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/auth/verify-account.blade.php ENDPATH**/ ?>