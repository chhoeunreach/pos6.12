@extends('layouts.app')
@section('title', 'Loan Management Dashboard')

@section('content')
<section class="content-header">
    <h1>Installment / Loan Dashboard</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form id="loanDashboardFilter" class="row" autocomplete="off">
                <div class="col-md-2"><label>Date From</label><input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] }}"></div>
                <div class="col-md-2"><label>Date To</label><input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] }}"></div>
                <div class="col-md-2"><label>Location</label><select class="form-control" name="business_location_id"><option value="">All</option>@foreach($locations as $x)<option value="{{ $x['id'] }}">{{ $x['name'] }}</option>@endforeach</select></div>
                <div class="col-md-2"><label>Loan Status</label><select class="form-control" name="loan_status"><option value="">All</option>@foreach($statuses as $x)<option value="{{ $x }}">{{ ucfirst($x) }}</option>@endforeach</select></div>
                <div class="col-md-2"><label>Collector</label><select class="form-control" name="collector_id"><option value="">All</option>@foreach($collectors as $x)<option value="{{ $x['id'] }}">{{ $x['name'] }}</option>@endforeach</select></div>
                <div class="col-md-1"><label>Currency</label><select class="form-control" name="currency"><option value="">All</option>@foreach($currencies as $x)<option value="{{ $x }}">{{ $x }}</option>@endforeach</select></div>
                <div class="col-md-1"><label>Payment</label><select class="form-control" name="payment_method_id"><option value="">All</option>@foreach($paymentMethods as $x)<option value="{{ $x['id'] }}">{{ $x['name'] }}</option>@endforeach</select></div>
            </form>
            <div class="m-t-10">
                <button class="btn btn-primary" id="btnRefresh"><i class="fa fa-refresh"></i> Refresh Dashboard</button>
                <span id="dashboardLoading" class="m-l-10 text-info" style="display:none;"><i class="fa fa-spinner fa-spin"></i> Loading...</span>
            </div>
        </div>
    </div>

    <div class="row" id="cardsContainer"></div>

    <div class="row">
        @foreach(['monthly_loan'=>'Monthly Loan Created','monthly_collection'=>'Monthly Collection','loan_status'=>'Loan Status Pie','payment_method'=>'Payment Method','overdue_aging'=>'Overdue Aging','collector_performance'=>'Collector Performance','customer_status'=>'Customer Status','daily_collection'=>'Daily Collection'] as $k=>$t)
        <div class="col-md-6"><div class="box box-solid"><div class="box-header"><h3 class="box-title">{{ $t }}</h3></div><div class="box-body"><canvas id="chart_{{ $k }}" height="120"></canvas></div></div></div>
        @endforeach
    </div>

    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Latest Loans</h3><button class="btn btn-xs btn-default pull-right export-btn" data-table="latest_loans">Export CSV</button></div><div class="box-body"><table id="table_latest_loans" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Today Due Payments</h3></div><div class="box-body"><table id="table_today_due_payments" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Overdue Customers</h3><button class="btn btn-xs btn-default pull-right export-btn" data-table="overdue_customers">Export CSV</button></div><div class="box-body"><table id="table_overdue_customers" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Recent Payments</h3><button class="btn btn-xs btn-default pull-right export-btn" data-table="recent_payments">Export CSV</button></div><div class="box-body"><table id="table_recent_payments" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">ABA Transactions</h3></div><div class="box-body"><table id="table_aba_transactions" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Staff Latest Location</h3></div><div class="box-body"><table id="table_staff_latest_locations" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Follow Up Customers</h3></div><div class="box-body"><table id="table_follow_up_customers" class="table table-bordered table-striped" width="100%"></table></div></div>
    <div class="box box-solid"><div class="box-header"><h3 class="box-title">Blacklist Customers</h3></div><div class="box-body"><table id="table_blacklist_customers" class="table table-bordered table-striped" width="100%"></table></div></div>
</section>
@endsection

