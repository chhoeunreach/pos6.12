<?php if(! empty($assetGallery)): ?>
    <div class="loan-asset-gallery-grid">
        <?php $__currentLoopData = $assetGallery; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button type="button" class="loan-asset-gallery-item" data-path="<?php echo e($asset['path'], false); ?>" data-url="<?php echo e($asset['url'], false); ?>">
                <img src="<?php echo e($asset['url'], false); ?>" alt="<?php echo e($asset['name'], false); ?>" loading="lazy" onerror="this.style.display='none';">
                <span class="loan-asset-gallery-name" title="<?php echo e($asset['name'], false); ?>"><?php echo e($asset['name'], false); ?></span>
                <span class="loan-asset-gallery-date"><?php echo e($asset['modified'], false); ?></span>
            </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php else: ?>
    <div class="alert alert-info" style="margin-bottom:0;">No existing images found yet.</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/locations/partials/asset_gallery.blade.php ENDPATH**/ ?>