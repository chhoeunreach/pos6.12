<div class="modal fade" id="stock_error_modal" tabindex="-1" role="dialog" aria-labelledby="stock_error_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="stock_error_title">Stock Reconciliation Required</h4>
            </div>
            <div class="modal-body">
                <p id="stock_error_reason" class="text-danger"></p>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody id="stock_error_details"></tbody>
                    </table>
                </div>
                <h5>Related Allocations</h5>
                <ul id="stock_error_allocations"></ul>
                <div id="stock_error_links" class="clearfix"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
