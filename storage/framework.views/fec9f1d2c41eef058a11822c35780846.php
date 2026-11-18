<?php
    use Illuminate\Support\Str;
    $user = auth()->user();
    $routeName = request()->route() ? request()->route()->getName() : '';
    $active = function (string $prefix) use ($routeName): string {
        return Str::startsWith($routeName, $prefix) ? 'active' : '';
    };
    $bell = $user->notificationBellData();
    $unreadCount = $bell['unread'];
    $recentNotifications = $bell['recent'];
    $pendingClientCount = $user->isAdmin()
        ? \App\Models\User::query()
            ->where('role', \App\Models\User::ROLE_CLIENT)
            ->whereNull('approved_at')
            ->whereNull('declined_at')
            ->count()
        : 0;
?>
<!DOCTYPE html>
<html lang="en">
<?php echo $__env->make('layouts.head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<body class="dash-body">
    <?php if (isset($component)) { $__componentOriginal4d6bed2ebceb29e0a9932fbda627422a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4d6bed2ebceb29e0a9932fbda627422a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-loader','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-loader'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4d6bed2ebceb29e0a9932fbda627422a)): ?>
<?php $attributes = $__attributesOriginal4d6bed2ebceb29e0a9932fbda627422a; ?>
<?php unset($__attributesOriginal4d6bed2ebceb29e0a9932fbda627422a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4d6bed2ebceb29e0a9932fbda627422a)): ?>
<?php $component = $__componentOriginal4d6bed2ebceb29e0a9932fbda627422a; ?>
<?php unset($__componentOriginal4d6bed2ebceb29e0a9932fbda627422a); ?>
<?php endif; ?>
    <?php echo $__env->make('partials.impersonation-banner', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(! Str::startsWith($routeName, 'admin.chatbot')): ?>
        <?php echo $__env->make('partials.chatbot', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
    <div class="offline-banner" id="offlineBanner">You&rsquo;re offline &mdash; showing previously loaded data.</div>

    <div class="dash-topbar">
        <div class="dash-topbar-inner">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a href="<?php echo e(route('home')); ?>" class="brand">
                <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
                <span class="brand-name">Egliane<small>Accounting Services</small></span>
            </a>
            <div class="dash-topbar-right">
                <div class="dash-bell" id="bellWrap">
                    <button type="button" class="bell-btn" id="bellBtn" aria-label="Notifications" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if($pendingClientCount > 0): ?>
                            <span class="bell-badge" title="<?php echo e($pendingClientCount); ?> pending client approval<?php echo e($pendingClientCount === 1 ? '' : 's'); ?>"><?php echo e(min($pendingClientCount, 99)); ?></span>
                        <?php elseif($unreadCount > 0): ?>
                            <span class="bell-badge"><?php echo e(min($unreadCount, 99)); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="bell-dropdown" id="bellDropdown">
                        <div class="bell-head">
                            <span>Notifications
                                <?php if($unreadCount > 0): ?><span class="bell-head-count"><?php echo e(min($unreadCount, 99)); ?></span><?php endif; ?>
                            </span>
                            <?php if($unreadCount > 0): ?>
                                <form method="POST" action="<?php echo e(route('notifications.read-all')); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="link-btn">Mark all read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="bell-list">
                            <?php if($pendingClientCount > 0): ?>
                                <a href="<?php echo e(route('admin.clients.pending')); ?>" class="bell-item bell-item--approval unread">
                                    <div class="bell-item-title">Pending Client Approval</div>
                                    <div class="bell-item-body"><?php echo e($pendingClientCount); ?> client account<?php echo e($pendingClientCount === 1 ? '' : 's'); ?> waiting for approval.</div>
                                    <div class="bell-item-time">Review pending accounts</div>
                                </a>
                            <?php endif; ?>
                            <?php $__empty_1 = true; $__currentLoopData = $recentNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <a href="<?php echo e(route('notifications.open', $notification)); ?>" class="bell-item <?php if($notification->isUnread()): ?> unread <?php endif; ?>">
                                    <div class="bell-item-title"><?php echo e($notification->title); ?></div>
                                    <?php if($notification->body): ?>
                                        <div class="bell-item-body"><?php echo e(Str::limit($notification->body, 90)); ?></div>
                                    <?php endif; ?>
                                    <?php if(($notification->reminder_count ?? 1) > 1): ?>
                                        <div class="reminder-count">Reminded <?php echo e($notification->reminder_count); ?> times</div>
                                    <?php endif; ?>
                                    <div class="bell-item-time"><?php echo e($notification->created_at->diffForHumans()); ?></div>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="bell-empty">No notifications yet.</div>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo e(route('notifications.index')); ?>" class="bell-footer">View all notifications</a>
                    </div>
                </div>
                <a href="<?php echo e(route('help')); ?>" class="dash-help-btn" title="How to use this system" aria-label="Help guide">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </a>
                <div class="dash-user-chip">
                    <?php if($user->photoUrl()): ?>
                        <span class="avatar avatar-photo"><img src="<?php echo e($user->photoUrl()); ?>" alt="<?php echo e($user->name); ?>" loading="lazy"></span>
                    <?php else: ?>
                        <span class="avatar"><?php echo e(mb_strtoupper(mb_substr($user->name, 0, 1))); ?></span>
                    <?php endif; ?>
                    <span class="hidden-xs"><?php echo e($user->name); ?></span>
                    <span class="dash-role"><?php echo e(ucfirst($user->role)); ?></span>
                </div>
                <button type="button" class="btn btn-outline-light btn-sm hidden" data-install>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Install
                </button>
                <div class="ios-install-tip hidden" id="iosInstallTip" role="tooltip" aria-hidden="true">
                    <button type="button" class="ios-install-tip-close" id="iosInstallTipClose" aria-label="Dismiss">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                    <strong>Install the app</strong>
                    <span>Tap the <svg class="share-glyph" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-4 5h2v7h4V7h2l-4-5zM5 10H3v11h18V10h-2v9H5v-9z"/></svg> Share icon, then &ldquo;Add to Home Screen&rdquo;.</span>
                </div>
                <form method="POST" action="<?php echo e(route('logout')); ?>" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Log out?', message: 'You&rsquo;ll need to log in again to access your account.', confirmLabel: 'Log out' });">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-outline-light btn-sm">Log out</button>
                </form>
            </div>
        </div>
    </div>

    <div class="dash-drawer-backdrop" id="dashDrawerBackdrop" aria-hidden="true"></div>
    <nav class="dash-drawer" id="dashDrawer" aria-label="Navigation menu" aria-hidden="true" tabindex="-1">
        <div class="dash-drawer-head">
            <a href="<?php echo e(route('home')); ?>" class="brand">
                <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
                <span class="brand-name">Egliane<small>Accounting Services</small></span>
            </a>
            <button type="button" class="drawer-close" id="drawerClose" aria-label="Close menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <?php echo $__env->make('partials.dashboard-nav', ['pendingClientCount' => $pendingClientCount], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </nav>

    <div class="dash-layout">
        <aside class="dash-nav">
            <?php echo $__env->make('partials.dashboard-nav', ['pendingClientCount' => $pendingClientCount], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </aside>

        <script>
            /* Restore the sidebar/drawer scroll position before first paint so
               navigation doesn't jump back to the top. The value is saved by
               app.js ("Sidebar / drawer scroll persistence") under the key
               egliane_sidebar_scroll (shared by the desktop sidebar and the
               mobile drawer, which render the same nav partial). */
            (function () {
                var KEY = 'egliane_sidebar_scroll';
                var top = -1;
                try { top = parseInt(sessionStorage.getItem(KEY), 10); } catch (e) {}
                if (!(top > 0)) return;
                var nav = document.querySelector('.dash-nav');
                if (nav) nav.scrollTop = top;
                var drawer = document.getElementById('dashDrawer');
                if (drawer) drawer.scrollTop = top;
            })();
        </script>

        <main class="dash-main">
            
            <?php if(session('status')): ?>
                <div class="alert alert-success" role="status"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if(session('error')): ?>
                <div class="alert alert-error" role="alert"><?php echo e(session('error')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="alert alert-error" role="alert">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div><?php echo e($error); ?></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
            <?php echo e($slot ?? ''); ?>

        </main>
    </div>

    <?php if (isset($component)) { $__componentOriginalb23d8b8dfe9a2108996bc1e69255c04a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb23d8b8dfe9a2108996bc1e69255c04a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.onboarding','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('onboarding'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb23d8b8dfe9a2108996bc1e69255c04a)): ?>
<?php $attributes = $__attributesOriginalb23d8b8dfe9a2108996bc1e69255c04a; ?>
<?php unset($__attributesOriginalb23d8b8dfe9a2108996bc1e69255c04a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb23d8b8dfe9a2108996bc1e69255c04a)): ?>
<?php $component = $__componentOriginalb23d8b8dfe9a2108996bc1e69255c04a; ?>
<?php unset($__componentOriginalb23d8b8dfe9a2108996bc1e69255c04a); ?>
<?php endif; ?>

    <?php echo $__env->make('components.confirm-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="/js/confirm.js?v=1" defer></script>
    <script src="/js/app.js?v=12" defer></script>
    <script src="/js/auth.js?v=4" defer></script>
    <script src="/js/push.js?v=2" defer></script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/layouts/dashboard.blade.php ENDPATH**/ ?>