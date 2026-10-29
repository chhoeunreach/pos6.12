<div class="modal-dialog modal-md" role="document">
    <div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <form method="POST" action="{{ route('loan-management.loans.settlement.process', $loanRow->id) }}" id="loanSettlementForm">
            @csrf
            <div class="modal-header" style="background:#1e293b;color:#fff;padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight:700;">
                    <i class="fa fa-handshake-o" style="color:#10b981;margin-right:8px;"></i>
                    Early Payoff & Loan Settlement / ទូទាត់ផ្តាច់កម្ចី
                </h4>
            </div>

            <div class="modal-body" style="padding:20px;">
                <!-- Loan Summary Card -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-muted">Loan Account:</span>
                        <strong>{{ $loanRow->loan_number ?? ('#'.$loanRow->id) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-muted">Customer:</span>
                        <strong>{{ $loanRow->customer_name_snapshot ?? '-' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-muted">Unpaid Terms:</span>
                        <span class="badge" style="background:#3b82f6;">{{ count($schedules) }} Terms Remaining</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-top:1px dashed #cbd5e1;padding-top:6px;margin-top:6px;">
                        <span class="text-muted">Outstanding Principal:</span>
                        <strong style="color:#0f172a;">${{ number_format($principalBalance, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong>
                    </div>
                    @if($accruedPenalties > 0)
                    <div style="display:flex;justify-content:space-between;margin-top:4px;">
                        <span class="text-danger">Accrued Penalties:</span>
                        <strong class="text-danger">+${{ number_format($accruedPenalties, 2) }}</strong>
                    </div>
                    @endif
                    @if($futureInterest > 0)
                    <div style="display:flex;justify-content:space-between;margin-top:4px;">
                        <span class="text-muted">Future Interest (To Rebate):</span>
                        <span class="text-muted"><del>${{ number_format($futureInterest, 2) }}</del></span>
                    </div>
                    @endif
                </div>

                <div class="form-group">
                    <label><strong>Final Settlement Amount / ទឹកប្រាក់ទូទាត់ផ្តាច់ ($)</strong> <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="settlement_amount" id="settlement_amount" class="form-control" value="{{ number_format($suggestedSettlement, 2, '.', '') }}" required style="font-size:18px;font-weight:800;color:#10b981;height:44px;">
                    <small class="text-muted">Principal ($ {{ number_format($principalBalance, 2) }}) + Penalties ($ {{ number_format($accruedPenalties, 2) }}) with unearned interest waived.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Payment Method / វិធីសាស្ត្រទូទាត់ <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            @foreach($paymentTypes as $code => $label)
                                <option value="{{ $code }}" {{ $code === $defaultPaymentMethod ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Paid Date / ថ្ងៃបង់ប្រាក់ <span class="text-danger">*</span></label>
                        <input type="date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Prepayment Fee (If any)</label>
                        <input type="number" step="0.01" name="prepayment_fee" class="form-control" value="0.00" placeholder="0.00">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Interest Discount / Rebate</label>
                        <input type="number" step="0.01" name="interest_discount" class="form-control" value="{{ number_format($futureInterest, 2, '.', '') }}" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Settlement Note / មូលហេតុផ្តាច់</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="e.g. Borrower closed loan early with full principal payoff"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel / បោះបង់</button>
                <button type="submit" class="btn btn-success" style="font-weight:700;" id="btnSubmitSettlement">
                    <i class="fa fa-check-circle"></i> Confirm Payoff & Close Loan / បញ្ជាក់ការទូទាត់ផ្តាច់
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$('#loanSettlementForm').on('submit', function(e) {
    e.preventDefault();
    if (!confirm('Are you sure you want to execute early payoff and permanently close this loan?')) return;

    var btn = $('#btnSubmitSettlement').prop('disabled', true);
    $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: $(this).serialize(),
        success: function(resp) {
            btn.prop('disabled', false);
            if (resp && resp.success) {
                if (window.toastr) toastr.success(resp.message);
                $('.modal').modal('hide');
                window.location.reload();
            } else {
                alert(resp.message || 'Error occurred');
            }
        },
        error: function(xhr) {
            btn.prop('disabled', false);
            alert(xhr.responseJSON ? xhr.responseJSON.message : 'Error processing settlement');
        }
    });
});
</script>
