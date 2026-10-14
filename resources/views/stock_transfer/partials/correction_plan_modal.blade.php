<div class="modal fade" id="stock_correction_plan_modal" tabindex="-1" role="dialog" aria-labelledby="stock_correction_plan_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 id="stock_correction_plan_title" class="modal-title">Stock Correction Plan</h4>
            </div>
            <div class="modal-body">
                <p id="stock_correction_plan_status" role="status"></p>
                <p id="stock_correction_plan_error" class="text-danger" role="alert"></p>
                <ul id="stock_correction_plan_blockers"></ul>
                <div class="table-responsive">
                    <table class="table table-bordered" id="stock_correction_plan_repairs">
                        <thead><tr><th>Batch</th><th>Serial</th><th>Recorded Consumed</th><th>Verified Consumed</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <ol id="stock_correction_plan_steps"></ol>
                <label id="stock_correction_confirmation"><input type="checkbox" id="confirm_stock_correction"> I approve the listed counter changes. No serials, dates or movements will be changed.</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="open_manual_stock_correction"><i class="fa fa-edit" aria-hidden="true"></i> Correct Verified Serials</button>
                <button type="button" class="btn btn-default" id="download_stock_correction_plan" title="Download correction plan"><i class="fa fa-download" aria-hidden="true"></i> Download Plan</button>
                <button type="button" class="btn btn-primary" id="apply_stock_correction_plan" disabled><i class="fa fa-check" aria-hidden="true"></i> Apply Verified Counter Repairs</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@include('stock_transfer.partials.manual_correction_modal')
