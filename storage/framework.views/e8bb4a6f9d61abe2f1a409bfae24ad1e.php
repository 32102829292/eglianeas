<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'latitude' => null,
    'longitude' => null,
    'id' => 'map',
    'interactive' => false,
    'hidden' => false,
    'onPinCallback' => null,
]));

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

foreach (array_filter(([
    'latitude' => null,
    'longitude' => null,
    'id' => 'map',
    'interactive' => false,
    'hidden' => false,
    'onPinCallback' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $hasCoords = $latitude !== null && $longitude !== null
                 && is_numeric($latitude) && is_numeric($longitude);
    $containerHidden = $hidden || ($interactive && !$hasCoords);
?>

<div
    class="map-preview"
    id="<?php echo e($id); ?>"
    <?php if($interactive && !$hasCoords): ?> hidden <?php endif; ?>
    <?php if($containerHidden && !$interactive): ?> hidden <?php endif; ?>
></div>

<?php if (! $__env->hasRenderedOnce('a5dd6e2d-8d2e-412e-bbc7-eaa893cb6f6c')): $__env->markAsRenderedOnce('a5dd6e2d-8d2e-412e-bbc7-eaa893cb6f6c');
$__env->startPush('styles'); ?>
    <link rel="stylesheet" href="/vendor/leaflet/leaflet.css">
<?php $__env->stopPush(); endif; ?>

<?php if (! $__env->hasRenderedOnce('26cbf49a-ba12-4be3-b6ec-f22657497f42')): $__env->markAsRenderedOnce('26cbf49a-ba12-4be3-b6ec-f22657497f42');
$__env->startPush('scripts'); ?>
    <script src="/vendor/leaflet/leaflet.js" defer></script>
    <script src="/js/address-map.js" defer></script>
<?php $__env->stopPush(); endif; ?>

<?php if($hasCoords): ?>
    <script>
        (function () {
            var q = window._addressMapQueue = window._addressMapQueue || [];
            q.push({
                id: <?php echo \Illuminate\Support\Js::from($id)->toHtml() ?>,
                lat: <?php echo \Illuminate\Support\Js::from((float) $latitude)->toHtml() ?>,
                lng: <?php echo \Illuminate\Support\Js::from((float) $longitude)->toHtml() ?>,
                interactive: <?php echo \Illuminate\Support\Js::from($interactive)->toHtml() ?>,
                onPinCallback: <?php echo \Illuminate\Support\Js::from($onPinCallback)->toHtml() ?>,
            });
        })();
    </script>
<?php endif; ?>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/components/address-map.blade.php ENDPATH**/ ?>