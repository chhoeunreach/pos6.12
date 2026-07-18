@extends('loanmanagement::layouts.app')
@section('title', 'Create Loan')

@section('content_body')
<style>
    .lm-standalone .box { border-top: 0; box-shadow: 0 4px 16px rgba(15,23,42,0.06); margin-bottom: 16px; }
    .lm-standalone .box-header { padding: 12px 16px; }
    .lm-standalone .box-body { padding: 16px; }
    .lm-standalone .form-group { margin-bottom: 10px; }
    .lm-standalone .row > [class*='col-'] { padding-left: 8px; padding-right: 8px; }
    .lm-standalone-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .lm-standalone-actions .btn { margin: 0; }
    .lm-customer-search-wrap { position: relative; }
    .lm-customer-search-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
        background: #fff; border: 1px solid #d1d5db; border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto;
        display: none;
    }
    .lm-customer-search-results .lm-cs-item {
        padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
    }
    .lm-customer-search-results .lm-cs-item:hover { background: #f0f9ff; }
    .lm-customer-search-results .lm-cs-item .lm-cs-name { font-weight: 600; }
    .lm-customer-search-results .lm-cs-item .lm-cs-phone { color: #6b7280; font-size: 12px; }
    .lm-items-table th { background: #f8fafc; font-weight: 600; font-size: 13px; }
    .lm-items-table td { vertical-align: middle; }
    .lm-items-table input { font-size: 13px; }
    .lm-item-photo-control { display: flex; align-items: center; gap: 8px; min-width: 132px; }
    .lm-item-photo-thumb {
        width: 42px; height: 42px; border: 1px dashed #cbd5e1; border-radius: 6px;
        background: #f8fafc; display: flex; align-items: center; justify-content: center;
        color: #94a3b8; overflow: hidden;
    }
    .lm-item-photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lm-item-photo-status { display: block; margin-top: 4px; color: #64748b; font-size: 11px; line-height: 1.2; }
    .lm-summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .lm-summary-card { border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; padding: 12px 14px; }
    .lm-summary-card small { display: block; color: #64748b; font-weight: 600; margin-bottom: 4px; }
    .lm-summary-card strong { display: block; color: #0f172a; font-size: 18px; }
    .lm-recent-loans .box-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .lm-recent-loans-title { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
    .lm-recent-loans-subtitle { margin: 2px 0 0; color: #64748b; font-size: 12px; }
    .lm-recent-loans-table { margin-bottom: 0; }
    .lm-recent-loans-table th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
    .lm-recent-loans-table td { vertical-align: middle !important; }
    .lm-recent-loan-number { font-weight: 700; color: #2563eb; }
    .lm-recent-loan-customer { font-weight: 700; color: #0f172a; }
    .lm-recent-loan-meta { display: block; color: #94a3b8; font-size: 12px; margin-top: 2px; }
    .lm-recent-loan-status {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 74px; padding: 3px 8px; border-radius: 999px;
        background: #eef2ff; color: #2563eb; font-size: 11px; font-weight: 800; text-transform: uppercase;
    }
    .lm-recent-loan-status.active,
    .lm-recent-loan-status.approved,
    .lm-recent-loan-status.completed { background: #ecfdf5; color: #16a34a; }
    .lm-recent-loan-status.draft,
    .lm-recent-loan-status.pending { background: #fffbeb; color: #d97706; }
    .lm-recent-loan-status.rejected,
    .lm-recent-loan-status.cancelled,
    .lm-recent-loan-status.defaulted { background: #fef2f2; color: #dc2626; }
    .lm-recent-actions { white-space: nowrap; }
    .lm-doc-section { margin-top: 8px; }
    .lm-doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-top: 8px; }
    .lm-doc-thumb {
        position: relative; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;
        background: #f8fafc; aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    }
    .lm-doc-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lm-doc-thumb .lm-doc-icon { text-align: center; color: #64748b; }
    .lm-doc-thumb .lm-doc-icon i { font-size: 28px; display: block; margin-bottom: 4px; }
    .lm-doc-thumb .lm-doc-icon span { font-size: 9px; word-break: break-all; display: block; padding: 0 4px; }
    .lm-doc-thumb .lm-doc-remove {
        position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; border-radius: 50%;
        background: rgba(0,0,0,.55); color: #fff; border: none; font-size: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; line-height: 1;
    }
    .lm-doc-thumb .lm-doc-badge {
        position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,.6); color: #fff;
        font-size: 9px; padding: 1px 5px; border-radius: 4px;
    }
    .lm-doc-add {
        border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column;
        align-items: center; justify-content: center; cursor: pointer; color: #94a3b8;
        transition: all .15s; min-height: 100px; text-align: center; padding: 8px;
    }
    .lm-doc-add:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
    .lm-doc-add i { font-size: 22px; margin-bottom: 4px; }
    .lm-doc-add span { font-size: 10px; }
    .lm-doc-paste-hint {
        margin-top: 8px; padding: 8px 10px; background: #f0f9ff; border: 1px solid #bae6fd;
        border-radius: 6px; font-size: 11px; color: #0369a1; display: flex; align-items: center; gap: 6px;
    }
    .lm-id-crop-overlay {
        position: fixed; inset: 0; z-index: 1060; display: none; align-items: center; justify-content: center;
        background: rgba(15, 23, 42, 0.72); padding: 18px;
    }
    .lm-id-crop-box {
        width: min(860px, 96vw); max-height: 94vh; overflow: auto; background: #fff;
        border-radius: 8px; box-shadow: 0 24px 70px rgba(15, 23, 42, .28); padding: 16px;
    }
    .lm-id-crop-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .lm-id-crop-title { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
    .lm-id-crop-canvas { display: block; width: 100%; max-height: 68vh; border: 1px solid #dbe3ef; border-radius: 6px; touch-action: none; background: #f8fafc; }
    .lm-id-crop-status { min-height: 18px; margin-top: 8px; color: #64748b; font-size: 12px; }
    .lm-id-crop-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    @media (max-width: 767px) {
        .lm-summary-cards { grid-template-columns: repeat(2, 1fr); }
        .lm-standalone-actions { flex-direction: column; }
        .lm-standalone-actions .btn { width: 100%; justify-content: center; }
        .lm-standalone .box-body { padding: 12px; }
        .content-header h1 { font-size: 18px; }
        .content-header .pull-right { float: none !important; margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
        .lm-recent-loans .box-header { display: block; }
        .lm-recent-loans .box-header .btn { margin-top: 8px; }
    }
</style>

<section class="content-header no-print">
    <h1>Create Loan</h1>
    <div class="pull-right">
        <a href="{{ route('loan-management.loans.calculator') }}" class="btn btn-sm btn-default">
            <i class="fa fa-calculator"></i> Calculator
        </a>
    </div>
</section>

<section class="content lm-standalone no-print">
    <form id="standaloneLoanForm" method="POST" action="{{ route('loan-management.loans.store-standalone') }}">
        @csrf
        <input type="hidden" name="action_type" value="create_approve">

        @include('loanmanagement::loans.standalone.partials.customer_section')
        @include('loanmanagement::loans.standalone.partials.items_section')
        @include('loanmanagement::loans.standalone.partials.loan_terms', ['locations' => $locations, 'collectors' => $collectors, 'loanLocations' => $loanLocations])
        @include('loanmanagement::loans.standalone.partials.payment_section', ['paymentTypes' => $paymentTypes, 'defaultPaymentMethod' => $defaultPaymentMethod])
        @include('loanmanagement::loans.standalone.partials.schedule_preview')

        <div class="box box-solid">
            <div class="box-body">
                <div class="lm-standalone-actions">
                    <button type="button" class="btn btn-info" id="btnPreviewSchedule">
                        <i class="fa fa-table"></i> Preview Schedule
                    </button>
                    <button type="button" class="btn btn-primary" id="btnCreateLoan" data-action="create_approve">
                        <i class="fa fa-plus"></i> Create Loan
                    </button>
                    <a href="{{ route('loan-management.loans') }}" class="btn btn-danger">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="box box-solid lm-recent-loans">
        <div class="box-header with-border">
            <div>
                <h3 class="lm-recent-loans-title"><i class="fa fa-clock-o"></i> Recently Created Loans</h3>
                <p class="lm-recent-loans-subtitle">Latest loans for quick review after creating a new one.</p>
            </div>
            <a href="{{ route('loan-management.loans') }}" class="btn btn-default btn-sm">
                <i class="fa fa-list"></i> View All Loans
            </a>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-hover lm-recent-loans-table">
                <thead>
                    <tr>
                        <th>Loan</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-right">Principal</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLoans ?? collect() as $loan)
                        @php
                            $status = strtolower((string) ($loan->status ?? ''));
                            $statusClass = preg_replace('/[^a-z0-9_-]+/', '-', $status) ?: 'unknown';
                            $currency = $loan->currency ?? 'USD';
                            $loanDate = ($loan->loan_date ?? '') ?: (substr((string) ($loan->created_at ?? ''), 0, 10) ?: '-');
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('loan-management.loans.view', $loan->id) }}" class="lm-recent-loan-number">
                                    {{ $loan->loan_number ?? ('#'.$loan->id) }}
                                </a>
                                <span class="lm-recent-loan-meta">#{{ $loan->id }}</span>
                            </td>
                            <td>
                                <span class="lm-recent-loan-customer">{{ $loan->customer_name_snapshot ?? '-' }}</span>
                                <span class="lm-recent-loan-meta">{{ $loan->customer_phone_snapshot ?? '-' }}</span>
                            </td>
                            <td>{{ $loanDate }}</td>
                            <td class="text-right">{{ number_format((float) ($loan->principal_amount ?? 0), 2) }} {{ $currency }}</td>
                            <td class="text-right">{{ number_format((float) ($loan->balance_amount ?? 0), 2) }} {{ $currency }}</td>
                            <td><span class="lm-recent-loan-status {{ $statusClass }}">{{ $loan->status ?? '-' }}</span></td>
                            <td class="text-center lm-recent-actions">
                                <a href="{{ route('loan-management.loans.view', $loan->id) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-eye"></i> View
                                </a>
                                <a href="#" class="btn btn-xs btn-success btn-modal" data-href="{{ route('loan-management.loans.payment.quick-pay', $loan->id) }}" data-container=".view_modal">
                                    <i class="fa fa-money"></i> Pay
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No loans created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="lm-id-crop-overlay" id="lmIdCardCropOverlay" aria-hidden="true">
    <div class="lm-id-crop-box">
        <div class="lm-id-crop-head">
            <h3 class="lm-id-crop-title"><i class="fa fa-crop"></i> Crop ID Card Photo</h3>
            <button type="button" class="btn btn-default btn-sm" id="btnCancelIdCrop"><i class="fa fa-times"></i></button>
        </div>
        <canvas class="lm-id-crop-canvas" id="lmIdCardCropCanvas"></canvas>
        <div class="lm-id-crop-status" id="lmIdCardCropStatus">Drag the box or corners to keep only the ID card.</div>
        <div class="lm-id-crop-actions">
            <button type="button" class="btn btn-default" id="btnResetIdCrop"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" class="btn btn-default" id="btnUseOriginalIdPhoto"><i class="fa fa-image"></i> Use Original</button>
            <button type="button" class="btn btn-primary" id="btnUseCroppedIdPhoto"><i class="fa fa-check"></i> Use Cropped Photo</button>
        </div>
    </div>
</div>
@endsection

@section('loan_js')
<script>
(function($){
    var urls = {
        searchCustomers: "{{ route('loan-management.loans.ajax.search-customers') }}",
        scanIdCard: "{{ route('loan-management.loans.ajax.scan-id-card') }}",
        previewSchedule: "{{ route('loan-management.loans.preview-standalone-schedule') }}",
        storeLoan: "{{ route('loan-management.loans.store-standalone') }}",
        loanList: "{{ route('loan-management.loans') }}",
        productBySerial: "{{ route('loan-management.loans.ajax.product-by-serial') }}"
    };

    var serialLookupTimers = {};

    function lookupProductBySerial($row) {
        var serial = $row.find('.item-imei').val().trim();
        if (serial.length < 3) return;

        var existingName = $row.find('.item-name').val().trim();
        if (existingName) return;

        $.get(urls.productBySerial, { serial: serial }, function (res) {
            if (res.success && res.data && res.data.product_name) {
                var $nameField = $row.find('.item-name');
                if (!$nameField.val().trim()) {
                    $nameField.val(res.data.product_name);
                }
            }
        });
    }

    $(document).on('input', '.item-imei', function () {
        var $row = $(this).closest('tr');
        var serial = $(this).val().trim();
        var rowId = $row.index();

        if (serialLookupTimers[rowId]) {
            clearTimeout(serialLookupTimers[rowId]);
        }

        if (serial.length < 3) return;

        serialLookupTimers[rowId] = setTimeout(function () {
            lookupProductBySerial($row);
        }, 600);
    });

    var searchTimer = null;
    var idCardImageData = '';
    var idCardCropper = null;
    var idCardCropFile = null;
    var lmDocFiles = [];

    function lmGetFileIcon(name) {
        var ext = (name || '').split('.').pop().toLowerCase();
        var icons = { pdf: 'fa-file-pdf-o', txt: 'fa-file-text-o', csv: 'fa-file-text-o', doc: 'fa-file-word-o', docx: 'fa-file-word-o' };
        return icons[ext] || 'fa-file-o';
    }

    function lmIsImageFile(file) {
        return file && file.type && file.type.indexOf('image/') === 0;
    }

    function lmAddDocThumb(dataUri, fileName, fileSize, isText) {
        var grid = document.getElementById('lmDocGrid');
        var addBtn = grid.querySelector('.lm-doc-add');
        var thumb = document.createElement('div');
        thumb.className = 'lm-doc-thumb';
        var idx = lmDocFiles.length;
        lmDocFiles.push({ dataUri: dataUri, name: fileName || 'document', type: isText ? 'text' : 'file' });
        var sizeKb = fileSize ? Math.round(fileSize / 1024) : Math.round((dataUri.length * 3 / 4) / 1024);

        if (isText) {
            thumb.innerHTML = '<div class="lm-doc-icon"><i class="fa fa-file-text-o"></i><span>' + (fileName || 'text') + '</span></div>' +
                '<button type="button" class="lm-doc-remove" onclick="lmRemoveDoc(' + idx + ')"><i class="fa fa-times"></i></button>' +
                '<span class="lm-doc-badge">' + sizeKb + 'KB</span>';
        } else {
            thumb.innerHTML = '<img src="' + dataUri + '" alt="">' +
                '<button type="button" class="lm-doc-remove" onclick="lmRemoveDoc(' + idx + ')"><i class="fa fa-times"></i></button>' +
                '<span class="lm-doc-badge">' + sizeKb + 'KB</span>';
        }
        grid.insertBefore(thumb, addBtn);
    }

    function lmRemoveDoc(idx) {
        lmDocFiles[idx] = null;
        var grid = document.getElementById('lmDocGrid');
        var thumbs = grid.querySelectorAll('.lm-doc-thumb');
        if (thumbs[idx]) thumbs[idx].remove();
    }

    function lmCompressImageFile(file, maxW, maxH, quality) {
        return new Promise(function(resolve) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var w = img.width, h = img.height;
                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
                    var canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function lmReadTextFile(file) {
        return new Promise(function(resolve) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var text = e.target.result || '';
                var dataUri = 'data:text/plain;base64,' + btoa(unescape(encodeURIComponent(text)));
                resolve(dataUri);
            };
            reader.readAsText(file);
        });
    }

    function lmHandleDocFiles(files) {
        Array.from(files || []).forEach(function(file) {
            if (lmIsImageFile(file)) {
                lmCompressImageFile(file, 1200, 800, 0.65).then(function(dataUri) {
                    lmAddDocThumb(dataUri, file.name, file.size, false);
                });
            } else if (file.type === 'text/plain' || file.name.match(/\.(txt|csv|log)$/i)) {
                lmReadTextFile(file).then(function(dataUri) {
                    lmAddDocThumb(dataUri, file.name, file.size, true);
                });
            } else {
                var reader = new FileReader();
                reader.onload = function(e) {
                    lmAddDocThumb(e.target.result, file.name, file.size, false);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    document.getElementById('lmDocInput').addEventListener('change', function() {
        lmHandleDocFiles(this.files);
        this.value = '';
    });

    $(document).on('click', '#btnAddDocumentLink', function() {
        $('#lmDocumentLinks').append(
            '<div class="input-group" style="margin-bottom:6px;">' +
                '<input type="url" name="document_links[]" class="form-control" placeholder="Paste document link">' +
                '<span class="input-group-btn">' +
                    '<button type="button" class="btn btn-default btn-remove-document-link" title="Remove link"><i class="fa fa-times"></i></button>' +
                '</span>' +
            '</div>'
        );
    });

    $(document).on('click', '.btn-remove-document-link', function() {
        $(this).closest('.input-group').remove();
    });

    document.addEventListener('paste', function(e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        var handled = false;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image/') === 0) {
                var file = items[i].getAsFile();
                if (file) {
                    lmCompressImageFile(file, 1200, 800, 0.65).then(function(dataUri) {
                        lmAddDocThumb(dataUri, 'pasted-image-' + Date.now() + '.png', file.size, false);
                    });
                    handled = true;
                }
            }
        }
        if (handled) {
            e.preventDefault();
        }
    });

    function money(value) {
        var n = parseFloat(value || 0);
        return Number.isFinite(n) ? n.toFixed(2) : '0.00';
    }

    function parseNum(v) {
        var n = parseFloat(String(v || '').replace(/,/g, '').trim());
        return Number.isFinite(n) ? n : 0;
    }

    function recalcItemTotals() {
        var total = 0;
        $('#itemsTable tbody tr').each(function(){
            var qty = parseNum($(this).find('.item-qty').val());
            var price = parseNum($(this).find('.item-price').val());
            var lineTotal = Math.round(qty * price * 100) / 100;
            $(this).find('.item-total').text(money(lineTotal));
            total += lineTotal;
        });
        $('#computedPrincipal').text(money(total));
        $('#principal_amount_input').val(total > 0 ? total.toFixed(2) : '');
        recalcSummary();
    }

    function recalcSummary() {
        var totalAmount = parseNum($('#principal_amount_input').val());
        var downPayment = parseNum($('#payment_amount_input').val());
        var due = Math.max(0, totalAmount - downPayment);
        $('#summaryTotal').text(money(totalAmount));
        $('#summaryDownPayment').text(money(downPayment));
        $('#summaryDue').text(money(due));
        $('#down_payment_hidden').val(downPayment.toFixed(2));
    }

    function addItemRow() {
        var idx = $('#itemsTable tbody tr').length;
        var row = '<tr>' +
            '<td><input type="text" name="items['+idx+'][product_name]" class="form-control item-name" placeholder="Product name"></td>' +
            '<td><input type="text" name="items['+idx+'][sku]" class="form-control item-sku" placeholder="SKU"></td>' +
            '<td><input type="text" name="items['+idx+'][imei]" class="form-control item-imei" placeholder="IMEI/Serial"></td>' +
            '<td>' +
                '<div class="lm-item-photo-control">' +
                    '<label class="btn btn-default btn-xs" style="margin:0;">' +
                        '<i class="fa fa-camera"></i> Photo' +
                        '<input type="file" accept="image/*" capture="environment" class="item-photo-input" style="display:none;">' +
                    '</label>' +
                    '<span class="lm-item-photo-thumb"><i class="fa fa-image"></i></span>' +
                '</div>' +
                '<input type="hidden" name="items['+idx+'][product_photo]" class="item-photo-data">' +
                '<span class="lm-item-photo-status"></span>' +
            '</td>' +
            '<td><input type="number" name="items['+idx+'][qty]" class="form-control item-qty" min="1" value="1"></td>' +
            '<td><input type="number" name="items['+idx+'][unit_price]" class="form-control item-price" min="0" step="0.01" value="0"></td>' +
            '<td class="item-total text-right">0.00</td>' +
            '<td><button type="button" class="btn btn-xs btn-danger btn-remove-item"><i class="fa fa-trash"></i></button></td>' +
            '</tr>';
        $('#itemsTable tbody').append(row);
    }

    function setItemPhoto($row, dataUri) {
        $row.find('.item-photo-data').val(dataUri || '');
        var $thumb = $row.find('.lm-item-photo-thumb');
        if (dataUri) {
            $thumb.html('<img src="' + dataUri + '" alt="">');
            $row.find('.lm-item-photo-status').text('Photo ready');
        } else {
            $thumb.html('<i class="fa fa-image"></i>');
            $row.find('.lm-item-photo-status').text('');
        }
    }

    function compressImage(file, maxW, maxH, quality) {
        return new Promise(function(resolve) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var w = img.width, h = img.height;
                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
                    var canvas = document.createElement('canvas');
                    canvas.width = w;
                    canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function applyIdCardImage(dataUri) {
        idCardImageData = dataUri;
        $('#customer_id_card_photo_preview img').attr('src', dataUri);
        $('#customer_id_card_photo_preview').show();
        $('#customer_info_fields').show();
        scanIdCard(dataUri);
    }

    function setIdCropStatus(message, isError) {
        $('#lmIdCardCropStatus').text(message || '').css('color', isError ? '#dd4b39' : '#64748b');
    }

    function showIdCropOverlay() {
        $('#lmIdCardCropOverlay').css('display', 'flex').attr('aria-hidden', 'false');
    }

    function hideIdCropOverlay() {
        $('#lmIdCardCropOverlay').hide().attr('aria-hidden', 'true');
    }

    function cancelIdCrop() {
        idCardCropper = null;
        idCardCropFile = null;
        hideIdCropOverlay();
        $('#customer_id_card_photo_input').val('');
        $('#customer_id_card_camera_input').val('');
    }

    function startIdCardCrop(file) {
        idCardCropFile = file;
        idCardCropper = null;
        showIdCropOverlay();
        setIdCropStatus('Preparing photo for crop...');

        if (!window.FileReader) {
            useOriginalIdPhoto();
            return;
        }

        var reader = new FileReader();
        var image = new Image();
        reader.onload = function(event) {
            image.onload = function() {
                idCardCropper = createIdCardCropper(document.getElementById('lmIdCardCropCanvas'), image);
                setIdCropStatus('Drag the box or corners to keep only the ID card.');
            };
            image.onerror = function() {
                setIdCropStatus('This browser cannot preview this image. Using original photo.', true);
                useOriginalIdPhoto();
            };
            image.src = event.target.result;
        };
        reader.onerror = function() {
            setIdCropStatus('This browser cannot preview this image. Using original photo.', true);
            useOriginalIdPhoto();
        };
        reader.readAsDataURL(file);
    }

    function useOriginalIdPhoto() {
        if (!idCardCropFile) {
            cancelIdCrop();
            return;
        }

        var file = idCardCropFile;
        cancelIdCrop();
        setOcrStatus('Preparing ID card photo...');
        compressImage(file, 1600, 1000, 0.76).then(applyIdCardImage);
    }

    function useCroppedIdPhoto() {
        if (!idCardCropper) {
            useOriginalIdPhoto();
            return;
        }

        setIdCropStatus('Cropping photo...');
        idCardCropper.getDataUrl(function(dataUri) {
            cancelIdCrop();
            setOcrStatus('Preparing cropped ID card photo...');
            applyIdCardImage(dataUri);
        });
    }

    function createIdCardCropper(canvas, image) {
        var context = canvas.getContext('2d');
        var maxWidth = Math.min(820, image.width);
        var scale = maxWidth / image.width;
        var canvasWidth = Math.round(image.width * scale);
        var canvasHeight = Math.round(image.height * scale);
        var dragMode = null;
        var lastPoint = null;
        var handleSize = 16;
        var crop = {};

        canvas.width = canvasWidth;
        canvas.height = canvasHeight;

        function reset() {
            crop = {
                x: Math.round(canvasWidth * 0.05),
                y: Math.round(canvasHeight * 0.08),
                width: Math.round(canvasWidth * 0.90),
                height: Math.round(canvasHeight * 0.78)
            };
            draw();
        }

        function drawHandle(x, y) {
            context.fillStyle = '#2563eb';
            context.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
        }

        function draw() {
            context.clearRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, 0, 0, canvasWidth, canvasHeight);
            context.fillStyle = 'rgba(15, 23, 42, 0.45)';
            context.fillRect(0, 0, canvasWidth, canvasHeight);
            context.drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, crop.x, crop.y, crop.width, crop.height);
            context.strokeStyle = '#2563eb';
            context.lineWidth = 3;
            context.strokeRect(crop.x, crop.y, crop.width, crop.height);
            drawHandle(crop.x, crop.y);
            drawHandle(crop.x + crop.width, crop.y);
            drawHandle(crop.x, crop.y + crop.height);
            drawHandle(crop.x + crop.width, crop.y + crop.height);
        }

        function pointFromEvent(event) {
            var source = event.touches && event.touches.length ? event.touches[0] : event;
            var rect = canvas.getBoundingClientRect();
            return {
                x: (source.clientX - rect.left) * (canvas.width / rect.width),
                y: (source.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        function dragModeFor(point) {
            var handles = {
                nw: {x: crop.x, y: crop.y},
                ne: {x: crop.x + crop.width, y: crop.y},
                sw: {x: crop.x, y: crop.y + crop.height},
                se: {x: crop.x + crop.width, y: crop.y + crop.height}
            };
            for (var mode in handles) {
                if (Math.abs(point.x - handles[mode].x) <= handleSize && Math.abs(point.y - handles[mode].y) <= handleSize) {
                    return mode;
                }
            }
            return point.x >= crop.x && point.x <= crop.x + crop.width && point.y >= crop.y && point.y <= crop.y + crop.height ? 'move' : null;
        }

        function constrain() {
            var minSize = 50;
            crop.width = Math.max(minSize, crop.width);
            crop.height = Math.max(minSize, crop.height);
            crop.x = Math.max(0, Math.min(crop.x, canvasWidth - crop.width));
            crop.y = Math.max(0, Math.min(crop.y, canvasHeight - crop.height));
            if (crop.x + crop.width > canvasWidth) crop.width = canvasWidth - crop.x;
            if (crop.y + crop.height > canvasHeight) crop.height = canvasHeight - crop.y;
        }

        function resize(mode, dx, dy) {
            if (mode.indexOf('n') !== -1) { crop.y += dy; crop.height -= dy; }
            if (mode.indexOf('s') !== -1) crop.height += dy;
            if (mode.indexOf('w') !== -1) { crop.x += dx; crop.width -= dx; }
            if (mode.indexOf('e') !== -1) crop.width += dx;
        }

        function start(event) {
            var point = pointFromEvent(event);
            dragMode = dragModeFor(point);
            lastPoint = point;
            if (dragMode) event.preventDefault();
        }

        function move(event) {
            if (!dragMode) return;
            var point = pointFromEvent(event);
            var dx = point.x - lastPoint.x;
            var dy = point.y - lastPoint.y;
            if (dragMode === 'move') {
                crop.x += dx;
                crop.y += dy;
            } else {
                resize(dragMode, dx, dy);
            }
            constrain();
            lastPoint = point;
            draw();
            event.preventDefault();
        }

        function end() {
            dragMode = null;
            lastPoint = null;
        }

        canvas.onmousedown = start;
        canvas.onmousemove = move;
        canvas.onmouseup = end;
        canvas.onmouseleave = end;
        canvas.ontouchstart = start;
        canvas.ontouchmove = move;
        canvas.ontouchend = end;
        reset();

        return {
            reset: reset,
            getDataUrl: function(callback) {
                var cropWidth = Math.round(crop.width / scale);
                var cropHeight = Math.round(crop.height / scale);
                var outputScale = Math.min(1, 1800 / Math.max(cropWidth, cropHeight));
                var output = document.createElement('canvas');
                output.width = Math.max(1, Math.round(cropWidth * outputScale));
                output.height = Math.max(1, Math.round(cropHeight * outputScale));
                output.getContext('2d').drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, 0, 0, output.width, output.height);
                callback(output.toDataURL('image/jpeg', 0.9));
            }
        };
    }

    function setOcrStatus(message, isError) {
        $('#id_card_ocr_status').text(message || '').css('color', isError ? '#dd4b39' : '#64748b');
    }

    function fillIfEmpty(selector, value) {
        if (value && !String($(selector).val() || '').trim()) {
            $(selector).val(value).trigger('change');
        }
    }

    function applyIdCardFields(fields, rawText) {
        fields = fields || {};
        $('#id_card_ocr_raw_text_input').val(rawText || '');
        $('#id_card_ocr_number_input').val(fields.id_card_number || '');
        $('#id_card_ocr_khmer_name_input').val(fields.khmer_name || '');
        $('#id_card_ocr_english_name_input').val(fields.english_name || '');
        $('#id_card_ocr_address_input').val(fields.address || '');
        fillIfEmpty('#customer_id_card_input', fields.id_card_number);
        fillIfEmpty('#customer_khmer_name_input', fields.khmer_name);
        fillIfEmpty('#customer_english_name_input', fields.english_name);
        fillIfEmpty('#customer_address_input', fields.address);
        $('#customer_name_input').val($('#customer_khmer_name_input').val() || $('#customer_english_name_input').val() || '');
    }

    function scanIdCard(dataUri) {
        setOcrStatus('Reading ID card...');
        $.ajax({
            url: urls.scanIdCard,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                id_card_image: dataUri
            },
            success: function(res) {
                if (res && res.success) {
                    var data = res.data || {};
                    applyIdCardFields(data.fields || {}, data.raw_text || '');
                    setOcrStatus(Object.keys(data.fields || {}).length ? 'ID card text filled automatically.' : 'OCR finished, but no matching fields were found.');
                } else {
                    setOcrStatus((res && res.message) || 'OCR unavailable.', true);
                }
            },
            error: function(xhr) {
                setOcrStatus(xhr.responseJSON?.message || 'OCR failed.', true);
            }
        });
    }

    $(document).on('click', '#btnAddItem', function(){ addItemRow(); });
    $(document).on('click', '.btn-remove-item', function(){
        $(this).closest('tr').remove();
        recalcItemTotals();
    });
    $(document).on('input change', '.item-qty, .item-price', function(){ recalcItemTotals(); });
    $(document).on('change', '.item-photo-input', function(){
        var input = this;
        var file = input.files && input.files[0];
        var $row = $(input).closest('tr');
        if (!file) return;

        $row.find('.lm-item-photo-status').text('Preparing photo...');
        compressImage(file, 1200, 900, 0.75).then(function(dataUri) {
            setItemPhoto($row, dataUri);
            input.value = '';
        });
    });

    $('#payment_amount_input, #principal_amount_input').on('input change', recalcSummary);

    $('#customerSearchInput').on('input', function(){
        var q = $(this).val().trim();
        clearTimeout(searchTimer);
        if (q.length < 2) {
            $('.lm-customer-search-results').hide();
            return;
        }
        searchTimer = setTimeout(function(){
            $.get(urls.searchCustomers, {q: q}, function(res){
                var $box = $('.lm-customer-search-results');
                $box.empty();
                if (res.data && res.data.length) {
                    res.data.forEach(function(c){
                        var primaryName = (c.khmer_name || c.name || '');
                        var secondaryName = (c.name && c.name !== primaryName) ? c.name : '';
                        $box.append(
                            '<div class="lm-cs-item" data-id="'+c.id+'" data-name="'+(c.name||'')+'" data-khmer-name="'+(c.khmer_name||'')+'" data-phone="'+(c.phone||'')+'" data-alternate-phone="'+(c.alternate_phone||'')+'" data-address="'+(c.address||'')+'" data-idcard="'+(c.id_card_number||'')+'">' +
                            '<div class="lm-cs-name">'+primaryName+'</div>' +
                            (secondaryName ? '<div class="lm-cs-phone">'+secondaryName+'</div>' : '') +
                            '<div class="lm-cs-phone">'+(c.phone||'')+' '+(c.customer_code||'')+'</div>' +
                            '</div>'
                        );
                    });
                    $box.show();
                } else {
                    $box.hide();
                }
            });
        }, 300);
    });

    $(document).on('click', '.lm-cs-item', function(){
        var $item = $(this);
        $('#customer_id_input').val($item.data('id'));
        $('#customer_name_input').val($item.data('khmer-name') || $item.data('name'));
        $('#customer_english_name_input').val($item.data('name'));
        $('#customer_khmer_name_input').val($item.data('khmer-name'));
        $('#customer_phone_input').val($item.data('phone'));
        $('#alternate_phone_input').val($item.data('alternate-phone'));
        $('#alternate_phone_group').toggle(!!String($item.data('alternate-phone') || '').trim());
        $('#customer_address_input').val($item.data('address'));
        $('#customer_id_card_input').val($item.data('idcard'));
        $('#customer_info_fields').show();
        $('.lm-customer-search-results').hide();
        $('#customerSearchInput').val('');
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('.lm-customer-search-wrap').length) {
            $('.lm-customer-search-results').hide();
        }
    });

    $('#btnClearCustomer').on('click', function(){
        $('#customer_id_input').val('');
        $('#customer_name_input').val('');
        $('#customer_english_name_input').val('');
        $('#customer_khmer_name_input').val('');
        $('#customer_phone_input').val('');
        $('#alternate_phone_input').val('');
        $('#alternate_phone_group').hide();
        $('#customer_address_input').val('');
        $('#customer_id_card_input').val('');
    });

    $('#btnShowAlternatePhone').on('click', function(){
        $('#alternate_phone_group').show();
        $('#alternate_phone_input').focus();
    });

    $('#customer_id_card_photo_input, #customer_id_card_camera_input').on('change', function(){
        var file = this.files && this.files[0];
        if (!file) return;
        startIdCardCrop(file);
    });

    $('#btnCancelIdCrop').on('click', cancelIdCrop);
    $('#btnUseOriginalIdPhoto').on('click', useOriginalIdPhoto);
    $('#btnUseCroppedIdPhoto').on('click', useCroppedIdPhoto);
    $('#btnResetIdCrop').on('click', function(){
        if (idCardCropper) {
            idCardCropper.reset();
            setIdCropStatus('Crop reset. Drag the box or corners to adjust.');
        }
    });

    $('#btnPreviewSchedule').on('click', function(){
        var form = $('#standaloneLoanForm');
        $('#customer_name_input').val($('#customer_khmer_name_input').val() || $('#customer_english_name_input').val() || '');
        $.post(urls.previewSchedule, form.serialize(), function(res){
            var rows = res.data || [];
            var $tb = $('#schedulePreviewTable tbody');
            var $table = $tb.closest('table');
            var totalP = 0, totalI = 0, totalA = 0, totalB = 0;
            $tb.empty();
            rows.forEach(function(r){
                totalP += Number(r.principal || 0);
                totalI += Number(r.interest || 0);
                totalA += Number(r.total || 0);
                totalB += Number(r.balance || 0);
                $tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td class="text-right">'+money(r.principal)+'</td><td class="text-right">'+money(r.interest)+'</td><td class="text-right">'+money(r.total)+'</td><td class="text-right">'+money(r.balance)+'</td></tr>');
            });
            $table.find('tfoot th').eq(1).text(totalP.toFixed(2));
            $table.find('tfoot th').eq(2).text(totalI.toFixed(2));
            $table.find('tfoot th').eq(3).text(totalA.toFixed(2));
            $table.find('tfoot th').eq(4).text(totalB.toFixed(2));
        }).fail(function(xhr){
            alert(xhr.responseJSON?.message || 'Failed to preview schedule');
        });
    });

    $('#btnCreateLoan').on('click', function(){
        $('#standaloneLoanForm').find('input[name="action_type"]').val($(this).data('action'));
        $('#standaloneLoanForm').trigger('submit');
    });

    $('#standaloneLoanForm').on('submit', function(e){
        e.preventDefault();
        var $form = $(this);
        var $buttons = $('#btnCreateLoan');
        $('#customer_name_input').val($('#customer_khmer_name_input').val() || $('#customer_english_name_input').val() || '');
        if (this.checkValidity && ! this.checkValidity()) {
            this.reportValidity();
            return;
        }
        var fd = new FormData(this);
        if (idCardImageData) {
            fd.append('id_card_image', idCardImageData);
        }
        lmDocFiles.forEach(function(d, i) { if (d) fd.append('documents[]', d.dataUri); });
        $buttons.prop('disabled', true);
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res){
                if (window.toastr) {
                    toastr.success(res.message || 'Loan created successfully');
                } else {
                    alert(res.message || 'Loan created successfully');
                }
                if (res?.data?.loan_id) {
                    window.location.href = urls.loanList + '/' + res.data.loan_id + '/view';
                }
            },
            error: function(xhr){
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    var errors = xhr.responseJSON.errors;
                    var firstKey = Object.keys(errors)[0];
                    alert(errors[firstKey][0] || xhr.responseJSON?.message || 'Validation failed');
                } else {
                    alert(xhr.responseJSON?.message || 'Failed to create loan');
                }
            },
            complete: function(){ $buttons.prop('disabled', false); }
        });
    });

    if ($.fn.select2) {
        $('.select2').select2();
    }

    addItemRow();
})(jQuery);
</script>
@endsection
