<div class="box box-solid">
    <div class="box-header"><h3 class="box-title">Customer Snapshot</h3></div>
    <div class="box-body row">
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Customer Group Name</label><input name="customer_group_name" class="form-control" value="{{ old('customer_group_name', 'រំលស់') }}"></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Name</label><input class="form-control" value="{{ $sell['transaction']->customer_name }}" readonly></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Phone</label><input class="form-control" value="{{ $sell['transaction']->customer_phone }}" readonly></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Address</label><input class="form-control" value="{{ $sell['transaction']->customer_address }}" readonly></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>ID Card Number</label><input name="id_card_number" class="form-control"></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Profile</label><input type="file" class="form-control"></div></div>
        <div class="col-sm-6 col-md-4"><div class="form-group"><label>Document</label><input type="file" class="form-control" multiple></div></div>
    </div>
</div>
