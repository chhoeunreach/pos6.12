
<?php $__env->startSection('title', 'Installment List'); ?>

<?php $__env->startSection('content_body'); ?>
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Installment List</h1>
</section>

<section class="content no-print">
    <?php $__env->startComponent('components.filters', ['title' => __('report.filters')]); ?>
        <div class="col-md-3">
            <div class="form-group">
                <?php echo Form::label('sell_list_filter_date_range', __('report.date_range') . ':'); ?>

                <?php echo Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); ?>

                <input type="hidden" id="start_date">
                <input type="hidden" id="end_date">
            </div>
        </div>
        <div class="col-md-3"><div class="form-group"><label>Status:</label><select id="status" class="form-control select2" style="width:100%"><option value="">All</option><option>draft</option><option>pending</option><option>approved</option><option>active</option><option>completed</option><option>rejected</option><option>cancelled</option><option>defaulted</option></select></div></div>
        <div class="col-md-3"><div class="form-group"><label>Location:</label><?php echo Form::select('location_name', $locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'location_name']); ?></div></div>
        <div class="col-md-3"><div class="form-group"><label>Collector:</label><?php echo Form::select('collector_name', $collectors, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all'), 'id' => 'collector_name']); ?></div></div>
        <div class="col-md-3"><div class="form-group"><label>Customer:</label><input id="customer" class="form-control" placeholder="Customer name"></div></div>
    <?php echo $__env->renderComponent(); ?>

    <?php $__env->startComponent('components.widget', ['class' => 'box-primary', 'title' => 'Installment List']); ?>
        <table class="table table-bordered table-striped" id="loan_list_table" width="100%">
            <thead>
                <tr>
                    <th>Loan #</th><th>Date</th><th>Source Invoice</th><th>Customer</th><th>Phone</th><th>Location</th><th>Collector</th><th>Principal</th><th>Paid</th><th>Balance</th><th>Status</th><th>Currency</th><th>Action</th>
                </tr>
            </thead>
        </table>
    <?php echo $__env->renderComponent(); ?>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('loan_js'); ?>
<script>
$(document).ready(function(){
    $('.select2').select2();
    function setRange(s, e){
        $('#start_date').val(s.format('YYYY-MM-DD'));
        $('#end_date').val(e.format('YYYY-MM-DD'));
        $('#sell_list_filter_date_range').val(s.format(moment_date_format) + ' ~ ' + e.format(moment_date_format));
    }

    $('#sell_list_filter_date_range').daterangepicker($.extend(true, {}, dateRangeSettings, {autoUpdateInput: false}), function(s, e){
        setRange(s, e);
        loanTable.ajax.reload();
    });

    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(){
        $(this).val('');
        $('#start_date').val('');
        $('#end_date').val('');
        loanTable.ajax.reload();
    });

    var loanTable = $('#loan_list_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "<?php echo e(route('loan-management.loans.list-data'), false); ?>",
            data: function(d){
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
                d.status = $('#status').val();
                d.location_name = $('#location_name').val();
                d.collector_name = $('#collector_name').val();
                d.customer = $('#customer').val();
            }
        },
        columns: [
            {data:'loan_number', name:'l.loan_number'},
            {data:'loan_date', name:'l.loan_date'},
            {data:'source_invoice_no', name:'l.source_invoice_no'},
            {data:'customer_name_snapshot', name:'l.customer_name_snapshot'},
            {data:'customer_phone_snapshot', name:'l.customer_phone_snapshot'},
            {data:'location_name_snapshot', name:'location_name_snapshot', searchable:false},
            {data:'collector_name_snapshot', name:'l.collector_name_snapshot'},
            {data:'principal_amount', name:'l.principal_amount'},
            {data:'paid_amount', name:'l.paid_amount'},
            {data:'balance_amount', name:'l.balance_amount'},
            {data:'status', name:'l.status'},
            {data:'currency', name:'l.currency'},
            {data:'action', name:'action', orderable:false, searchable:false}
        ],
        fnDrawCallback: function(){ __currency_convert_recursively($('#loan_list_table')); }
    });

    $(document).on('change keyup', '#status,#location_name,#collector_name,#customer', function(){
        loanTable.ajax.reload();
    });

    $(document).on('click', '.btn-delete-loan', function(){
        if(!confirm('Delete this loan?')) return;
        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: {_token: $('meta[name=\"csrf-token\"]').attr('content')},
            success: function(){ loanTable.ajax.reload(); },
            error: function(){ alert('Failed to delete loan.'); }
        });
    });

    $(document).on('click', '.btn-change-status', function(e){
        e.preventDefault();
        $.post($(this).data('url'), {
            _token: $('meta[name=\"csrf-token\"]').attr('content'),
            status: $(this).data('status')
        }, function(){ loanTable.ajax.reload(); }).fail(function(){ alert('Failed to update status.'); });
    });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('loanmanagement::layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/loans/index.blade.php ENDPATH**/ ?>