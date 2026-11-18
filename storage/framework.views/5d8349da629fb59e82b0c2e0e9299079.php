<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['segments' => [], 'size' => 128, 'thickness' => 12, 'showTotalLabel' => false, 'legend' => 'bottom', 'showPct' => false, 'emptyText' => 'No billing data yet.']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['segments' => [], 'size' => 128, 'thickness' => 12, 'showTotalLabel' => false, 'legend' => 'bottom', 'showPct' => false, 'emptyText' => 'No billing data yet.']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    static $donutIdx = 0;
    $uid = 'dc-' . $donutIdx++;
    $total = collect($segments)->sum('value');
    $hasData = $total > 0;
    $radius = ($size - $thickness) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset = 0;
    // Lighter tint (65% toward white) paired with each segment's full color,
    // used as the soft start of the gradient. Order follows $segments.
    $tints = [
        '#B3E3C7', // --success  (Paid)
        '#FADBC0', // --warning  (Pending)
        '#AFAFBA', // --navy     (Unpaid)
        '#F7C0BB', // --danger   (Overdue)
    ];
    $ti = 0;
?>

<div class="donut-chart <?php echo e($legend === 'right' ? 'is-legend-right' : ''); ?>">
    <?php if($hasData): ?>
        <?php if($legend === 'right'): ?><div class="donut-figure"><?php endif; ?>
        <svg width="<?php echo e($size); ?>" height="<?php echo e($size); ?>" viewBox="0 0 <?php echo e($size); ?> <?php echo e($size); ?>">
            <defs>
                <?php $__currentLoopData = $segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $seg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if((int) $seg['value'] <= 0): ?> <?php continue; ?> <?php endif; ?>
                    <linearGradient id="<?php echo e($uid); ?>-<?php echo e($loop->index); ?>" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="<?php echo e($seg['tint'] ?? ($tints[$ti] ?? '#FFFFFF')); ?>"></stop>
                        <stop offset="100%" stop-color="<?php echo e($seg['color']); ?>"></stop>
                    </linearGradient>
                    <?php $ti++; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </defs>
            <g transform="rotate(-90 <?php echo e($size / 2); ?> <?php echo e($size / 2); ?>)">
                <?php $__currentLoopData = $segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if((int) $seg['value'] <= 0): ?> <?php continue; ?> <?php endif; ?>
                    <?php
                        $fraction = $seg['value'] / $total;
                        $dash = $fraction * $circumference;
                        $gap = $circumference - $dash;
                    ?>
                    <circle
                        cx="<?php echo e($size / 2); ?>" cy="<?php echo e($size / 2); ?>" r="<?php echo e($radius); ?>"
                        fill="none" stroke="url(#<?php echo e($uid); ?>-<?php echo e($loop->index); ?>)" stroke-width="<?php echo e($thickness); ?>"
                        stroke-linecap="round"
                        stroke-dasharray="<?php echo e(round($dash, 2)); ?> <?php echo e(round($gap, 2)); ?>"
                        stroke-dashoffset="<?php echo e(round(-$offset, 2)); ?>"
                    ></circle>
                    <?php $offset += $dash; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </g>
            <?php if($showTotalLabel): ?>
                <text x="50%" y="46%" text-anchor="middle" class="donut-chart-total"><?php echo e($total); ?></text>
                <text x="50%" y="58%" text-anchor="middle" class="donut-chart-total-label">Total</text>
            <?php else: ?>
                <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" class="donut-chart-total"><?php echo e($total); ?></text>
            <?php endif; ?>
        </svg>
        <?php if($legend === 'right'): ?></div><?php endif; ?>
        <ul class="donut-chart-legend list-unstyled d-flex flex-wrap gap-3 <?php echo e($legend === 'right' ? 'legend-right' : 'justify-content-center'); ?> mb-0">
            <?php $__currentLoopData = $segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if((int) $seg['value'] <= 0): ?> <?php continue; ?> <?php endif; ?>
                <?php $segPct = round($seg['value'] / $total * 100, 1); ?>
                <li class="d-flex align-items-center gap-2 m-0">
                    <span class="donut-chart-dot" style="background:<?php echo e($seg['color']); ?>;"></span>
                    <?php if($legend === 'right'): ?>
                        <span class="legend-label"><?php echo e($seg['label']); ?></span>
                        <span class="legend-value"><?php echo e($seg['value']); ?><?php if($showPct): ?> <small>(<?php echo e($segPct); ?>%)</small><?php endif; ?></span>
                    <?php else: ?>
                        <?php echo e($seg['label']); ?> — <?php echo e($seg['value']); ?><?php if($showPct): ?> (<?php echo e($segPct); ?>%)<?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php else: ?>
        <p class="chart-empty"><?php echo e($emptyText); ?></p>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/components/donut-chart.blade.php ENDPATH**/ ?>