<?php $__env->startSection('title', 'Confirm Password — Egliane Accounting Services'); ?>

<?php $__env->startSection('content'); ?>
    <h1 class="auth-title">Confirm your password</h1>
    <p class="auth-sub">This is a secure area of the app. Please confirm your password to continue.</p>

    <form method="POST" action="<?php echo e(route('password.confirm')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="form-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block mt-4">Confirm</button>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/auth/confirm-password.blade.php ENDPATH**/ ?>