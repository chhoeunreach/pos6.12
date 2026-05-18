@extends('loanmanagement::layouts.app')
@section('title', 'Create Loan From Sell')
@section('content_body')
<section class="content-header"><h1>Create Loan From Sell</h1></section>
<section class="content">
@if(session('duplicate_installment_warning'))
<div class="alert alert-warning">
    <strong>{{ session('duplicate_installment_warning') }}</strong>
    @if(session('duplicate_loan_url'))
        <a href="{{ session('duplicate_loan_url') }}" class="btn btn-xs btn-primary m-l-10">View Loan</a>
    @endif
</div>
@endif
<div class="box box-primary">
    <div class="box-header"><h3 class="box-title">Sell Search Section</h3></div>
    <div class="box-body">
        <form id="sellSearchForm" class="row">
            <div class="col-md-3"><div class="form-group"><label>Invoice Number</label><input name="invoice_no" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Customer Name</label><input name="customer_name" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Customer Phone</label><input name="customer_phone" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Business Location</label><select name="location_id" class="form-control"><option value="">All</option>@foreach($locations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div></div>
            <div class="col-md-3"><div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Payment Status</label><select name="payment_status" class="form-control"><option value="">All</option>@foreach($paymentStatuses as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div></div>
            <div class="col-md-3"><div class="form-group"><label>Final Total</label><input type="number" step="0.01" name="final_total" class="form-control"></div></div>
            <div class="col-md-3"><div class="form-group"><label>IMEI / Lot</label><input name="imei_or_lot" class="form-control"></div></div>
            <div class="col-md-12"><button type="button" class="btn btn-primary" id="btnSearchSells">Search</button></div>
        </form>
    </div>
</div>

<div class="box box-solid">
    <div class="box-header"><h3 class="box-title">Sell List</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered" id="sellSearchTable">
            <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Phone</th><th>Location</th><th>Total</th><th>Paid</th><th>Due</th><th>Payment Status</th><th>Action</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createLoanWorkspaceModal" tabindex="-1" role="dialog" aria-labelledby="createLoanWorkspaceLabel">
    <div class="modal-dialog modal-xl" role="document" style="width: 95%; max-width: 1400px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="createLoanWorkspaceLabel">Create Loan Workspace</h4>
            </div>
            <div class="modal-body" id="createLoanFromSellFormContainer">
                <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>
</div>
</section>
@endsection

@section('javascript')
<script>
(function($){
    const searchUrl = "{{ route('loan-management.loans.search-sells') }}";
    const cloneBase = "{{ url('/loan-management/loans/sell') }}";

    function loadSells(){
        $.get(searchUrl, $('#sellSearchForm').serialize(), function(res){
            const rows = res.data || [];
            const tb = $('#sellSearchTable tbody');
            tb.empty();
            if(!rows.length){ tb.append('<tr><td colspan="10" class="text-center">No sells found</td></tr>'); return; }
            rows.forEach(r => {
                const action = r.is_converted
                    ? '<button class="btn btn-xs btn-success" disabled>Already Added</button>'
                    : '<button class="btn btn-xs btn-primary btn-clone" data-id="'+r.id+'">Create Loan</button>';
                tb.append('<tr><td>'+r.transaction_date+'</td><td>'+r.invoice_no+'</td><td>'+r.customer_name+'</td><td>'+(r.customer_phone||'')+'</td><td>'+(r.location_name||'')+'</td><td>'+Number(r.final_total).toFixed(2)+'</td><td>'+Number(r.paid_amount).toFixed(2)+'</td><td>'+Number(r.due_amount).toFixed(2)+'</td><td>'+r.payment_status+'</td><td>'+action+'</td></tr>');
            });
        });
    }

    function bindFormActions(){
        const form = $('#createLoanFromSellForm');
        function updatePaymentSummary(){
            const parseNum = (v) => {
                const n = parseFloat(String(v ?? '').replace(/,/g, '').trim());
                return Number.isFinite(n) ? n : 0;
            };
            const totalAmount = parseNum(form.find('#loan_total_amount_value').val() || form.find('#loan_total_amount_display').val());
            const paid = parseNum(form.find('#payment_amount_input').val());
            const due = Math.max(0, totalAmount - paid);
            form.find('#down_payment_hidden').val(paid.toFixed(2));
            form.find('#loan_total_paid_display').val(paid.toFixed(2));
            form.find('#loan_total_due_display').val(due.toFixed(2));
        }

        form.find('#principal_amount_input, #payment_amount_input, input[name=\"interest_rate\"], input[name=\"duration_months\"]').off('input change').on('input change', updatePaymentSummary);
        updatePaymentSummary();

        $('#btnPreviewSchedule').off('click').on('click', function(){
            $.post("{{ route('loan-management.loans.preview-schedule') }}", form.serialize(), function(res){
                const rows = res.data || [];
                let tb = form.find('#schedulePreviewTable tbody').first();
                if (!tb.length) {
                    tb = $('#schedulePreviewTable:visible tbody').first();
                }
                const table = tb.closest('table');
                tb.empty();
                let totalPrincipal = 0;
                let totalInterest = 0;
                let totalAmount = 0;
                let totalBalance = 0;
                rows.forEach(r => {
                    totalPrincipal += Number(r.principal || 0);
                    totalInterest += Number(r.interest || 0);
                    totalAmount += Number(r.total || 0);
                    totalBalance += Number(r.balance || 0);
                    tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td>'+Number(r.principal || 0).toFixed(2)+'</td><td>'+Number(r.interest || 0).toFixed(2)+'</td><td>'+Number(r.total || 0).toFixed(2)+'</td><td>'+Number(r.balance || 0).toFixed(2)+'</td></tr>');
                });
                table.find('.schedule-total-principal').text(totalPrincipal.toFixed(2));
                table.find('.schedule-total-interest').text(totalInterest.toFixed(2));
                table.find('.schedule-total-amount').text(totalAmount.toFixed(2));
                table.find('.schedule-total-balance').text(totalBalance.toFixed(2));
                const footerCells = table.find('tfoot tr th');
                footerCells.eq(1).text(totalPrincipal.toFixed(2));
                footerCells.eq(2).text(totalInterest.toFixed(2));
                footerCells.eq(3).text(totalAmount.toFixed(2));
                footerCells.eq(4).text(totalBalance.toFixed(2));
            }).fail(function(xhr){
                alert(xhr.responseJSON?.message || 'Failed to preview schedule');
            });
        });

        $('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan').off('click').on('click', function(){
            form.find('input[name="action_type"]').val($(this).data('action'));
            form.trigger('submit');
        });

        form.off('submit').on('submit', function(e){
            e.preventDefault();
            const submitBtn = $('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan');
            submitBtn.prop('disabled', true);

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                success: function(res){
                    alert(res.message || 'Loan created successfully');
                    if(res?.data?.loan_id){
                        $('#createLoanWorkspaceModal').modal('hide');
                        window.location = "{{ url('/loan-management/loans') }}/" + res.data.loan_id + "/view";
                        return;
                    }
                    loadSells();
                },
                error: function(xhr){
                    if(xhr.status === 422 && xhr.responseJSON?.errors){
                        const errors = xhr.responseJSON.errors;
                        const firstKey = Object.keys(errors)[0];
                        alert(errors[firstKey][0] || xhr.responseJSON?.message || 'Validation failed');
                    } else {
                        alert(xhr.responseJSON?.message || 'Failed to create loan');
                    }
                },
                complete: function(){
                    submitBtn.prop('disabled', false);
                }
            });
        });
    }

    $(document).on('click', '.btn-clone', function(){
        const id = $(this).data('id');
        $('#createLoanFromSellFormContainer').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
        $('#createLoanWorkspaceModal').modal('show');
        $.get(cloneBase + '/' + id + '/clone', function(res){
            if(!res.success){ alert(res.message); $('#createLoanWorkspaceModal').modal('hide'); return; }
            $('#createLoanFromSellFormContainer').html(res.data.form_html);
            bindFormActions();
        }).fail(function(xhr){
            alert(xhr.responseJSON?.message || 'Failed to load sell data');
            $('#createLoanWorkspaceModal').modal('hide');
        });
    });

    $('#btnSearchSells').on('click', loadSells);
    loadSells();
})(jQuery);
</script>
@endsection
