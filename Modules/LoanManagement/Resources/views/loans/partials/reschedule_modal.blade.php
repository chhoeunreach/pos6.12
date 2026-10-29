<div class="modal-dialog modal-md" role="document">
    <div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <form method="POST" action="{{ route('loan-management.loans.reschedule.process', $loanRow->id) }}" id="loanRescheduleForm">
            @csrf
            <div class="modal-header" style="background:#1e293b;color:#fff;padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight:700;">
                    <i class="fa fa-refresh" style="color:#f59e0b;margin-right:8px;"></i>
                    Loan Restructuring & Rescheduling / រៀបចំរចនាសម្ព័ន្ធកម្ចីឡើងវិញ
                </h4>
            </div>

            <div class="modal-body" style="padding:20px;">
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="color:#92400e;">Current Balance:</span>
                        <strong style="color:#92400e;">${{ number_format($balanceAmount, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="color:#92400e;">Accrued Penalties:</span>
                        <strong style="color:#dc2626;">+${{ number_format($accruedPenalties, 2) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#92400e;">Unpaid Installments:</span>
                        <strong style="color:#92400e;">{{ count($unpaidSchedules) }} Terms</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>New Restructured Principal Amount / ប្រាក់ដើមរៀបចំឡើងវិញ ($)</strong> <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="reschedule_amount" class="form-control" value="{{ number_format($balanceAmount, 2, '.', '') }}" required style="font-size:16px;font-weight:700;">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>New Term / រយៈពេលថ្មី (Months) <span class="text-danger">*</span></label>
                        <select name="new_duration_months" class="form-control" required>
                            @foreach([3, 6, 9, 12, 18, 24, 36] as $m)
                                <option value="{{ $m }}" {{ $m == 12 ? 'selected' : '' }}>{{ $m }} Months</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>New Interest Rate / ការប្រាក់ (% / Yr) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="new_interest_rate" class="form-control" value="{{ $annualInterestRate }}" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Method / វិធីគណនា <span class="text-danger">*</span></label>
                        <select name="interest_type" class="form-control" required>
                            <option value="flat">Flat Rate (ការប្រាក់ថេរ)</option>
                            <option value="reducing_balance">Declining (ថយចុះ)</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>First Due Date / ថ្ងៃបង់លើកដំបូង <span class="text-danger">*</span></label>
                        <input type="date" name="first_due_date" class="form-control" value="{{ now()->addMonth()->format('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="waive_penalties" value="1" checked>
                        <span><strong>Waive Accrued Penalties / លើកលែងប្រាក់ពិន័យចាស់ទាំងអស់</strong></span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Reason for Restructuring / មូលហេតុរៀបចំកម្ចីឡើងវិញ <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Borrower experienced cashflow hardship; extended term to lower monthly payment"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel / បោះបង់</button>
                <button type="submit" class="btn btn-warning" style="font-weight:700;" id="btnSubmitReschedule">
                    <i class="fa fa-refresh"></i> Apply Restructuring / អនុវត្តរៀបចំកម្ចី
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$('#loanRescheduleForm').on('submit', function(e) {
    e.preventDefault();
    if (!confirm('This will archive existing unpaid schedules and generate a brand-new amortization schedule. Proceed?')) return;

    var btn = $('#btnSubmitReschedule').prop('disabled', true);
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
            alert(xhr.responseJSON ? xhr.responseJSON.message : 'Error restructuring loan');
        }
    });
});
</script>
