@php
    $isEdit = isset($customerRow);
@endphp

@if(!$isEdit)
<div class="form-group">
    <a href="{{ route('loan-management.customers.clone-from-pos') }}" class="btn btn-info">Clone From Ultimate POS</a>
    <button type="button" class="btn btn-default" id="btn-new-customer">Create New Customer</button>
</div>
@endif

<ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">
    <li class="active"><a href="#tab-basic" role="tab" data-toggle="tab">Basic Info</a></li>
    <li><a href="#tab-address" role="tab" data-toggle="tab">Address</a></li>
    <li><a href="#tab-family" role="tab" data-toggle="tab">Family</a></li>
    <li><a href="#tab-work" role="tab" data-toggle="tab">Work</a></li>
    <li><a href="#tab-documents" role="tab" data-toggle="tab">Documents</a></li>
    <li><a href="#tab-app-login" role="tab" data-toggle="tab">App Login</a></li>
    <li><a href="#tab-gps" role="tab" data-toggle="tab">GPS Tracking</a></li>
    <li><a href="#tab-blacklist" role="tab" data-toggle="tab">Blacklist</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane active" id="tab-basic">@include('loanmanagement::customers.partials.basic_info')</div>
    <div class="tab-pane" id="tab-address">@include('loanmanagement::customers.partials.address')</div>
    <div class="tab-pane" id="tab-family">@include('loanmanagement::customers.partials.family')</div>
    <div class="tab-pane" id="tab-work">@include('loanmanagement::customers.partials.work')</div>
    <div class="tab-pane" id="tab-documents">@include('loanmanagement::customers.partials.documents')</div>
    <div class="tab-pane" id="tab-app-login">@include('loanmanagement::customers.partials.app_login')</div>
    <div class="tab-pane" id="tab-gps">@include('loanmanagement::customers.partials.gps_tracking')</div>
    <div class="tab-pane" id="tab-blacklist">@include('loanmanagement::customers.partials.blacklist')</div>
</div>

<script>
(function($){
    $('#btn-capture-gps').on('click', function(){
        if(!navigator.geolocation){ return; }
        navigator.geolocation.getCurrentPosition(function(pos){
            $('#latitude').val(pos.coords.latitude);
            $('#longitude').val(pos.coords.longitude);
        });
    });
})(jQuery);
</script>

