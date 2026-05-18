<div class="box box-warning">
    <div class="box-header"><h3 class="box-title">Loan Terms</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Total Amount</label><input type="text" id="loan_total_amount_display" class="form-control" value="{{ number_format((float)($sell['transaction']->final_total ?? 0), 2) }}" readonly><input type="hidden" id="loan_total_amount_value" value="{{ (float)($sell['transaction']->final_total ?? 0) }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Total Paid</label><input type="text" id="loan_total_paid_display" class="form-control" value="{{ number_format((float)($sell['defaults']['down_payment'] ?? 0), 2) }}" readonly></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Total Due</label><input type="text" id="loan_total_due_display" class="form-control" value="{{ number_format(max(0, (float)($sell['transaction']->final_total ?? 0) - (float)($sell['defaults']['down_payment'] ?? 0)), 2) }}" readonly></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Loan Date</label><input type="date" name="loan_date" class="form-control" value="{{ date('Y-m-d') }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Principal Amount</label><input type="number" step="0.01" id="principal_amount_input" name="principal_amount" class="form-control" value="{{ $sell['defaults']['principal_amount'] }}"></div></div>
        <input type="hidden" id="down_payment_hidden" name="down_payment" value="{{ $sell['defaults']['down_payment'] }}">
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Interest Rate</label><input type="number" step="0.01" name="interest_rate" class="form-control" value="{{ isset($sell['defaults']['interest_rate']) && (float)$sell['defaults']['interest_rate'] > 0 ? $sell['defaults']['interest_rate'] : 4 }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Interest Type</label><select name="interest_type" class="form-control"><option value="flat">Flat</option><option value="reducing">Reducing</option></select></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Duration Months</label><input type="number" name="duration_months" class="form-control" value="{{ $sell['defaults']['duration_months'] ?? 12 }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Payment Frequency</label><select name="payment_frequency" class="form-control"><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="daily">Daily</option></select></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>First Due Date</label><input type="date" name="first_due_date" class="form-control" value="{{ $sell['defaults']['first_due_date'] }}"></div></div>
        <input type="hidden" name="currency" value="{{ $sell['defaults']['currency'] ?? 'USD' }}">
        <input type="hidden" name="exchange_rate" value="{{ $sell['defaults']['exchange_rate'] }}">
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Penalty Type</label><input name="penalty_type" class="form-control" value="fixed"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Penalty Amount</label><input type="number" step="0.01" name="penalty_amount" class="form-control" value="0"></div></div>
        <input type="hidden" name="assigned_collector_id" value="{{ auth()->id() }}">
        <div class="col-sm-12 col-md-9"><div class="form-group"><label>Note</label><input name="note" class="form-control"></div></div>
    </div>
</div>
