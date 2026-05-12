@extends('layouts.app')
@section('title', 'Clone From Ultimate POS')

@section('content')
<section class="content-header"><h1>Clone Customer From Ultimate POS</h1></section>
<section class="content">
    <div class="box box-primary"><div class="box-body">
        <div class="form-group">
            <label>Search by name / mobile / contact code / email</label>
            <input id="search" class="form-control" placeholder="Type keyword...">
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="tb"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Code</th><th>Address</th><th>Action</th></tr></thead><tbody></tbody></table>
        </div>
    </div></div>
</section>
<form id="cloneForm" method="POST" action="{{ route('loan-management.customers.clone-from-pos.store') }}" style="display:none;">@csrf<input type="hidden" name="main_contact_id" id="main_contact_id"></form>
@endsection

@section('javascript')
<script>
(function($){
    var timer = null;
    $('#search').on('keyup', function(){
        clearTimeout(timer);
        var q = $(this).val();
        timer = setTimeout(function(){
            $.get("{{ route('loan-management.customers.search-main-contacts') }}", {q:q}, function(resp){
                var rows = resp.data || [];
                var tb = $('#tb tbody'); tb.html('');
                rows.forEach(function(r){
                    var address = [r.address_line_1,r.address_line_2,r.city,r.state,r.country,r.zip_code].filter(Boolean).join(' ');
                    tb.append('<tr><td>'+ (r.name||'-') +'</td><td>'+ (r.mobile||'-') +'</td><td>'+ (r.email||'-') +'</td><td>'+ (r.customer_code||'-') +'</td><td>'+ (address||'-') +'</td><td><button class="btn btn-xs btn-primary btn-clone" data-id="'+r.id+'">Clone</button></td></tr>');
                });
            });
        }, 300);
    });
    $(document).on('click','.btn-clone', function(e){
        e.preventDefault();
        $('#main_contact_id').val($(this).data('id'));
        $('#cloneForm').submit();
    });
})(jQuery);
</script>
@endsection

