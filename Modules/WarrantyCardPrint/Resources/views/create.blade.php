@extends('layouts.app')
@section('title', 'Print Warranty Card')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Print Warranty Card</h1>
</section>

<section class="content no-print warranty-card-workspace">
    <div class="row">
        <div class="col-md-5">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Card Information</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Product Code</label>
                        <input type="text" class="form-control js-card-input" id="manual_product_code" data-target="preview_product_code" placeholder="I15PMZA256SC">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control js-card-input" id="manual_serial" data-target="preview_serial" placeholder="016469756">
                    </div>
                    <div class="form-group">
                        <label>Serial / IMEI</label>
                        <input type="text" class="form-control js-card-input" id="manual_line_note" data-target="preview_line_note" placeholder="JHKHDFKSHFKS">
                    </div>
                    <div class="form-group">
                        <label>Warranty Text</label>
                        <textarea class="form-control js-card-input" id="manual_warranty_text" data-target="preview_warranty_text" rows="2">ម៉ាស៊ីនថ្ម​​ និងមិនធានាខុសចំពោះទំរង់ដើមនឹងមិនធានាការចូលទឹក</textarea>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" class="form-control" id="manual_start_date" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control" id="manual_end_date" value="{{ \Carbon\Carbon::now()->addYear()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Copies</label>
                        <input type="number" min="1" max="50" class="form-control" id="manual_copies" value="1">
                    </div>
                    <button type="button" class="btn btn-primary" id="print_warranty_card">
                        <i class="fa fa-print"></i> @lang('messages.print')
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Preview</h3>
                </div>
                <div class="box-body warranty-card-preview-wrap">
                    <div id="warranty_card_preview" class="manual-warranty-card">
                        <div class="manual-row-1 manual-field-1 text-left" id="preview_product_code"></div>
                        <div class="manual-row-1b manual-field-2 text-left" id="preview_serial"></div>
                        <div class="manual-row-2 manual-field-3 text-left" id="preview_line_note"></div>
                        <div class="manual-row-3 manual-field-4 text-left" id="preview_warranty_text"></div>
                        <div class="manual-row-4 manual-field-5" id="preview_start_date"></div>
                        <div class="manual-row-4 manual-field-6" id="preview_end_date"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="print_section" id="manual_warranty_print_section"></section>
@endsection

@section('css')
<style>
@font-face {
    font-family: 'NewTimes';
    src: url('{{ asset("fonts/english/NewTimes.ttf") }}') format('truetype');
}
@font-face {
    font-family: 'Khmer OS Battambang';
    src: url('{{ asset("fonts/khmer/Battambang-Regular.ttf") }}') format('truetype');
}
@page {
    size: 85.6mm 53.98mm;
    margin: 0;
}
.warranty-card-preview-wrap {
    overflow: auto;
}
.manual-warranty-card {
    position: relative;
    width: 85.6mm;
    height: 53.98mm;
    font-family: Arial, sans-serif;
    box-sizing: border-box;
    background: #fff;
    color: #000;
    page-break-after: always;
    break-after: page;
}
.manual-warranty-card:last-child {
    page-break-after: auto;
    break-after: auto;
}
.manual-row-1,
.manual-row-1b {
    position: absolute;
    width: 25mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-2 {
    position: absolute;
    width: 62mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-3 {
    position: absolute;
    width: 66mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-row-4 {
    position: absolute;
    width: 20mm;
    height: 5mm;
    line-height: 5mm;
    white-space: nowrap;
}
.manual-field-1 {
    left: 14mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-2 {
    right: 3mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-3 {
    right: 3mm;
    top: 15.5mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}
.manual-field-4 {
    right: 3mm;
    top: 22mm;
    font-family: 'Khmer OS Battambang', serif;
    font-weight: bold;
    font-size: 9px;
    padding-left: 2mm;
}
.manual-field-5 {
    right: 3mm;
    top: 28mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 0.7mm;
}
.manual-field-6 {
    right: 3mm;
    top: 33.5mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 0.7mm;
}
@media print {
    body {
        margin: 0;
    }
}
</style>
@endsection

@section('javascript')
<script>
$(function () {
    function formatDate(value) {
        if (!value) {
            return '';
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }
        return parts[2] + ' / ' + parts[1] + ' / ' + parts[0];
    }

    function updatePreview() {
        $('.js-card-input').each(function () {
            $('#' + $(this).data('target')).text($(this).val());
        });
        $('#preview_start_date').text(formatDate($('#manual_start_date').val()));
        $('#preview_end_date').text(formatDate($('#manual_end_date').val()));
    }

    function buildPrintCards() {
        var copies = parseInt($('#manual_copies').val(), 10);
        copies = isNaN(copies) || copies < 1 ? 1 : copies;
        copies = copies > 50 ? 50 : copies;

        var cardHtml = $('#warranty_card_preview').prop('outerHTML');
        var html = '';
        for (var i = 0; i < copies; i++) {
            html += cardHtml;
        }
        $('#manual_warranty_print_section').html(html);
    }

    $('.js-card-input, #manual_start_date, #manual_end_date').on('input change', updatePreview);

    $('#print_warranty_card').on('click', function () {
        updatePreview();
        buildPrintCards();
        window.print();
    });

    updatePreview();
});
</script>
@endsection
