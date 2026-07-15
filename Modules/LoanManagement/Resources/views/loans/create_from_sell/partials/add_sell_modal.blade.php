<div class="modal fade" id="addSellModal" tabindex="-1" role="dialog" aria-labelledby="addSellModalLabel">
    <div class="modal-dialog" role="document" style="width: 98%; max-width: 1500px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="addSellModalLabel">Create Ultimate POS Sale</h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <iframe
                    id="ultimatePosSellFrame"
                    title="Ultimate POS Add Sale"
                    src="about:blank"
                    style="width:100%; height:78vh; border:0; display:block;"
                ></iframe>
            </div>
            <div class="modal-footer">
                <span class="pull-left text-muted">After saving the sale, close this window to refresh the results.</span>
                <a href="{{ $posAddSellUrl }}" target="_blank" class="btn btn-default">
                    <i class="fa fa-external-link"></i> Open Full Page
                </a>
                <button type="button" class="btn btn-primary" id="btnRefreshSalesAfterPosSell">
                    <i class="fa fa-refresh"></i> Refresh Results
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
