@extends('layouts.app')
@section('title', 'Live Chat Inbox')

@section('content')
<section class="content-header"><h1>Live Chat Inbox</h1></section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <div class="row" style="margin-bottom:10px;">
                <div class="col-md-2"><input class="form-control" id="f_status" placeholder="status"></div>
                <div class="col-md-2"><input class="form-control" id="f_priority" placeholder="priority"></div>
                <div class="col-md-2"><button id="btnFilter" class="btn btn-default">Filter</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered" id="chat-inbox-table">
                    <thead>
                        <tr>
                            <th>Thread #</th><th>Customer</th><th>Staff</th><th>Loan</th><th>Priority</th><th>Status</th><th>Last Message</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
@include('loanmanagement::layouts.sidebar_focus')
<script>
(function($){
    function loadInbox(){
        $.get('/api/loan-management/chats', {status:$('#f_status').val(), priority:$('#f_priority').val()}, function(resp){
            var rows = (resp && resp.data && resp.data.data) ? resp.data.data : [];
            var tb = $('#chat-inbox-table tbody'); tb.html('');
            rows.forEach(function(r){
                tb.append('<tr><td>'+r.thread_number+'</td><td>'+(r.customer_id||'-')+'</td><td>'+(r.staff_id||'-')+'</td><td>'+(r.loan_id||'-')+'</td><td>'+r.priority+'</td><td>'+r.status+'</td><td>'+(r.last_message_at||'-')+'</td><td><a class="btn btn-xs btn-info" href="{{ url('loan-management/live-chat') }}/'+r.id+'">Open</a></td></tr>');
            });
        });
    }
    $('#btnFilter').on('click', loadInbox);
    loadInbox();
    setInterval(loadInbox, {{ (int) config('loanmanagement.chat_polling_seconds', 5) * 1000 }});
})(jQuery);
</script>
@endsection
