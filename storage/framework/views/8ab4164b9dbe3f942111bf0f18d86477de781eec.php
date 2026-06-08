


<div
    class="tw-transition-all tw-mb-4 lg:tw-col-span-1 tw-duration-200 tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 hover:tw-shadow-md tw-ring-gray-200">
    <div class="box-header with-border" style="cursor: pointer;">
        <h3 class="box-title tw-pt-2 tw-pb-2 tw-pl-2">
            <a data-toggle="collapse" data-parent="#accordion" href="#collapseFilter">
                <?php if(!empty($icon)): ?>
                    <?php echo $icon; ?>

                <?php else: ?>
                    <i class="fa fa-filter" aria-hidden="true"></i>
                <?php endif; ?> <?php echo e($title ?? '', false); ?>

            </a>
        </h3>
    </div>
    <?php
        if (isMobile()) {
            $closed = true;
        }
        $closed = true;
    ?>
    <div id="collapseFilter" class="panel-collapse active collapse <?php if(empty($closed)): ?> in <?php endif; ?> tw-pt-4 tw-pb-4"
        aria-expanded="true">
        <div class="box-body">
            <?php echo e($slot, false); ?>

        </div>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\resources\views/components/filters.blade.php ENDPATH**/ ?>