@section('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function($){
    const url = "{{ route('loan-management.dashboard.data') }}";
    const cardsMeta = [
        ['total_loans','Total Loans','fa-list'],['new_loans_this_month','New Loans This Month','fa-calendar'],['active_loans','Active Loans','fa-check'],['completed_loans','Completed Loans','fa-check-circle'],['pending_loans','Pending Loans','fa-clock-o'],['rejected_loans','Rejected Loans','fa-times-circle'],['cancelled_loans','Cancelled Loans','fa-ban'],['overdue_loans','Overdue Loans','fa-exclamation-triangle'],
        ['total_principal','Total Principal','fa-money'],['total_payable','Total Payable','fa-money'],['total_paid','Total Paid','fa-money'],['total_balance','Total Balance','fa-balance-scale'],['today_collection','Today Collection','fa-credit-card'],['month_collection','This Month Collection','fa-line-chart'],['penalty_collected','Penalty Collected','fa-gavel'],['discount_given','Discount Given','fa-tag'],
        ['total_customers','Total Customers','fa-users'],['active_customers','Active Customers','fa-user'],['late_customers','Late Customers','fa-user-times'],['follow_up_customers','Follow Up Customers','fa-phone'],['blacklist_customers','Blacklist Customers','fa-user-secret'],['aba_pending','ABA Pending','fa-university'],['aba_paid','ABA Paid','fa-university'],['aba_failed','ABA Failed','fa-university'],['collection_visits_today','Collection Visits Today','fa-map-marker'],['staff_online','Staff Online','fa-wifi'],['payment_proof_pending','Payment Proof Pending','fa-file-image-o'],['id_card_scan_pending','ID Card Scan Pending','fa-id-card']
        ,['converted_sales','Converted Sales','fa-exchange'],['pending_sales_for_installment','Pending Sales For Installment','fa-clock-o']
    ];

    const charts = {};
    const tables = {};
    let lastTables = {};

    function formatMoney(v){ return Number(v || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function badge(type, value){
        const map = {draft:'default',pending:'warning',approved:'info',active:'primary',completed:'success',rejected:'danger',cancelled:'default',defaulted:'danger',unpaid:'default',partial:'warning',paid:'success',late:'danger',waived:'info',inactive:'default',blacklist:'danger',failed:'danger',expired:'default'};
        const c = map[(value || '').toString().toLowerCase()] || 'default';
        return '<span class="label label-'+c+'">'+value+'</span>';
    }

    function drawCards(cards){
        let html = '';
        cardsMeta.forEach(m => {
            const v = (m[0].includes('total_') || m[0].includes('collection') || m[0].includes('principal') || m[0].includes('payable') || m[0].includes('paid') || m[0].includes('balance') || m[0].includes('penalty') || m[0].includes('discount')) ? formatMoney(cards[m[0]]) : (cards[m[0]] || 0);
            html += '<div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa '+m[2]+'"></i></span><div class="info-box-content"><span class="info-box-text">'+m[1]+'</span><span class="info-box-number">'+v+'</span></div></div></div>';
        });
        $('#cardsContainer').html(html);
    }

    function makeChart(id, type, labels, datasets){
        if(charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), {type, data:{labels, datasets}, options:{responsive:true, maintainAspectRatio:false}});
    }

    function initOrReloadTable(id, columns, rows){
        if(tables[id]){ tables[id].clear().rows.add(rows).draw(); return; }
        tables[id] = $('#'+id).DataTable({data: rows, columns: columns, destroy: true, pageLength: 10});
    }

    function csvExport(key){
        const rows = lastTables[key] || [];
        if(!rows.length){ alert('No data to export.'); return; }
        const headers = Object.keys(rows[0]);
        const csv = [headers.join(',')].concat(rows.map(r => headers.map(h => '"'+String(r[h] ?? '').replace(/"/g,'""')+'"').join(','))).join('\n');
        const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = key + '.csv'; a.click();
    }

    function loadDashboard(){
        $('#dashboardLoading').show();
        $.get(url, $('#loanDashboardFilter').serialize())
            .done(function(res){
                const d = (res.data || {cards:{}, charts:{}, tables:{}});
                drawCards(d.cards || {});

                const c = d.charts || {};
                makeChart('chart_monthly_loan', 'bar', c.monthly_loan?.labels || [], [{label:'Loans', data:c.monthly_loan?.count || []},{label:'Principal', data:c.monthly_loan?.principal || []}]);
                makeChart('chart_monthly_collection', 'line', c.monthly_collection?.labels || [], [{label:'Collection', data:c.monthly_collection?.amount || []}]);
                makeChart('chart_loan_status', 'pie', c.loan_status?.labels || [], [{data:c.loan_status?.series || []}]);
                makeChart('chart_payment_method', 'doughnut', c.payment_method?.labels || [], [{data:c.payment_method?.amount || []}]);
                makeChart('chart_overdue_aging', 'bar', c.overdue_aging?.labels || [], [{label:'Overdue', data:c.overdue_aging?.series || []}]);
                makeChart('chart_collector_performance', 'bar', (c.collector_performance || []).map(x=>x.collector), [{label:'Assigned', data:(c.collector_performance || []).map(x=>x.assigned_loans)}, {label:'Collected', data:(c.collector_performance || []).map(x=>x.collected_amount)}]);
                makeChart('chart_customer_status', 'pie', c.customer_status?.labels || [], [{data:c.customer_status?.series || []}]);
                makeChart('chart_daily_collection', 'line', c.daily_collection?.labels || [], [{label:'Daily Collection', data:c.daily_collection?.amount || []}]);

                const t = d.tables || {};
                lastTables = t;
                initOrReloadTable('table_latest_loans', [{title:'Loan #',data:'loan_number'},{title:'Customer',data:'customer_name_snapshot'},{title:'Phone',data:'customer_phone_snapshot'},{title:'Principal',data:'principal_amount'},{title:'Paid',data:'paid_amount'},{title:'Balance',data:'balance_amount'},{title:'Currency',data:'currency'},{title:'Status',data:'status',render:v=>badge('loan',v)},{title:'Loan Date',data:'loan_date'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-info">View</a>'}], t.latest_loans || []);
                initOrReloadTable('table_today_due_payments', [{title:'Loan #',data:'loan_number'},{title:'Customer',data:'customer'},{title:'Phone',data:'phone'},{title:'Due Date',data:'due_date'},{title:'Schedule Amount',data:'schedule_amount'},{title:'Paid Amount',data:'paid_amount'},{title:'Balance',data:'balance'},{title:'Collector',data:'collector'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-success">Receive</a>'}], t.today_due_payments || []);
                initOrReloadTable('table_overdue_customers', [{title:'Loan #',data:'loan_number'},{title:'Customer',data:'customer'},{title:'Phone',data:'phone'},{title:'Overdue Days',data:'overdue_days'},{title:'Overdue Amount',data:'overdue_amount'},{title:'Collector',data:'collector'},{title:'Last Visit',data:'last_visit'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-info">View</a>'}], t.overdue_customers || []);
                initOrReloadTable('table_recent_payments', [{title:'Receipt #',data:'receipt_number'},{title:'Customer',data:'customer_name_snapshot'},{title:'Loan #',data:'loan_number'},{title:'Paid Amount',data:'paid_amount'},{title:'Method',data:'payment_method'},{title:'Received By',data:'received_by_name_snapshot'},{title:'Paid Date',data:'paid_date'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-default">Receipt</a>'}], t.recent_payments || []);
                initOrReloadTable('table_aba_transactions', [{title:'Tran ID',data:'tran_id'},{title:'Customer',data:'customer'},{title:'Amount',data:'amount'},{title:'Currency',data:'currency'},{title:'Status',data:'status',render:v=>badge('aba',v)},{title:'Created',data:'created_at'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-warning">Check</a>'}], t.aba_transactions || []);
                initOrReloadTable('table_staff_latest_locations', [{title:'Staff',data:'staff_name_snapshot'},{title:'Latitude',data:'latitude'},{title:'Longitude',data:'longitude'},{title:'Battery',data:'battery_level'},{title:'Recorded At',data:'recorded_at'},{title:'Status',data:'online_status',render:v=>badge('staff',v)}], t.staff_latest_locations || []);
                initOrReloadTable('table_follow_up_customers', [{title:'Customer',data:'customer'},{title:'Phone',data:'phone'},{title:'Follow Up Date',data:'follow_up_date'},{title:'Type',data:'follow_up_type'},{title:'Status',data:'status'},{title:'Assigned Staff',data:'assigned_staff'},{title:'Note',data:'note'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-info">Action</a>'}], t.follow_up_customers || []);
                initOrReloadTable('table_blacklist_customers', [{title:'Customer',data:'customer'},{title:'Phone',data:'phone'},{title:'ID Card',data:'id_card_number'},{title:'Reason',data:'blacklist_reason'},{title:'Created',data:'created_at'},{title:'Action',data:'id',render:v=>'<a href="#" class="btn btn-xs btn-danger">View</a>'}], t.blacklist_customers || []);
            })
            .fail(function(){ alert('Failed to load dashboard data.'); })
            .always(function(){ $('#dashboardLoading').hide(); });
    }

    $('#loanDashboardFilter').on('change', 'input,select', loadDashboard);
    $('#btnRefresh').on('click', function(e){ e.preventDefault(); loadDashboard(); });
    $('.export-btn').on('click', function(){ csvExport($(this).data('table')); });
    loadDashboard();
})(jQuery);
</script>
@endsection
