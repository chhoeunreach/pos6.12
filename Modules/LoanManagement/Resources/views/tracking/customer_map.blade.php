@extends('layouts.app')
@section('title', 'Customer Tracking Map')

@section('content')
<section class="content-header">
    <h1>Customer Realtime Tracking</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <div id="map" style="width:100%;height:480px;background:#f5f5f5;"></div>
            <hr>
            <div class="table-responsive">
                <table class="table table-bordered" id="tracking-table">
                    <thead>
                    <tr>
                        <th>Customer</th><th>Phone</th><th>Loan</th><th>Balance</th><th>Speed</th><th>Battery</th><th>Last GPS</th><th>Map</th>
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
<script>
(function($){
    var map, markers = [];
    function initMap(){
        if(window.google && google.maps){
            map = new google.maps.Map(document.getElementById('map'), {zoom:7, center:{lat:11.5564, lng:104.9282}});
        }
    }
    function loadData(){
        $.get("{{ route('loan-management.customer-tracking.data') }}", function(resp){
            var rows = (resp && resp.data) ? resp.data : [];
            var tb = $('#tracking-table tbody'); tb.html('');
            markers.forEach(function(m){ m.setMap(null); }); markers = [];
            rows.forEach(function(r){
                var link = 'https://maps.google.com/?q='+r.latitude+','+r.longitude;
                tb.append('<tr><td>'+r.customer_name+'</td><td>'+(r.phone||'-')+'</td><td>'+(r.loan_number||'-')+'</td><td>'+(r.balance_amount||0)+'</td><td>'+(r.speed||0)+'</td><td>'+(r.battery_level||'-')+'</td><td>'+(r.recorded_at||'-')+'</td><td><a class="btn btn-xs btn-default" target="_blank" href="'+link+'">Open</a></td></tr>');
                if(map){ markers.push(new google.maps.Marker({position:{lat:parseFloat(r.latitude), lng:parseFloat(r.longitude)}, map:map, title:r.customer_name})); }
            });
        });
    }
    initMap(); loadData(); setInterval(loadData, 15000);
})(jQuery);
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key="></script>
@endsection
