<div class="box box-success">
    <div class="box-header"><h3 class="box-title">Payment Information</h3></div>
    <div class="box-body row">
        <div class="col-md-12">
            <h4 class="m-0">Payment info:</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead style="background:#35c787;color:#fff;">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Reference No</th>
                            <th>Amount</th>
                            <th>Payment mode</th>
                            <th>Payment note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($sell['payment_rows'] ?? []) as $idx => $p)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ !empty($p->paid_on) ? \Carbon\Carbon::parse($p->paid_on)->format('d-m-Y') : '-' }}</td>
                                <td>{{ $p->payment_ref_no ?? '-' }}</td>
                                <td>${{ number_format((float)($p->amount ?? 0), 2) }}</td>
                                <td>{{ strtoupper((string)($p->method ?? '-')) }}</td>
                                <td>{{ $p->note ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">No payment info found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <hr>
        </div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Amount</label><input type="number" step="0.01" id="payment_amount_input" name="payment[amount]" class="form-control" value="{{ $sell['defaults']['down_payment'] ?? 0 }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Paid Date</label><input type="date" name="payment[paid_date]" class="form-control" value="{{ date('Y-m-d') }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Method</label><select name="payment[payment_method_id]" class="form-control"><option value="">Select</option>@foreach(($paymentMethods ?? []) as $m)<option value="{{ $m->id }}" {{ (int) ($defaultPaymentMethodId ?? 0) === (int) $m->id ? 'selected' : '' }}>{{ $m->name }}</option>@endforeach</select></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Reference Number</label><input name="payment[reference_number]" class="form-control"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Currency</label><select name="payment[currency]" class="form-control"><option value="USD">USD</option><option value="KHR">KHR</option></select></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Exchange Rate</label><input type="number" step="0.0001" name="payment[exchange_rate]" class="form-control" value="{{ $sell['defaults']['exchange_rate'] ?? 1 }}"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Status</label><select name="payment[status]" class="form-control"><option value="completed">Completed</option><option value="pending">Pending</option><option value="failed">Failed</option></select></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Received By</label><input class="form-control" value="{{ trim((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')) }}" readonly></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Account Name</label><input name="payment[account_name]" class="form-control"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Account Number</label><input name="payment[account_number]" class="form-control"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Transaction ID</label><input name="payment[transaction_id]" class="form-control"></div></div>
        <div class="col-sm-6 col-md-3"><div class="form-group"><label>Channel</label><input name="payment[channel]" class="form-control" placeholder="Cash / ABA / Bank / Card"></div></div>
        <div class="col-sm-12 col-md-12"><div class="form-group"><label>Payment Note</label><input name="payment[note]" class="form-control"></div></div>
    </div>
</div>
