<div class="box box-primary">
    <div class="box-header"><h3 class="box-title">Selected Sale Information</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Invoice No</label>
                <input type="text" name="source_invoice_no" class="form-control" value="{{ old('source_invoice_no', $sell['transaction']->invoice_no) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Sell Date</label>
                <input type="date" name="source_created_at" class="form-control" value="{{ old('source_created_at', !empty($sell['transaction']->transaction_date) ? \Carbon\Carbon::parse($sell['transaction']->transaction_date)->format('Y-m-d') : '') }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Customer</label>
                <input type="text" name="customer_name_snapshot" class="form-control" value="{{ old('customer_name_snapshot', $sell['transaction']->customer_name) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="customer_phone_snapshot" class="form-control" value="{{ old('customer_phone_snapshot', $sell['transaction']->customer_phone) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location_name_snapshot" class="form-control" value="{{ old('location_name_snapshot', $sell['transaction']->location_name_snapshot) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Location ID</label>
                <input type="number" min="0" name="main_location_id" class="form-control" value="{{ old('main_location_id', $sell['transaction']->main_location_id) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Final Total</label>
                <input type="number" step="0.01" min="0" name="sell_final_total_snapshot" class="form-control" value="{{ old('sell_final_total_snapshot', number_format((float) $sell['transaction']->final_total, 2, '.', '')) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Paid Amount</label>
                <input type="number" step="0.01" min="0" name="sell_paid_amount_snapshot" class="form-control" value="{{ old('sell_paid_amount_snapshot', number_format((float) $sell['paid_amount'], 2, '.', '')) }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Due Amount</label>
                <input type="number" step="0.01" min="0" name="sell_due_amount_snapshot" class="form-control" value="{{ old('sell_due_amount_snapshot', number_format((float) $sell['due_amount'], 2, '.', '')) }}">
            </div>
        </div>
    </div>
</div>
