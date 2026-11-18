<?php if($paginator->hasPages()): ?>
<div class="pagination-wrap">
    <span class="pagination-info">Showing <?php echo e($paginator->firstItem()); ?>-<?php echo e($paginator->lastItem()); ?> of <?php echo e($paginator->total()); ?></span>
    <nav class="pagination" aria-label="Pagination">
        <?php if($paginator->onFirstPage()): ?>
            <span class="page-link disabled">&laquo;</span>
        <?php else: ?>
            <a class="page-link" href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev">&laquo;</a>
        <?php endif; ?>
        <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(is_string($element)): ?>
                <span class="page-link disabled"><?php echo e($element); ?></span>
            <?php endif; ?>
            <?php if(is_array($element)): ?>
                <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($page == $paginator->currentPage()): ?>
                        <span class="page-link active"><?php echo e($page); ?></span>
                    <?php else: ?>
                        <a class="page-link" href="<?php echo e($url); ?>"><?php echo e($page); ?></a>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($paginator->hasMorePages()): ?>
            <a class="page-link" href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next">&raquo;</a>
        <?php else: ?>
            <span class="page-link disabled">&raquo;</span>
        <?php endif; ?>
    </nav>
</div>
<?php endif; ?>
<?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/pagination/simple.blade.php ENDPATH**/ ?>