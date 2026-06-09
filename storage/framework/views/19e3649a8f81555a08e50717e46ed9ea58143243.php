<?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
	<div class="col-md-3 col-xs-4 no-print !tw-px-[3px]">
		<div class="product_box tw-w-full tw-mb-1 tw-text-center tw-cursor-pointer tw-font-semibold tw-bg-white tw-rounded-lg tw-p-1 tw-border tw-border-[#e5e7eb] tw-shadow-[0_1px_3px_rgba(0,0,0,0.06)] tw-transition-all tw-duration-150 hover:-tw-translate-y-px hover:tw-shadow-[0_4px_12px_rgba(0,0,0,0.1)] active:tw-scale-[0.97] <?php if($product->enable_stock && $product->qty_available <= 0): ?> product_out_of_stock !tw-bg-[#f3f4f6] tw-opacity-60 <?php endif; ?>" data-variation_id="<?php echo e($product->id, false); ?>" title="<?php echo e($product->name, false); ?> <?php if($product->type == 'variable'): ?>- <?php echo e($product->variation, false); ?> <?php endif; ?> <?php echo e('(' . $product->sub_sku . ')', false); ?> <?php if(!empty($show_prices)): ?> <?php echo app('translator')->get('lang_v1.default'); ?> - <?php 
            $formated_number = "";
            if (session("business.currency_symbol_placement") == "before") {
                $formated_number .= session("currency")["symbol"] . " ";
            } 
            $formated_number .= number_format((float) $product->selling_price, session("business.currency_precision", 2) , session("currency")["decimal_separator"], session("currency")["thousand_separator"]);

            if (session("business.currency_symbol_placement") == "after") {
                $formated_number .= " " . session("currency")["symbol"];
            }
            echo $formated_number; ?> <?php $__currentLoopData = $product->group_prices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group_price): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php if(array_key_exists($group_price->price_group_id, $allowed_group_prices)): ?> <?php echo e($allowed_group_prices[$group_price->price_group_id], false); ?> - <?php 
            $formated_number = "";
            if (session("business.currency_symbol_placement") == "before") {
                $formated_number .= session("currency")["symbol"] . " ";
            } 
            $formated_number .= number_format((float) $group_price->price_inc_tax, session("business.currency_precision", 2) , session("currency")["decimal_separator"], session("currency")["thousand_separator"]);

            if (session("business.currency_symbol_placement") == "after") {
                $formated_number .= " " . session("currency")["symbol"];
            }
            echo $formated_number; ?> <?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php endif; ?>">

		<div class="image-container tw-h-[58px] tw-mx-auto tw-w-full tw-mb-[3px]"
			style="background-image: url(
					<?php if(count($product->media) > 0): ?>
						<?php echo e($product->media->first()->display_url, false); ?>

					<?php elseif(!empty($product->product_image)): ?>
						<?php echo e(asset('/uploads/img/' . rawurlencode($product->product_image)), false); ?>

					<?php else: ?>
						<?php echo e(asset('/img/default.png'), false); ?>

					<?php endif; ?>
				);
			background-repeat: no-repeat; background-position: center;
			background-size: contain;">

		</div>

		<div class="text_div tw-mt-0.5">
			<small class="text text-muted tw-w-full tw-line-clamp-1 !tw-leading-[13px] tw-max-h-[13px] !tw-text-[11px]"><?php echo e($product->name, false); ?>

			<?php if($product->type == 'variable'): ?>
				- <?php echo e($product->variation, false); ?>

			<?php endif; ?>
			</small>

			<small class="text-muted">
				(<?php echo e($product->sub_sku, false); ?>)
			</small><br>
			<small class="text-muted" style="font-size: 10px;">
				<?php if($product->enable_stock): ?>
				<?php echo e(number_format($product->qty_available, session('business.currency_precision', 2), session('currency')['decimal_separator'], session('currency')['thousand_separator']), false); ?> <?php echo e($product->unit, false); ?> <?php echo app('translator')->get('lang_v1.in_stock'); ?>
				<?php else: ?>
					--
				<?php endif; ?>
			</small><br>
			<?php if(!empty($show_prices)): ?>
				<span class="product_price !tw-text-[11px] tw-font-bold tw-text-[#15803d] tw-leading-[13px] tw-whitespace-nowrap tw-overflow-hidden tw-text-ellipsis"><?php 
            $formated_number = "";
            if (session("business.currency_symbol_placement") == "before") {
                $formated_number .= session("currency")["symbol"] . " ";
            } 
            $formated_number .= number_format((float) $product->selling_price, session("business.currency_precision", 2) , session("currency")["decimal_separator"], session("currency")["thousand_separator"]);

            if (session("business.currency_symbol_placement") == "after") {
                $formated_number .= " " . session("currency")["symbol"];
            }
            echo $formated_number; ?></span>
			<?php endif; ?>
		</div>

		</div>
	</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
	<input type="hidden" id="no_products_found">
	<div class="col-md-12">
		<h4 class="text-center">
			<?php echo app('translator')->get('lang_v1.no_products_to_display'); ?>
		</h4>
	</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules/Service\Resources/views/sale_pos/partials/product_list.blade.php ENDPATH**/ ?>