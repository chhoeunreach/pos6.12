<style>
    #createLoanFromSellForm.loan-create-form .box { margin-bottom: 16px; }
    #createLoanFromSellForm.loan-create-form .box-header { padding: 10px 14px; }
    #createLoanFromSellForm.loan-create-form .box-body { padding: 14px; }
    #createLoanFromSellForm.loan-create-form .row { margin-left: -8px; margin-right: -8px; }
    #createLoanFromSellForm.loan-create-form .row > [class*='col-'] { padding-left: 8px; padding-right: 8px; margin-bottom: 8px; }
    #createLoanFromSellForm.loan-create-form .form-group { margin-bottom: 10px; }
    #createLoanFromSellForm.loan-create-form .loan-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    #createLoanFromSellForm.loan-create-form .loan-actions .btn { margin: 0; }
    #createLoanFromSellForm.loan-create-form .loan-info-item { margin-bottom: 6px; }
    @media (max-width: 767px) {
        #createLoanFromSellForm.loan-create-form .box-body { padding: 10px; }
        #createLoanFromSellForm.loan-create-form .loan-actions .btn { width: 100%; }
    }
</style>
<form id="createLoanFromSellForm" class="loan-create-form" method="POST" action="{{ route('loan-management.loans.store-from-sell') }}">
    @csrf
    <input type="hidden" name="transaction_id" value="{{ $sell['transaction']->id }}">
    <input type="hidden" name="action_type" value="create">

    @include('loanmanagement::loans.create_from_sell.partials.sell_info', ['sell' => $sell])
    @include('loanmanagement::loans.create_from_sell.partials.customer_snapshot', ['sell' => $sell])
    @include('loanmanagement::loans.create_from_sell.partials.product_items', ['sell' => $sell])
    @include('loanmanagement::loans.create_from_sell.partials.loan_terms', ['sell' => $sell, 'collectors' => $collectors])
    @include('loanmanagement::loans.create_from_sell.partials.payment_information', ['sell' => $sell, 'paymentMethods' => $paymentMethods ?? [], 'defaultPaymentMethodId' => $defaultPaymentMethodId ?? null])
    @include('loanmanagement::loans.create_from_sell.partials.schedule_preview')

    <div class="box box-solid">
        <div class="box-body">
            <div class="loan-actions">
                <button type="button" class="btn btn-info" id="btnPreviewSchedule">Preview Schedule</button>
                <button type="button" class="btn btn-default" id="btnSaveDraft" data-action="draft">Save Draft</button>
                <button type="button" class="btn btn-primary" id="btnCreateLoan" data-action="create">Create Loan</button>
                <button type="button" class="btn btn-success" id="btnCreateApproveLoan" data-action="create_approve">Create & Approve Loan</button>
                <button type="button" class="btn btn-danger" onclick="$('#createLoanFromSellFormContainer').html('')">Cancel</button>
            </div>
        </div>
    </div>
</form>
