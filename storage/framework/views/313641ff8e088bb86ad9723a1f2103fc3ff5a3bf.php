<div class="pos-sidebar-root tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-rounded-2xl tw-bg-white" style="padding: 6px; overflow: hidden; height: 100%;">
<div class="tw-flex tw-items-start tw-gap-2 tw-flex-wrap" style="margin: 0 0 6px 0; padding: 0 4px;">
    <?php if(!empty($categories)): ?>
        <div class="tw-flex-1 tw-min-w-[140px]" id="product_category_div">
            <div class="tw-dw-drawer tw-dw-drawer-end">
                <input id="my-drawer-4<?php echo e($drawer_id_suffix ?? '', false); ?>" type="checkbox" class="tw-dw-drawer-toggle">
                <div class="tw-dw-drawer-content">
                    <!-- Page content here -->
                    <label for="my-drawer-4<?php echo e($drawer_id_suffix ?? '', false); ?>"
                        class="tw-dw-btn tw-dw-btn-sm tw-group tw-w-full tw-h-9 tw-min-h-[2.25rem] tw-rounded-full tw-flex tw-flex-row tw-items-center tw-flex-nowrap tw-gap-1.5 tw-px-3 tw-text-sm tw-font-semibold tw-normal-case tw-bg-white tw-border-slate-200 tw-text-slate-700 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-bg-indigo-50 hover:tw-border-indigo-300 hover:tw-text-slate-900 hover:tw-shadow-md hover:tw-shadow-indigo-500/15 hover:-tw-translate-y-0.5 focus:tw-ring-2 focus:tw-ring-indigo-400 focus:tw-ring-offset-1">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="tw-w-4 md:tw-w-5 tw-flex-shrink-0 tw-text-indigo-600 tw-transition-transform tw-duration-200 group-hover:tw-scale-110 icon icon-tabler icon-tabler-category-plus" width="44" height="44"
                            viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 4h6v6h-6zm10 0h6v6h-6zm-10 10h6v6h-6zm10 3h6m-3 -3v6" />
                        </svg>
                        <span class="tw-truncate tw-flex-1 tw-text-left"><?php echo app('translator')->get('category.category'); ?></span>
                        
                        <span class="pos-active-filter-badge pos-filter-badge-cat tw-dw-badge tw-dw-badge-md tw-bg-indigo-50 tw-border tw-border-indigo-200 tw-text-indigo-700 tw-gap-1 tw-max-w-[110px] tw-flex-shrink-0 tw-font-semibold tw-text-[12px] tw-px-2">
                            <span class="pos-active-filter-name tw-truncate"></span>
                            <button type="button" class="pos-filter-chip-clear tw-text-indigo-400" data-clear="category" aria-label="Clear">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </span>
                        
                        <span class="pos-count-badge tw-dw-badge tw-dw-badge-sm tw-bg-indigo-50 tw-border-indigo-100 tw-text-indigo-700 tw-font-bold tw-text-[11px] tw-flex-shrink-0 group-hover:tw-bg-white group-hover:tw-border-indigo-200 tw-transition-colors"><?php echo e(count($categories), false); ?></span>
                    </label>
                </div>
                <div class="tw-dw-drawer-side" style="z-index: 4000">
                    <label for="my-drawer-4<?php echo e($drawer_id_suffix ?? '', false); ?>" aria-label="close sidebar"
                        class="tw-dw-drawer-overlay overlay-category"></label>
                    <div class="tw-dw-menu pos-drawer-panel pos-drawer-category tw-min-h-full tw-bg-white">

                        
                        <div class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-px-6 tw-pt-[22px] tw-pb-4 tw-border-b tw-border-slate-100">
                            <div class="tw-flex-1 tw-min-w-0">
                                <h3 class="tw-text-xl tw-font-bold tw-text-slate-900 tw-m-0 tw-leading-tight tw-tracking-tight"><?php echo app('translator')->get('category.category'); ?></h3>
                                <div class="tw-font-mono tw-text-[11px] tw-tracking-[0.08em] tw-uppercase tw-text-slate-500 tw-mt-1.5"><span><?php echo e(count($categories), false); ?></span> <?php echo app('translator')->get('category.category'); ?></div>
                            </div>
                            <button type="button" class="tw-w-9 tw-h-9 tw-rounded-[10px] tw-bg-slate-100 tw-text-slate-500 tw-border-0 tw-cursor-pointer tw-inline-flex tw-items-center tw-justify-center tw-transition-all tw-duration-[160ms] tw-flex-shrink-0 hover:tw-bg-slate-200 hover:tw-text-slate-900 focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-indigo-500 focus-visible:tw-outline-offset-2 close-side-bar-category" aria-label="Close">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>

                        
                        <div class="pos-drawer-body tw-px-6 tw-pt-[18px] tw-pb-6 tw-overflow-y-auto tw-flex-1">
                            <div class="row pos-card-grid pos-card-grid-categories" data-label-all="<?php echo app('translator')->get('messages.all'); ?>" style="margin-right: 0; margin-left: -8px;">
                                <div class="col-md-4 col-xs-6 tw-mb-3 tw-cursor-pointer main-category-div main-category no-print"
                                    data-value="all" data-parent="0" style="padding-left: 4px; padding-right: 4px;">
                                    <div class="pos-card cat">
                                        <h4 class="pos-card-name"><?php echo app('translator')->get('lang_v1.all_category'); ?></h4>
                                    </div>
                                </div>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="col-md-4 col-xs-6 tw-mb-3 tw-cursor-pointer main-category-div main-category no-print"
                                        data-value="<?php echo e($category['id'], false); ?>" data-name="<?php echo e($category['name'], false); ?>" data-parent="0"
                                        style="padding-left: 4px; padding-right: 4px;">
                                        <div class="pos-card cat">
                                            <h4 class="pos-card-name"><?php echo e($category['name'], false); ?></h4>
                                            <?php if(!empty($category['sub_categories'])): ?>
                                                <span class="pos-card-subcat-dot"></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if(!empty($category['sub_categories'])): ?>
                                        <div class="all-sub-category" data-category-id="<?php echo e($category['id'], false); ?>" style="display: none">
                                            <?php $__currentLoopData = $category['sub_categories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php if($sc['parent_id'] != 0): ?>
                                                    <div class="col-md-4 col-xs-6 tw-mb-3 tw-cursor-pointer product_category no-print"
                                                        data-value="<?php echo e($sc['id'], false); ?>" data-name="<?php echo e($sc['name'], false); ?>"
                                                        style="padding-left: 4px; padding-right: 4px;">
                                                        <div class="pos-card cat">
                                                            <h4 class="pos-card-name"><?php echo e($sc['name'], false); ?></h4>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if(!empty($brands)): ?>
        <div class="tw-flex-1 tw-min-w-[140px]" id="product_brand_div">
            <div class="tw-dw-drawer tw-dw-drawer-end">
                <input id="my-drawer-brand<?php echo e($drawer_id_suffix ?? '', false); ?>" type="checkbox" class="tw-dw-drawer-toggle">
                <div class="tw-dw-drawer-content">
                    <!-- Page content here -->
                    <label for="my-drawer-brand<?php echo e($drawer_id_suffix ?? '', false); ?>"
                        class="tw-dw-btn tw-dw-btn-sm tw-group tw-w-full tw-h-9 tw-min-h-[2.25rem] tw-rounded-full tw-flex tw-flex-row tw-items-center tw-flex-nowrap tw-gap-1.5 tw-px-3 tw-text-sm tw-font-semibold tw-normal-case tw-bg-white tw-border-slate-200 tw-text-slate-700 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-bg-violet-50 hover:tw-border-violet-300 hover:tw-text-slate-900 hover:tw-shadow-md hover:tw-shadow-violet-500/15 hover:-tw-translate-y-0.5 focus:tw-ring-2 focus:tw-ring-violet-400 focus:tw-ring-offset-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-4 md:tw-w-5 tw-flex-shrink-0 tw-text-violet-600 tw-transition-transform tw-duration-200 group-hover:tw-scale-110 icon icon-tabler icon-tabler-brand-beats"
                            width="44" height="44" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"
                            fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                            <path d="M12.5 12.5m-3.5 0a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0 -7 0" />
                            <path d="M9 12v-8" />
                        </svg>
                        <span class="tw-truncate tw-flex-1 tw-text-left"><?php echo app('translator')->get('brand.brands'); ?></span>
                        
                        <span class="pos-active-filter-badge pos-filter-badge-brand tw-dw-badge tw-dw-badge-md tw-bg-violet-50 tw-border tw-border-violet-200 tw-text-violet-700 tw-gap-1 tw-max-w-[110px] tw-flex-shrink-0 tw-font-semibold tw-text-[12px] tw-px-2">
                            <span class="pos-active-filter-name tw-truncate"></span>
                            <button type="button" class="pos-filter-chip-clear tw-text-violet-400" data-clear="brand" aria-label="Clear">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </span>
                        
                        <span class="pos-count-badge tw-dw-badge tw-dw-badge-sm tw-bg-violet-50 tw-border-violet-100 tw-text-violet-700 tw-font-bold tw-text-[11px] tw-flex-shrink-0 group-hover:tw-bg-white group-hover:tw-border-violet-200 tw-transition-colors"><?php echo e(count($brands), false); ?></span>
                    </label>
                </div>
                <div class="tw-dw-drawer-side" style="z-index: 4000">
                    <label for="my-drawer-brand<?php echo e($drawer_id_suffix ?? '', false); ?>" aria-label="close sidebar"
                        class="tw-dw-drawer-overlay overlay-brand"></label>
                    <div class="tw-dw-menu pos-drawer-panel pos-drawer-brand tw-min-h-full tw-bg-white">

                        <div class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-px-6 tw-pt-[22px] tw-pb-4 tw-border-b tw-border-slate-100">
                            <div class="tw-flex-1 tw-min-w-0">
                                <h3 class="tw-text-xl tw-font-bold tw-text-slate-900 tw-m-0 tw-leading-tight tw-tracking-tight"><?php echo app('translator')->get('brand.brands'); ?></h3>
                                <div class="tw-font-mono tw-text-[11px] tw-tracking-[0.08em] tw-uppercase tw-text-slate-500 tw-mt-1.5"><span><?php echo e(count($brands), false); ?></span> <?php echo app('translator')->get('brand.brands'); ?></div>
                            </div>
                            <button type="button" class="tw-w-9 tw-h-9 tw-rounded-[10px] tw-bg-slate-100 tw-text-slate-500 tw-border-0 tw-cursor-pointer tw-inline-flex tw-items-center tw-justify-center tw-transition-all tw-duration-[160ms] tw-flex-shrink-0 hover:tw-bg-slate-200 hover:tw-text-slate-900 focus-visible:tw-outline focus-visible:tw-outline-2 focus-visible:tw-outline-violet-500 focus-visible:tw-outline-offset-2 close-side-bar-brand" aria-label="Close">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>

                        <div class="pos-drawer-body tw-px-6 tw-pt-[18px] tw-pb-6 tw-overflow-y-auto tw-flex-1">
                            <div class="row pos-card-grid pos-card-grid-brands" style="margin-right: 0; margin-left: -8px;">
                                <?php $__currentLoopData = $brands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $brand): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="col-md-4 col-xs-6 tw-mb-3 tw-cursor-pointer product_brand no-print"
                                        data-value="<?php echo e($key, false); ?>" data-name="<?php echo e($brand, false); ?>"
                                        style="padding-left: 4px; padding-right: 4px;">
                                        <div class="pos-card brand">
                                            <h4 class="pos-card-name"><?php echo e($brand, false); ?></h4>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- used in repair : filter for service/product -->
    <div class="col-md-6 hide" id="product_service_div">
        <?php echo Form::select(
            'is_enabled_stock',
            ['' => __('messages.all'), 'product' => __('sale.product'), 'service' => __('lang_v1.service')],
            null,
            ['id' => 'is_enabled_stock', 'class' => 'select2', 'name' => null, 'style' => 'width:100% !important'],
        ); ?>

    </div>

    <div class="tw-flex-1 tw-min-w-[140px]" id="feature_product_div">
        <button type="button" id="show_featured_products"
            class="tw-dw-btn tw-dw-btn-sm tw-group tw-w-full tw-h-9 tw-min-h-[2.25rem] tw-rounded-full tw-flex-nowrap tw-gap-2 tw-px-3 tw-text-sm tw-font-semibold tw-normal-case tw-bg-white tw-border-slate-200 tw-text-slate-700 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-bg-amber-50 hover:tw-border-amber-300 hover:tw-text-slate-900 hover:tw-shadow-md hover:tw-shadow-amber-500/15 hover:-tw-translate-y-0.5 focus:tw-ring-2 focus:tw-ring-amber-400 focus:tw-ring-offset-1">
            <svg xmlns="http://www.w3.org/2000/svg"
                class="tw-w-4 md:tw-w-5 tw-flex-shrink-0 tw-text-amber-500 tw-transition-transform tw-duration-200 group-hover:tw-scale-110 group-hover:tw-rotate-12 icon icon-tabler icon-tabler-star" width="44" height="44"
                viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M12 17.75l-6.172 3.245 1.179 -6.873 -5 -4.867 6.9 -1 3.086 -6.253 3.086 6.253 6.9 1 -5 4.867 1.179 6.873z" />
            </svg>
            <span class="tw-truncate"><?php echo app('translator')->get('lang_v1.featured_products'); ?></span>
            <?php if(!empty($featured_products) && count($featured_products) > 0): ?>
                <span class="tw-dw-badge tw-dw-badge-sm tw-bg-amber-50 tw-border-amber-100 tw-text-amber-700 tw-font-bold tw-text-[11px] group-hover:tw-bg-white group-hover:tw-border-amber-200 tw-transition-colors"><?php echo e(count($featured_products), false); ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>
