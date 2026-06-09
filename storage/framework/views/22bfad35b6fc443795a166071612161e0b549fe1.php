<?php
    $is_mobile = isMobile();
?>
<div class="row" style="margin:0;">
    <div
        class="pos-form-actions tw-fixed tw-bottom-0 tw-left-0 tw-right-0 tw-w-full tw-z-[1000] !tw-mt-0 tw-bg-[#f8fafc] tw-border-t-0 tw-shadow-[0_-2px_10px_rgba(0,0,0,0.06)] tw-rounded-tl-xl tw-rounded-tr-xl">
        <div
            class="tw-flex tw-items-center tw-justify-between tw-flex-col sm:tw-flex-row md:tw-flex-row lg:tw-flex-row xl:tw-flex-row tw-gap-2 tw-overflow-x-auto tw-w-full tw-px-4 tw-py-[6px] tw-min-h-[52px]">

            <div class="!tw-w-full md:!tw-w-none !tw-flex md:!tw-hidden !tw-flex-row !tw-items-center !tw-gap-3">
                <?php if(empty($edit)): ?>
                    <button type="button" class="tw-leading-none tw-whitespace-nowrap tw-font-bold tw-text-red-600 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-white tw-border-2 tw-border-red-400 tw-p-2 tw-rounded-md tw-w-[5.5rem] tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 active:tw-scale-95 tw-transition-transform js-pos-cancel"> <i
                            class="fas fa-window-close"></i> <?php echo app('translator')->get('sale.cancel'); ?></button>
                <?php else: ?>
                    <button type="button" class="btn-danger tw-dw-btn hide tw-dw-btn-xs js-pos-delete" id="pos-delete"
                        <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>> <i class="fas fa-trash-alt"></i>
                        <?php echo app('translator')->get('messages.delete'); ?></button>
                <?php endif; ?>

                <?php if(!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <button type="button"
                        class="pos-finalize tw-leading-none tw-whitespace-nowrap tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 tw-font-bold tw-text-white tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-[#001F3E] tw-rounded-md tw-p-2 tw-w-[8.5rem] active:tw-scale-95 tw-transition-transform no-print <?php if($pos_settings['disable_pay_checkout'] != 0): ?> hide <?php endif; ?>"
                        title="<?php echo app('translator')->get('lang_v1.tooltip_checkout_multi_pay'); ?>"><i class="fas fa-money-check-alt"
                            aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.checkout_multi_pay'); ?> </button>
                <?php endif; ?>

                <?php if(!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <button type="button"
                        class="tw-leading-none tw-whitespace-nowrap tw-font-bold tw-text-white tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-[rgb(40,183,123)] tw-p-2 tw-rounded-md tw-w-[5.5rem] tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 active:tw-scale-95 tw-transition-transform no-print <?php if($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)): ?> hide <?php endif; ?> pos-express-finalize"
                        data-pay_method="cash" title="<?php echo app('translator')->get('tooltip.express_checkout'); ?>"> <i class="fas fa-money-bill-alt"
                            aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.express_checkout_cash'); ?></button>
                <?php endif; ?>
            </div>
            <div class="tw-flex tw-items-center tw-gap-3 tw-flex-row tw-overflow-x-auto pos-footer-secondary">

                
                <div class="!tw-hidden md:!tw-flex md:tw-items-center md:tw-gap-3">
                    <?php if(empty($edit)): ?>
                        <button type="button"
                            class="tw-leading-none tw-whitespace-nowrap tw-font-bold tw-text-red-600 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-white tw-border-2 tw-border-red-400 tw-p-2 tw-rounded-md tw-w-[8.5rem] tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 active:tw-scale-95 tw-transition-transform js-pos-cancel"> <i
                                class="fas fa-window-close"></i> <?php echo app('translator')->get('sale.cancel'); ?></button>
                    <?php else: ?>
                        <button type="button"
                            class="tw-leading-none tw-whitespace-nowrap tw-font-bold tw-text-white tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-red-600 tw-p-2 tw-rounded-md tw-w-[8.5rem] tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 hide active:tw-scale-95 tw-transition-transform js-pos-delete"
                            <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>> <i
                                class="fas fa-trash-alt"></i> <?php echo app('translator')->get('messages.delete'); ?></button>
                    <?php endif; ?>
                    <span class="pos-footer-divider tw-inline-block tw-w-px tw-h-7 tw-bg-[#e2e8f0] tw-flex-shrink-0 tw-self-center tw-rounded-[1px] tw-mx-1"></span>
                </div>

                <?php if(!Gate::check('disable_draft') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <button type="button"
                        class="tw-font-bold tw-text-gray-700 tw-text-xs md:tw-text-sm tw-flex tw-flex-col tw-items-center tw-justify-center tw-gap-1 tw-whitespace-nowrap <?php if($pos_settings['disable_draft'] != 0): ?> hide <?php endif; ?>"
                        id="pos-draft" <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>><i
                            class="fas fa-edit tw-text-[#009ce4]"></i> <?php echo app('translator')->get('sale.draft'); ?></button>
                <?php endif; ?>

                <?php if(!Gate::check('disable_quotation') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <button type="button"
                        class="tw-font-bold tw-text-gray-700 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-flex tw-flex-col tw-items-center tw-justify-center tw-gap-1 tw-whitespace-nowrap <?php if($is_mobile): ?> col-xs-6 <?php endif; ?>"
                        id="pos-quotation" <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>><i
                            class="fas fa-edit tw-text-[#E7A500]"></i> <?php echo app('translator')->get('lang_v1.quotation'); ?></button>
                <?php endif; ?>

                <?php if(!Gate::check('disable_suspend_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <?php if(empty($pos_settings['disable_suspend'])): ?>
                        <button type="button"
                            class="tw-font-bold tw-text-gray-700 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-flex tw-flex-col tw-items-center tw-justify-center tw-gap-1 tw-whitespace-nowrap  no-print pos-express-finalize"
                            data-pay_method="suspend" title="<?php echo app('translator')->get('lang_v1.tooltip_suspend'); ?>"
                            <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>>
                            <i class="fas fa-pause tw-text-[#EF4B51]" aria-hidden="true"></i>
                            <?php echo app('translator')->get('lang_v1.suspend'); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if(!Gate::check('disable_credit_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <?php if(empty($pos_settings['disable_credit_sale_button'])): ?>
                        <input type="hidden" name="is_credit_sale" value="0" id="is_credit_sale">
                        <button type="button"
                            class=" tw-font-bold tw-text-gray-700 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-flex tw-flex-col tw-items-center tw-justify-center tw-gap-1 tw-whitespace-nowrap no-print pos-express-finalize <?php if($is_mobile): ?> col-xs-6 <?php endif; ?>"
                            data-pay_method="credit_sale" title="<?php echo app('translator')->get('lang_v1.tooltip_credit_sale'); ?>"
                            <?php if(!empty($only_payment)): ?> disabled <?php endif; ?>>
                            <i class="fas fa-check tw-text-[#5E5CA8]" aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.credit_sale'); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if(!Gate::check('disable_card') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                    <button type="button"
                        class="tw-font-bold tw-text-gray-700 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-flex tw-flex-col tw-items-center tw-justify-center tw-gap-1 tw-whitespace-nowrap  no-print <?php if(!empty($pos_settings['disable_suspend'])): ?>  <?php endif; ?> pos-express-finalize <?php if(!array_key_exists('card', $payment_types)): ?> hide <?php endif; ?> <?php if($is_mobile): ?> col-xs-6 <?php endif; ?>"
                        data-pay_method="card" title="<?php echo app('translator')->get('lang_v1.tooltip_express_checkout_card'); ?>">
                        <i class="fas fa-credit-card tw-text-[#D61B60]" aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.express_checkout_card'); ?>
                    </button>
                <?php endif; ?>

                
                <div class="!tw-hidden md:!tw-flex md:tw-items-center md:tw-gap-3">
                    <span class="pos-footer-divider tw-inline-block tw-w-px tw-h-7 tw-bg-[#e2e8f0] tw-flex-shrink-0 tw-self-center tw-rounded-[1px] tw-mx-1"></span>
                    <?php if(!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                        <button type="button"
                            class="pos-finalize tw-leading-none tw-whitespace-nowrap tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 tw-font-bold tw-text-white tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-[#001F3E] tw-rounded-md tw-p-2 tw-w-[8.5rem] no-print <?php if($pos_settings['disable_pay_checkout'] != 0): ?> hide <?php endif; ?>"
                            title="<?php echo app('translator')->get('lang_v1.tooltip_checkout_multi_pay'); ?>"><i class="fas fa-money-check-alt"
                                aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.checkout_multi_pay'); ?> </button>
                    <?php endif; ?>

                    <?php if(!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin')): ?>
                        <button type="button"
                            class="tw-leading-none tw-whitespace-nowrap tw-font-bold tw-text-white tw-cursor-pointer tw-text-xs md:tw-text-sm tw-bg-[rgb(40,183,123)] tw-p-2 tw-rounded-md tw-w-[8.5rem] tw-flex tw-flex-row tw-items-center tw-justify-center tw-gap-1 active:tw-scale-95 tw-transition-transform no-print <?php if($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)): ?> hide <?php endif; ?> pos-express-finalize"
                            data-pay_method="cash" title="<?php echo app('translator')->get('tooltip.express_checkout'); ?>"> <i class="fas fa-money-bill-alt"
                                aria-hidden="true"></i> <?php echo app('translator')->get('lang_v1.express_checkout_cash'); ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tw-w-full md:tw-w-fit tw-flex tw-flex-col tw-items-end tw-gap-3 tw-hidden md:tw-block">
                <?php if(!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0): ?>
                    <button type="button"
                        class="tw-font-bold tw-bg-[#646EE4] hover:tw-bg-[#414aac] tw-rounded-full tw-text-white tw-w-full md:tw-w-fit tw-px-5 tw-h-9 tw-cursor-pointer tw-text-xs md:tw-text-sm tw-inline-flex tw-items-center tw-justify-center tw-gap-1"
                        data-toggle="modal" data-target="#recent_transactions_modal" id="recent-transactions"><i
                            class="fas fa-clock"></i> <?php echo app('translator')->get('lang_v1.recent_transactions'); ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if(isset($transaction)): ?>
    <?php echo $__env->make('service::sale_pos.partials.edit_discount_modal', [
        'sales_discount' => $transaction->discount_amount,
        'discount_type' => $transaction->discount_type,
        'rp_redeemed' => $transaction->rp_redeemed,
        'rp_redeemed_amount' => $transaction->rp_redeemed_amount,
        'max_available' => !empty($redeem_details['points']) ? $redeem_details['points'] : 0,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php else: ?>
    <?php echo $__env->make('service::sale_pos.partials.edit_discount_modal', [
        'sales_discount' => $business_details->default_sales_discount,
        'discount_type' => 'percentage',
        'rp_redeemed' => 0,
        'rp_redeemed_amount' => 0,
        'max_available' => 0,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php endif; ?>

<?php if(isset($transaction)): ?>
    <?php echo $__env->make('service::sale_pos.partials.edit_order_tax_modal', ['selected_tax' => $transaction->tax_id], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php else: ?>
    <?php echo $__env->make('service::sale_pos.partials.edit_order_tax_modal', [
        'selected_tax' => $business_details->default_sales_tax,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php endif; ?>

<?php echo $__env->make('service::sale_pos.partials.edit_shipping_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules/Service\Resources/views/sale_pos/partials/pos_form_actions.blade.php ENDPATH**/ ?>