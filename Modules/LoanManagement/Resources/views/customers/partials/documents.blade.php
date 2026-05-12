<div class="row">
    <div class="col-md-4"><div class="form-group"><label>Customer Photo File ID</label><input class="form-control" name="customer_photo_file_id" value="{{ old('customer_photo_file_id', $customerRow->customer_photo_file_id ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>ID Front File ID</label><input class="form-control" name="id_front_file_id" value="{{ old('id_front_file_id', $customerRow->id_front_file_id ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>ID Back File ID</label><input class="form-control" name="id_back_file_id" value="{{ old('id_back_file_id', $customerRow->id_back_file_id ?? '') }}"></div></div>
</div>
