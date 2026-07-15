<div class="box box-warning">
    <div class="box-header"><h3 class="box-title"><i class="fa fa-file-text-o"></i> Loan Terms</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Loan Number</label>
                <input type="text" name="loan_number" class="form-control" placeholder="Auto-generate if blank">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Business Location</label>
                <select name="business_location_id" class="form-control select2" style="width:100%">
                    <option value="">-- Select --</option>
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string) $id === (string) ($defaultLocationId ?? '') ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Loan Date <span class="text-danger">*</span></label>
                <input type="date" name="loan_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Principal Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" id="principal_amount_input" name="principal_amount" class="form-control" min="0.01" required placeholder="Auto from items or manual">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Interest Rate (%)</label>
                <input type="number" step="0.01" name="interest_rate" class="form-control" value="{{ old('interest_rate', 4) }}" min="0">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Interest Type <span class="text-danger">*</span></label>
                <select name="interest_type" class="form-control">
                    <option value="flat">បង់ថេរ (Flat)</option>
                    <option value="reducing_balance">បង់ថយ (Reducing Balance)</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Duration (Months) <span class="text-danger">*</span></label>
                <input type="number" name="duration_months" class="form-control" min="1" max="360" value="12" required>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Payment Frequency <span class="text-danger">*</span></label>
                <select name="payment_frequency" class="form-control">
                    <option value="monthly">Monthly</option>
                    <option value="weekly">Weekly</option>
                    <option value="daily">Daily</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>First Due Date <span class="text-danger">*</span></label>
                <input type="date" name="first_due_date" class="form-control" value="{{ Carbon\Carbon::today()->addMonth()->format('Y-m-d') }}">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Currency <span class="text-danger">*</span></label>
                <select name="currency" class="form-control">
                    <option value="USD">USD</option>
                    <option value="KHR">KHR</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Exchange Rate</label>
                <input type="number" step="0.0001" name="exchange_rate" class="form-control" value="1">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Assigned Collector</label>
                <select name="assigned_collector_id" class="form-control select2" style="width:100%">
                    <option value="">-- None --</option>
                    @foreach($collectors as $c)
                        <option value="{{ $c->id }}" {{ (string) $c->id === (string) ($defaultCollectorId ?? '') ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Penalty Type</label>
                <select name="penalty_type" class="form-control">
                    <option value="fixed">Fixed</option>
                    <option value="percentage">Percentage</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Penalty Amount</label>
                <input type="number" step="0.01" name="penalty_amount" class="form-control" value="0" min="0">
            </div>
        </div>
        <div class="col-sm-12 col-md-6">
            <div class="form-group">
                <label>Note</label>
                <input name="note" class="form-control" placeholder="Optional note">
            </div>
        </div>
    </div>
</div>
