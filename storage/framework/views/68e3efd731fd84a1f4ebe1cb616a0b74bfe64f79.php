<?php
    $currentTitle = trim($__env->yieldContent('title'));
?>

<div class="lm-breadcrumb-wrap">
    <ol class="breadcrumb lm-breadcrumb">
        <li><a href="<?php echo e(route('loan-management.dashboard'), false); ?>">LoanManagement</a></li>
        <?php if($currentTitle !== ''): ?>
            <li class="active"><?php echo e($currentTitle, false); ?></li>
        <?php endif; ?>
    </ol>
</div>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/layouts/breadcrumb.blade.php ENDPATH**/ ?>