<div class="box box-success">
    <div class="box-header"><h3 class="box-title"><i class="fa fa-money"></i> Down Payment (Optional)</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Down Payment Amount</label>
                <input type="number" step="0.01" id="payment_amount_input" name="payment[amount]" class="form-control" value="0" min="0">
                <input type="hidden" id="down_payment_hidden" name="down_payment" value="0">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Paid Date</label>
                <input type="date" name="payment[paid_date]" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Payment Method</label>
                {!! Form::select('payment[method]', $paymentTypes ?? [], $defaultPaymentMethod ?? 'cash', ['class' => 'form-control select2', 'style' => 'width:100%;']) !!}
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Reference Number</label>
                <input name="payment[reference_number]" class="form-control" placeholder="Ref #">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Currency</label>
                <select name="payment[currency]" class="form-control">
                    <option value="USD">USD</option>
                    <option value="KHR">KHR</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Exchange Rate</label>
                <input type="number" step="0.0001" name="payment[exchange_rate]" class="form-control" value="1">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Status</label>
                <select name="payment[status]" class="form-control">
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Received By</label>
                <input class="form-control" value="{{ trim((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')) }}" readonly>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Account Name</label>
                <input name="payment[account_name]" class="form-control">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Account Number</label>
                <input name="payment[account_number]" class="form-control">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Transaction ID</label>
                <input name="payment[transaction_id]" class="form-control">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Channel</label>
                <input name="payment[channel]" class="form-control" placeholder="Cash / ABA / Bank / Card">
            </div>
        </div>
        <div class="col-sm-12 col-md-12">
            <div class="form-group">
                <label>Payment Note</label>
                <input name="payment[note]" class="form-control">
            </div>
        </div>
    </div>
</div>