<div class="row" style="margin: 0;">
    <input type="hidden" id="suggestion_page" value="1">
    <div class="col-md-12" style="padding: 0;">
        <div id="product_list_body" class="eq-height-row tw-max-h-[calc(100vh_-_229px)] tw-overflow-y-auto tw-overflow-x-hidden" style="padding-right: 4px;">
            <div id="featured_products_box" style="display: none;">
                <?php if(!empty($featured_products)): ?>
                    <?php echo $__env->make('service::sale_pos.partials.featured_products', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php endif; ?>
            </div>
            
            <div id="featured_empty_state_template" style="display: none;">
                <div class="tw-w-full tw-flex tw-flex-wrap tw-items-center tw-justify-center tw-gap-x-2 tw-gap-y-1 tw-py-3 tw-px-3 tw-text-amber-800 tw-text-xs md:tw-text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-4 md:tw-w-5 tw-flex-shrink-0" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 8h.01" />
                        <path d="M11 12h1v4h1" />
                    </svg>
                    <span><?php echo app('translator')->get('lang_v1.featured_products_empty_msg'); ?></span>
                    <a href="<?php echo e(url('business-location'), false); ?>" target="_blank" class="tw-inline-flex tw-items-center tw-gap-1 tw-font-semibold tw-text-amber-900 tw-underline tw-decoration-amber-400 tw-underline-offset-2 hover:tw-text-amber-950 hover:tw-decoration-amber-700 tw-transition-colors">
                        <?php echo app('translator')->get('business.business_location'); ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-3 md:tw-w-4 tw-flex-shrink-0" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                            <path d="M11 13l9 -9" />
                            <path d="M15 4h5v5" />
                        </svg>
                    </a>
                </div>
            </div>
            <div id="product_list_items" class="tw-w-full"></div>
        </div>
    </div>
    <div class="col-md-12 text-center" id="suggestion_page_loader" style="display: none;">
        <i class="fa fa-spinner fa-spin fa-2x"></i>
    </div>
</div>
</div>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules/Service\Resources/views/sale_pos/partials/pos_sidebar.blade.php ENDPATH**/ ?>