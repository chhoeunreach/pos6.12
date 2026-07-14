@php
    $currentPhotoUrl = $customerPhotoUrl ?? null;
    $photoFileId = old('customer_photo_file_id', $customerRow->customer_photo_file_id ?? '');
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Profile Photo</label>
            <div class="lm-customer-photo-uploader">
                <div class="lm-customer-photo-preview" id="customer_photo_preview">
                    @if($currentPhotoUrl)
                        <img src="{{ $currentPhotoUrl }}" alt="Customer profile photo">
                    @else
                        <i class="fa fa-user"></i>
                    @endif
                </div>
                <div class="lm-customer-photo-actions">
                    <input type="hidden" name="customer_photo_file_id" value="{{ $photoFileId }}">
                    <input type="file" class="form-control" id="customer_photo_input" name="customer_photo" accept="image/jpeg,image/png">
                    <p class="help-block">Upload JPG or PNG, max 5 MB.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4"><div class="form-group"><label>ID Front File ID</label><input class="form-control" name="id_front_file_id" value="{{ old('id_front_file_id', $customerRow->id_front_file_id ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>ID Back File ID</label><input class="form-control" name="id_back_file_id" value="{{ old('id_back_file_id', $customerRow->id_back_file_id ?? '') }}"></div></div>
</div>

<script>
(function(){
    var input = document.getElementById('customer_photo_input');
    var preview = document.getElementById('customer_photo_preview');
    if (!input || !preview) return;
    input.addEventListener('change', function(){
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(event) {
            preview.innerHTML = '<img src="' + event.target.result + '" alt="Customer profile photo preview">';
        };
        reader.readAsDataURL(file);
    });
})();
</script>
