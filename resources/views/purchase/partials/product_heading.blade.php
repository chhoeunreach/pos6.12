<div class="purchase-product-identification">
    <div class="purchase-product-main-row">
        <div class="purchase-product-heading">{{ $slot ?? '' }}</div>
    @if(empty($is_purchase_order) && session('business.enable_lot_number'))
        <div class="purchase-lot-control">
            <label class="purchase-identification-label">
                <small class="text-muted">@lang('lang_v1.lot_number')</small>
                {!! Form::text('purchases[' . $identification_row_index . '][lot_number]', $identification_lot_number, ['class' => 'form-control input-sm purchase_lot_number', 'placeholder' => __('lang_v1.lot_number')]) !!}
            </label>
            <button type="button" class="btn btn-xs btn-default add_purchase_lot_row" title="{{ __('product.add_lot_row') }}">
                <i class="fa fa-plus" aria-hidden="true"></i> {{ __('product.add_lot_row') }}
            </button>
        </div>
    @endif
    </div>
</div>
