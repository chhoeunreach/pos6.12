@extends('layouts.app')
@section('title', 'Convert To Installment')
@section('content')
<section class="content-header"><h1>Convert To Installment</h1></section>
<section class="content">
    @include('loanmanagement::loans.create_from_sell.form', ['sell' => $sell, 'collectors' => $collectors])
</section>
@endsection

@section('javascript')
<script>
(function($){
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
                        window.location = "{{ url('/loan-management/loans') }}/" + res.data.loan_id + "/view";
                        return;
                    }
                    window.location = "{{ route('loan-management.loans.create-from-sell') }}";
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
    bindFormActions();
})(jQuery);
</script>
@endsection
