<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <form method="post" action="{{ route('hr-sell.reports.update', [$report->id]) }}" id="hr_sell_report_edit_form">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-edit"></i> Edit HR Sell Report</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Invoice</label>
                            <input type="text" name="invoice_no" class="form-control" value="{{ $report->invoice_no }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Original Invoice</label>
                            <input type="text" name="original_invoice_no" class="form-control" value="{{ $report->original_invoice_no }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="datetime-local" name="created_at" class="form-control" value="{{ $report->created_at ? date('Y-m-d\TH:i', strtotime($report->created_at)) : now()->format('Y-m-d\TH:i') }}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Location / Branch</label>
                            <input type="text" name="branch_name" class="form-control" list="hr_sell_report_branch_list" value="{{ $report->branch_name }}">
                            <datalist id="hr_sell_report_branch_list">
                                @foreach($hrBranches as $branch => $name)
                                    <option value="{{ $name }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Sell Type</label>
                            <select name="service_type" class="form-control">
                                @foreach($hrSellTypes as $type => $name)
                                    <option value="{{ $type }}" @selected((string) $selectedSellType === (string) $type)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Total Amount</label>
                            <input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="{{ $report->total_amount }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Customer</label>
                            <input type="text" name="customer_name" class="form-control" value="{{ $report->customer_name }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ $report->customer_phone }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Seller</label>
                            <input type="text" name="seller_name" class="form-control" value="{{ $report->seller_name }}">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note" class="form-control" rows="4">{{ $report->note }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#hr_sell_report_edit_form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        form.find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(result) {
                if (result.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(result.msg);
                    }

                    $('.view_modal').modal('hide');
                    window.location.reload();
                } else if (typeof toastr !== 'undefined') {
                    toastr.error(result.msg || 'Unable to update HR sell report');
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to update HR sell report';

                if (typeof toastr !== 'undefined') {
                    toastr.error(message);
                } else {
                    alert(message);
                }
            },
            complete: function() {
                form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });
});
</script>
