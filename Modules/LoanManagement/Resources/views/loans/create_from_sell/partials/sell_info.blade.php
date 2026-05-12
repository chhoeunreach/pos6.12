<div class="box box-primary">
    <div class="box-header"><h3 class="box-title">Sell Information</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Invoice No:</strong> {{ $sell['transaction']->invoice_no }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Sell Date:</strong> {{ $sell['transaction']->transaction_date }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Customer:</strong> {{ $sell['transaction']->customer_name }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Phone:</strong> {{ $sell['transaction']->customer_phone }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Location:</strong> {{ $sell['transaction']->location_name_snapshot }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Final Total:</strong> {{ number_format($sell['transaction']->final_total,2) }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Paid Amount:</strong> {{ number_format($sell['paid_amount'],2) }}</div>
        <div class="col-sm-6 col-md-3 loan-info-item"><strong>Due Amount:</strong> {{ number_format($sell['due_amount'],2) }}</div>
    </div>
</div>
