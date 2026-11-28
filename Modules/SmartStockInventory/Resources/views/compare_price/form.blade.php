@extends('smartstockinventory::layouts.master')
@section('page_title', $isEdit ? 'Edit Compare Price' : 'New Compare Price')
@section('module_content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ $isEdit ? 'Edit Compare Price: ' . $compare->ref_no : 'New Compare Price' }}
        <small>Import or Enter SKU &amp; Qty only — Pull Real Purchase Prices</small>
    </h1>
</section>

<section class="content no-print">
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form id="compare_price_form"
          method="post"
          action="{{ $isEdit ? ssi_route('ssi.compare_price.update', ['id' => $compare->id]) : ssi_route('ssi.compare_price.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="submit_action" id="compare_submit_action" value="save">

        @component('components.widget', ['class' => 'box-primary', 'title' => 'Compare Sheet Info'])
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="compare_ref_no">Ref No</label>
                    <input type="text" id="compare_ref_no" name="ref_no" class="form-control" value="{{ old('ref_no', $compare->ref_no) }}" maxlength="60">
                </div>
                <div class="col-md-3 form-group">
                    <label for="compare_title">Title</label>
                    <input type="text" id="compare_title" name="title" class="form-control" value="{{ old('title', $compare->title) }}" maxlength="191" placeholder="e.g. Monthly Reorder Quote">
                </div>
                <div class="col-md-3 form-group">
                    <label for="compare_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="compare_date" name="compare_date" class="form-control" value="{{ old('compare_date', $compare->compare_date) }}" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="compare_location_id">Location Scope (Real Prices)</label>
                    <select id="compare_location_id" name="location_id" class="form-control select2" style="width:100%;">
                        <option value="">All Permitted Locations</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('location_id', $compare->location_id) === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 form-group" style="margin-bottom:0;">
                    <label for="compare_notes">Notes</label>
                    <input type="text" id="compare_notes" name="notes" class="form-control" value="{{ old('notes', $compare->notes) }}" maxlength="2000" placeholder="Optional note...">
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-info', 'title' => 'Fast Input: Import / Paste / Add SKU & Qty'])
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="compare_sku_search">Search / Scan SKU &amp; Qty</label>
                    <div class="input-group">
                        <select id="compare_sku_search" class="form-control" style="width:100%;" data-placeholder="Type SKU or Product Name..."></select>
                    </div>
                </div>
                <div class="col-md-2 form-group">
                    <label for="compare_quick_qty">Qty</label>
                    <div class="input-group">
                        <input type="number" id="compare_quick_qty" class="form-control" value="1" min="0" step="0.01">
                        <span class="input-group-btn">
                            <button type="button" id="compare_quick_add_btn" class="btn btn-primary">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add
                            </button>
                        </span>
                    </div>
                </div>
                <div class="col-md-6 form-group">
                    <label>Import Excel / CSV (`sku`, `qty` only)</label>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                        <input type="file" id="compare_import_file" accept=".xlsx,.xls,.csv,.txt" class="form-control" style="width:230px; height:auto;">
                        <button type="button" id="compare_import_btn" class="btn btn-success btn-sm">
                            <i class="fa fa-upload" aria-hidden="true"></i> Import File
                        </button>
                        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#compare_paste_modal">
                            <i class="fa fa-paste" aria-hidden="true"></i> Quick Paste (SKU + Qty)
                        </button>
                        <a href="{{ ssi_route('ssi.compare_price.template') }}" class="btn btn-default btn-sm">
                            <i class="fa fa-download" aria-hidden="true"></i> Template
                        </a>
                    </div>
                </div>
            </div>

            <div id="compare_missing_alert" class="alert alert-warning" style="display:none; margin-top:8px;"></div>

            <div class="table-responsive" style="margin-top:10px;">
                <table class="table table-bordered table-striped" id="compare_lines_table">
                    <thead>
                        <tr>
                            <th style="width:42px;">#</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Variant</th>
                            <th style="width:120px;" class="text-right">Qty</th>
                            <th class="text-right">តម្លៃដែលទិញចូលទាបជាងគេ</th>
                            <th class="text-right">តម្លៃមធ្យម</th>
                            <th class="text-right">តម្លៃខ្ពស់ជាងគេ</th>
                            <th class="text-right">តម្លៃទិញចូលក្រោយគេ</th>
                            <th style="width:70px;" class="text-center">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="compare_lines_tbody"></tbody>
                    <tfoot>
                        <tr class="bg-gray">
                            <th colspan="4" class="text-right">Total:</th>
                            <th class="text-right" id="compare_form_total_qty">0</th>
                            <th colspan="5"><small class="text-muted" id="compare_form_line_count">0 SKUs</small></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="text-right" style="margin-top:14px;">
                <a href="{{ ssi_route('ssi.compare_price.index') }}" class="btn btn-default">Cancel</a>
                <button type="submit" class="btn btn-primary" onclick="$('#compare_submit_action').val('save');">
                    <i class="fa fa-save" aria-hidden="true"></i> Save
                </button>
                @can('stock_inventory.export')
                    <button type="submit" class="btn btn-success" onclick="$('#compare_submit_action').val('save_and_export');">
                        <i class="fa fa-file-excel-o" aria-hidden="true"></i> Save &amp; Export Excel
                    </button>
                @endcan
            </div>
        @endcomponent
    </form>
</section>

<div class="modal fade" id="compare_paste_modal" tabindex="-1" role="dialog" aria-labelledby="comparePasteTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="comparePasteTitle">Quick Paste SKU &amp; Qty (from Excel)</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Copy 2 columns (`SKU` and `Qty`) from Excel and paste below (one row per line):</p>
                <textarea id="compare_paste_textarea" class="form-control" rows="10" placeholder="IPHONE-15&#9;5&#10;MACBOOK-M3&#9;2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="compare_paste_apply_btn">Load SKUs &amp; Fetch Prices</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('module_js')
<script>
$(function () {
    var importPreviewUrl = @json(ssi_route('ssi.compare_price.import_preview'));
    var searchSkuUrl = @json(ssi_route('ssi.compare_price.search_sku'));
    var canViewCosts = @json($canViewCosts);
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var lines = @json($enrichedLines->values()->all());

    function fmtMoney(value) {
        if (!canViewCosts || value === null || value === undefined || value === '') {
            return '<span class="text-muted">—</span>';
        }
        return __currency_trans_from_en(Number(value), true, false, __currency_precision, true);
    }

    function fmtQty(value) {
        return __currency_trans_from_en(Number(value || 0), false, false, __quantity_precision, true);
    }

    function escapeHtml(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function renderLines() {
        var $tbody = $('#compare_lines_tbody').empty();
        var totalQty = 0;

        if (!lines.length) {
            $tbody.append('<tr><td colspan="10" class="text-center text-muted">No SKUs added yet. Import an Excel/CSV file (`sku`, `qty`), quick-paste rows, or add SKUs above.</td></tr>');
        } else {
            lines.forEach(function (row, idx) {
                var qty = Number(row.qty || 0);
                totalQty += qty;
                var purchaseMeta = row.purchase_count > 0
                    ? '<br><small class="text-muted">' + row.purchase_count + ' purchase(s)' + (row.latest_ref_no ? ' · ' + escapeHtml(row.latest_ref_no) : '') + '</small>'
                    : '<br><small class="text-muted">No received purchase</small>';

                var tr = $('<tr>');
                tr.append('<td>' + (idx + 1) + '</td>');
                tr.append('<td><strong>' + escapeHtml(row.sku) + '</strong><input type="hidden" name="items[' + idx + '][sku]" value="' + escapeHtml(row.sku) + '"></td>');
                tr.append('<td>' + escapeHtml(row.product_name) + purchaseMeta + '</td>');
                tr.append('<td>' + (row.variant ? escapeHtml(row.variant) : '<span class="text-muted">—</span>') + '</td>');
                tr.append('<td class="text-right"><input type="number" step="0.01" min="0" class="form-control input-sm text-right js-line-qty" data-idx="' + idx + '" name="items[' + idx + '][qty]" value="' + qty + '"></td>');
                tr.append('<td class="text-right">' + fmtMoney(row.min_price) + '</td>');
                tr.append('<td class="text-right">' + fmtMoney(row.avg_price) + '</td>');
                tr.append('<td class="text-right">' + fmtMoney(row.max_price) + '</td>');
                tr.append('<td class="text-right"><strong>' + fmtMoney(row.latest_price) + '</strong></td>');
                tr.append('<td class="text-center"><button type="button" class="btn btn-xs btn-danger js-remove-line" data-idx="' + idx + '"><i class="fa fa-trash"></i></button></td>');
                $tbody.append(tr);
            });
        }

        $('#compare_form_total_qty').text(fmtQty(totalQty));
        $('#compare_form_line_count').text(lines.length + ' SKU(s)');
    }

    function showMissing(missing) {
        var $alert = $('#compare_missing_alert');
        if (!missing || !missing.length) {
            $alert.hide().empty();
            return;
        }
        var items = missing.map(function (m) {
            return 'Row ' + escapeHtml(m.row) + ': <strong>' + escapeHtml(m.sku) + '</strong>';
        }).join(', ');
        $alert.html('<strong>Unmatched SKUs ignored:</strong> ' + items).show();
    }

    function mergeMatched(matched) {
        (matched || []).forEach(function (incoming) {
            var existing = lines.find(function (l) { return Number(l.variation_id) === Number(incoming.variation_id); });
            if (existing) {
                existing.qty = Number(existing.qty || 0) + Number(incoming.qty || 0);
                existing.min_price = incoming.min_price;
                existing.avg_price = incoming.avg_price;
                existing.max_price = incoming.max_price;
                existing.latest_price = incoming.latest_price;
                existing.purchase_count = incoming.purchase_count;
                existing.latest_ref_no = incoming.latest_ref_no;
            } else {
                lines.push(incoming);
            }
        });
        renderLines();
    }

    function refreshAllPrices() {
        if (!lines.length) return;
        $.ajax({
            url: importPreviewUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                location_id: $('#compare_location_id').val(),
                items: lines.map(function (l) { return { sku: l.sku, qty: l.qty }; })
            }
        }).done(function (res) {
            lines = res.matched || [];
            renderLines();
        });
    }

    $('#compare_location_id').on('change', refreshAllPrices);

    $('#compare_sku_search').select2({
        width: '100%',
        minimumInputLength: 1,
        ajax: {
            url: searchSkuUrl,
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return data; }
        }
    });

    $('#compare_quick_add_btn').on('click', function () {
        var sku = $('#compare_sku_search').val();
        var qty = Number($('#compare_quick_qty').val() || 1);
        if (!sku) {
            toastr.warning('Please select an SKU first.');
            return;
        }
        $.ajax({
            url: importPreviewUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                location_id: $('#compare_location_id').val(),
                items: [{ sku: sku, qty: qty }]
            }
        }).done(function (res) {
            showMissing(res.missing);
            mergeMatched(res.matched);
            $('#compare_sku_search').val(null).trigger('change');
            $('#compare_quick_qty').val(1);
        }).fail(function () {
            toastr.error('Unable to lookup SKU price.');
        });
    });

    $('#compare_import_btn').on('click', function () {
        var fileInput = document.getElementById('compare_import_file');
        if (!fileInput.files || !fileInput.files.length) {
            toastr.warning('Please select an Excel or CSV file first.');
            return;
        }
        var formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('location_id', $('#compare_location_id').val() || '');
        $.ajax({
            url: importPreviewUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: formData,
            processData: false,
            contentType: false
        }).done(function (res) {
            showMissing(res.missing);
            mergeMatched(res.matched);
            $('#compare_import_file').val('');
            toastr.success('Loaded ' + (res.matched ? res.matched.length : 0) + ' SKU(s) with real purchase prices.');
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to parse import file.';
            toastr.error(msg);
        });
    });

    $('#compare_paste_apply_btn').on('click', function () {
        var text = $('#compare_paste_textarea').val() || '';
        if (!text.trim()) {
            toastr.warning('Please paste at least one row.');
            return;
        }
        $.ajax({
            url: importPreviewUrl,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                location_id: $('#compare_location_id').val(),
                paste_text: text
            }
        }).done(function (res) {
            showMissing(res.missing);
            mergeMatched(res.matched);
            $('#compare_paste_textarea').val('');
            $('#compare_paste_modal').modal('hide');
            toastr.success('Loaded ' + (res.matched ? res.matched.length : 0) + ' SKU(s).');
        }).fail(function () {
            toastr.error('Failed to process pasted rows.');
        });
    });

    $(document).on('input', '.js-line-qty', function () {
        var idx = Number($(this).data('idx'));
        if (lines[idx]) {
            lines[idx].qty = Number($(this).val() || 0);
        }
        var total = lines.reduce(function (s, l) { return s + Number(l.qty || 0); }, 0);
        $('#compare_form_total_qty').text(fmtQty(total));
    });

    $(document).on('click', '.js-remove-line', function () {
        var idx = Number($(this).data('idx'));
        lines.splice(idx, 1);
        renderLines();
    });

    $('#compare_price_form').on('submit', function (e) {
        if (!lines.length) {
            e.preventDefault();
            toastr.warning('Please add or import at least one SKU and Qty before saving.');
        }
    });

    renderLines();
});
</script>
@endsection
