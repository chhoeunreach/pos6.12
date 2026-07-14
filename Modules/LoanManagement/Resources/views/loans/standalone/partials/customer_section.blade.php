<div class="box box-primary">
    <div class="box-header"><h3 class="box-title"><i class="fa fa-user"></i> Customer Information</h3></div>
    <div class="box-body row">
        <div class="col-sm-12 col-md-6">
            <div class="form-group">
                <label>Search Existing Customer</label>
                <div class="lm-customer-search-wrap">
                    <input type="text" id="customerSearchInput" class="form-control" placeholder="Type name or phone to search...">
                    <div class="lm-customer-search-results"></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-md-6">
            <div class="form-group" style="padding-top: 24px;">
                <button type="button" class="btn btn-default btn-sm" id="btnClearCustomer">
                    <i class="fa fa-times"></i> Clear Selected Customer
                </button>
            </div>
        </div>
        <input type="hidden" name="customer_id" id="customer_id_input" value="">
        <div class="col-sm-12 col-md-6">
            <div class="form-group">
                <label>ID Card Photo</label>
                <div class="btn-group" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <label class="btn btn-default btn-sm" for="customer_id_card_camera_input" style="margin-bottom:0;">
                        <i class="fa fa-camera"></i> Take Photo
                    </label>
                    <label class="btn btn-default btn-sm" for="customer_id_card_photo_input" style="margin-bottom:0;">
                        <i class="fa fa-image"></i> Upload
                    </label>
                </div>
                <input type="file" id="customer_id_card_camera_input" accept="image/*" capture="environment" style="display:none;">
                <input type="file" id="customer_id_card_photo_input" accept="image/*" style="display:none;">
                <input type="hidden" name="id_card_ocr_raw_text" id="id_card_ocr_raw_text_input">
                <input type="hidden" name="id_card_ocr_fields[id_card_number]" id="id_card_ocr_number_input">
                <input type="hidden" name="id_card_ocr_fields[khmer_name]" id="id_card_ocr_khmer_name_input">
                <input type="hidden" name="id_card_ocr_fields[english_name]" id="id_card_ocr_english_name_input">
                <input type="hidden" name="id_card_ocr_fields[address]" id="id_card_ocr_address_input">
                <div id="customer_id_card_photo_preview" style="display:none; margin-top:8px;">
                    <img src="" alt="ID Card" style="max-width:100%; max-height:180px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <p id="id_card_ocr_status" class="help-block" style="margin-bottom:0;"></p>
            </div>
        </div>
        <div id="customer_info_fields" style="display:none;">
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Customer Group</label>
                <input name="customer_group_name" class="form-control" value="រំលស់">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Name in Khmer</label>
                <input type="text" name="customer_khmer_name" id="customer_khmer_name_input" class="form-control" placeholder="Khmer name">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Name in English <span class="text-danger">*</span></label>
                <input type="text" name="customer_name" id="customer_name_input" class="form-control" required placeholder="English name">
                <input type="hidden" name="customer_english_name" id="customer_english_name_input">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>Phone</label>
                <div class="input-group">
                    <input type="text" name="customer_phone" id="customer_phone_input" class="form-control" placeholder="Phone number">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="btnShowAlternatePhone" title="Add alternate phone">
                            <i class="fa fa-plus"></i>
                        </button>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" id="alternate_phone_group" style="display:none;">
            <div class="form-group">
                <label>Alternate Phone</label>
                <input type="text" name="alternate_phone" id="alternate_phone_input" class="form-control" placeholder="Alternate phone">
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="display:none;">
            <div class="form-group">
                <label>ID Card Address</label>
                <input type="hidden" name="customer_address" id="customer_address_input" class="form-control" placeholder="ID card address">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="form-group">
                <label>ID Card Number</label>
                <input type="text" name="id_card_number" id="customer_id_card_input" class="form-control" placeholder="ID Card">
            </div>
        </div>
        </div>
    </div>
</div>
