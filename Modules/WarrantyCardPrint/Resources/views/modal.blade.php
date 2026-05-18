<div class="modal-dialog modal-lg warranty-card-modal" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">Print Warranty Card</h4>
        </div>

        <div class="modal-body no-print warranty-card-workspace">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Product Code</label>
                        <input type="text" class="form-control js-card-input" id="manual_product_code" data-target="preview_product_code" placeholder="I15PMZA256SC">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control js-card-input" id="manual_serial" data-target="preview_serial" placeholder="456546445646">
                    </div>
                    <div class="form-group">
                        <label>Serial / IMEI</label>
                        <input type="text" class="form-control js-card-input" id="manual_line_note" data-target="preview_line_note" placeholder="JHKHDFKSHFKS">
                    </div>
                    <div class="form-group">
                        <label>Warranty Text</label>
                        <textarea class="form-control js-card-input" id="manual_warranty_text" data-target="preview_warranty_text" rows="2">ម៉ាស៊ីនថ្មី និងមិនធានាខុសបច្ចេក:ទំនិញមិនធានាការចូលទឹក</textarea>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" class="form-control manual-start-date" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control manual-end-date" value="{{ \Carbon\Carbon::now()->addYear()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Copies</label>
                        <input type="number" min="1" max="50" class="form-control manual-copies" value="1">
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="warranty-card-preview-wrap">
                        <div class="manual-warranty-card warranty-card-preview">
                            <div class="manual-row-1 manual-field-1 text-left" id="preview_product_code"></div>
                            <div class="manual-row-1b manual-field-2 text-left" id="preview_serial"></div>
                            <div class="manual-row-2 manual-field-3 text-left" id="preview_line_note"></div>
                            <div class="manual-row-3 manual-field-4 text-left" id="preview_warranty_text"></div>
                            <div class="manual-row-4 manual-field-5 preview-start-date"></div>
                            <div class="manual-row-4 manual-field-6 preview-end-date"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer no-print">
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            <button type="button" class="btn btn-primary print-warranty-card">
                <i class="fa fa-print"></i> @lang('messages.print')
            </button>
        </div>
    </div>
</div>
