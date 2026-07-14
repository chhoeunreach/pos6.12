<div class="box box-info">
    <div class="box-header">
        <h3 class="box-title"><i class="fa fa-shopping-cart"></i> Product Items</h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-sm btn-primary" id="btnAddItem">
                <i class="fa fa-plus"></i> Add Item
            </button>
        </div>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-bordered lm-items-table" id="itemsTable">
            <thead>
                <tr>
                    <th style="width:25%;">Product Name</th>
                    <th style="width:15%;">SKU</th>
                    <th style="width:15%;">IMEI / Serial</th>
                    <th style="width:12%;">Photo</th>
                    <th style="width:10%;">Qty</th>
                    <th style="width:15%;">Unit Price</th>
                    <th style="width:12%;" class="text-right">Total</th>
                    <th style="width:8%;"></th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-right">Computed Principal:</th>
                    <th class="text-right" id="computedPrincipal">0.00</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
