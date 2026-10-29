<div class="modal-dialog modal-md" role="document">
    <div class="modal-content" style="border-radius:12px;overflow:hidden;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <form method="POST" action="{{ route('loan-management.loans.ptp.store', $loanRow->id) }}" id="loanPtpForm">
            @csrf
            <div class="modal-header" style="background:#0f172a;color:#fff;padding:16px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight:700;display:flex;align-items:center;gap:8px;">
                    <i class="fa fa-calendar-check-o" style="color:#38bdf8;"></i>
                    Promise to Pay (PTP) / កត់ត្រាការសន្យាសង
                </h4>
            </div>

            <div class="modal-body" style="padding:20px;">
                <!-- Loan Snapshot -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-muted">Loan Account:</span>
                        <strong style="color:#1e293b;">{{ $loanRow->loan_number ?? ('#'.$loanRow->id) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-muted">Borrower / អតិថិជន:</span>
                        <strong>{{ $loanRow->customer_name_snapshot ?? '-' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-top:1px dashed #cbd5e1;padding-top:6px;margin-top:6px;">
                        <span class="text-muted">Total Overdue / ប្រាក់ត្រូវសង:</span>
                        <strong style="color:#ef4444;font-size:16px;">${{ number_format($overdueBalance, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Promised Payment Date / កាលបរិច្ឆេទសន្យាសង</strong> <span class="text-danger">*</span></label>
                    <input type="date" name="ptp_date" class="form-control" value="{{ \Carbon\Carbon::tomorrow()->toDateString() }}" min="{{ date('Y-m-d') }}" required style="font-size:15px;height:42px;font-weight:600;">
                    <small class="text-muted">The exact date customer committed to make repayment.</small>
                </div>

                <div class="form-group">
                    <label><strong>Promised Amount / ចំនួនទឹកប្រាក់សន្យាសង ($)</strong> <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="ptp_amount" class="form-control" value="{{ number_format($overdueBalance, 2, '.', '') }}" required style="font-size:17px;font-weight:700;color:#0369a1;height:42px;">
                </div>

                <div class="form-group">
                    <label>Collection Notes / កំណត់សម្គាល់ការទារបំណុល</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="e.g. Borrower promised salary payday transfer on 5th via ABA KHQR..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc;padding:12px 20px;border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="btnSubmitPtp" style="background:#0284c7;border-color:#0284c7;font-weight:600;">
                    <i class="fa fa-save"></i> Save Promise to Pay
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$('#loanPtpForm').on('submit', function(e) {
    e.preventDefault();
    var form = $(this);
    var btn = $('#btnSubmitPtp');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: form.attr('action'),
        type: 'POST',
        data: form.serialize(),
        success: function(res) {
            if (res.success) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(res.message);
                } else {
                    alert(res.message);
                }
                form.closest('.modal').modal('hide');
                if (typeof $('#overdue_loans_table').DataTable === 'function') {
                    $('#overdue_loans_table').DataTable().ajax.reload(null, false);
                }
            } else {
                alert(res.message || 'Operation failed');
            }
        },
        error: function(xhr) {
            var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error logging PTP.';
            alert(msg);
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Promise to Pay');
        }
    });
});
</script>
