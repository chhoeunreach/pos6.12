<div class="box box-success">
    <div class="box-header"><h3 class="box-title">Payment Summary</h3></div>
    <div class="box-body row">
        @if(!empty($loanLocation) && (!empty($loanLocation->payment_qr_asset_url) || !empty($loanLocation->telegram_qr_asset_url)))
            <div class="col-md-12">
                <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
                    <div style="flex:1 1 220px;min-width:220px;">
                        <strong>Installment Location:</strong> {{ $loanLocation->name ?? ($sell['transaction']->location_name_snapshot ?? '-') }}<br>
                        @if(!empty($loanLocation->phone))
                            <strong>Phone:</strong> {{ $loanLocation->phone }}<br>
                        @endif
                        @if(!empty($loanLocation->address))
                            <strong>Address:</strong> {{ $loanLocation->address }}
                        @endif
                    </div>
                    @if(!empty($loanLocation->payment_qr_asset_url))
                        <div style="text-align:center;min-width:140px;">
                            <div style="font-weight:600;margin-bottom:6px;">Payment QR Code</div>
                            <img src="{{ $loanLocation->payment_qr_asset_url }}" alt="Payment QR" style="max-width:140px;max-height:140px;border:1px solid #d9edf7;border-radius:6px;padding:6px;background:#fff;" onerror="this.style.display='none';">
                        </div>
                    @endif
                    @if(!empty($loanLocation->telegram_qr_asset_url))
                        <div style="text-align:center;min-width:140px;">
                            <div style="font-weight:600;margin-bottom:6px;">Telegram QR Code</div>
                            <img src="{{ $loanLocation->telegram_qr_asset_url }}" alt="Telegram QR" style="max-width:140px;max-height:140px;border:1px solid #d9edf7;border-radius:6px;padding:6px;background:#fff;" onerror="this.style.display='none';">
                        </div>
                    @endif
                </div>
            </div>
        @endif
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
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                {!! Form::label('loan_payment_method', lm_label('lang_v1.payment_method', 'Payment Method', 'វិធីបង់ប្រាក់') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::select('payment[method]', $paymentTypes ?? [], $defaultPaymentMethod ?? 'cash', ['class' => 'form-control payment_types_dropdown select2', 'id' => 'loan_payment_method', 'style' => 'width:100%;']) !!}
                </div>
            </div>
        </div>
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
