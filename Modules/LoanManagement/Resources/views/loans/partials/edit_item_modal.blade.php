@php
    $productName = $itemRow->product_name_snapshot ?? $itemRow->product_name ?? '';
    $sku = $itemRow->sku_snapshot ?? $itemRow->sku ?? '';
    $imei = $itemRow->imei_snapshot ?? $itemRow->imei ?? '';
    $serial = $itemRow->serial_number_snapshot ?? $itemRow->serial_number ?? '';
    $qty = (float) ($itemRow->qty ?? 1);
    $unitPrice = (float) ($itemRow->unit_price ?? 0);
    $lineTotal = (float) ($itemRow->line_total ?? ($qty * $unitPrice));
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $isCreate = (bool) ($isCreate ?? false);
    $editRouteParams = ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : []);
    $formUrl = $isCreate
        ? route('loan-management.loans.items.store', ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : []))
        : route('loan-management.loans.items.update', ['loan' => $loanRow->id, 'item' => $itemRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : []));
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => $formUrl, 'method' => 'post', 'id' => 'loan_item_update_form']) !!}
        <input type="hidden" name="return_to" value="{{ route('loan-management.loans.edit', $editRouteParams) }}">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ lm_label('messages.close', 'Close', 'បិទ') }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-cube"></i> {{ $isCreate ? 'Add Installment Item' : 'Edit Installment Item' }}
            </h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Installment #:</strong> {{ $loanRow->loan_number ?? $loanRow->id }}<br>
                        <strong>Customer:</strong> {{ $loanRow->customer_name_snapshot ?? '-' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Item ID:</strong> {{ $isCreate ? 'New' : $itemRow->id }}<br>
                        <strong>Currency:</strong> {{ $loanRow->currency ?? 'USD' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Current total:</strong> {{ number_format($lineTotal, 2) }}<br>
                        <strong>Qty:</strong> {{ number_format($qty, 2) }}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('product_name_snapshot', 'Product') !!}
                        <input type="text" name="product_name_snapshot" id="product_name_snapshot" class="form-control" value="{{ $productName }}" {{ $isCreate ? 'required' : '' }}>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sku_snapshot', 'SKU') !!}
                        <input type="text" name="sku_snapshot" id="sku_snapshot" class="form-control" value="{{ $sku }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('color', 'Color') !!}
                        <input type="text" name="color" id="color" class="form-control" value="{{ $itemRow->color ?? '' }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('imei_snapshot', 'IMEI') !!}
                        <input type="text" name="imei_snapshot" id="imei_snapshot" class="form-control" value="{{ $imei }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('serial_number_snapshot', 'Serial Number') !!}
                        <input type="text" name="serial_number_snapshot" id="serial_number_snapshot" class="form-control" value="{{ $serial }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('qty', 'Qty') !!}
                        <input type="number" step="0.0001" min="0" name="qty" id="qty" class="form-control item-number" value="{{ number_format($qty, 4, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('unit_price', 'Unit Price') !!}
                        <input type="number" step="0.01" min="0" name="unit_price" id="unit_price" class="form-control item-number" value="{{ number_format($unitPrice, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('line_total', 'Line Total') !!}
                        <input type="number" step="0.01" min="0" name="line_total" id="line_total" class="form-control item-number" value="{{ number_format($lineTotal, 2, '.', '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left loan-item-recalculate">
                <i class="fa fa-calculator"></i> Auto Total
            </button>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                {{ $isCreate ? lm_label('loanmanagement::ui.add_item', 'Add Item', 'បន្ថែមទំនិញ') : lm_label('messages.update', 'Update', 'ធ្វើបច្ចុប្បន្នភាព') }}
            </button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
                {{ lm_label('messages.close', 'Close', 'បិទ') }}
            </button>
        </div>
        {!! Form::close() !!}
    </div>
</div>

<script>
$(function () {
    var $form = $('#loan_item_update_form');

    function numberValue(selector) {
        return parseFloat($form.find(selector).val()) || 0;
    }

    function recalculateTotal() {
        var qty = numberValue('[name="qty"]');
        var unitPrice = numberValue('[name="unit_price"]');
        $form.find('[name="line_total"]').val(Math.max(qty * unitPrice, 0).toFixed(2));
    }

    $form.on('click', '.loan-item-recalculate', recalculateTotal);

    $form.off('submit.loanItemModal').on('submit.loanItemModal', function (e) {
        e.preventDefault();
        var $buttons = $form.find('button[type="submit"], .loan-item-recalculate');
        $buttons.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Installment item updated successfully');
                }

                $('.view_modal').modal('hide');
                window.location.href = (res.data && res.data.redirect_url) ? res.data.redirect_url : window.location.href;
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    alert(xhr.responseJSON.errors[firstKey][0] || 'Validation failed');
                    return;
                }

                alert(xhr.responseJSON?.message || 'Failed to update loan item');
            },
            complete: function () {
                $buttons.prop('disabled', false);
            }
        });
    });
});
</script>
