@extends('loanmanagement::layouts.app')
@section('title', 'Installment Calculator')

@section('content_body')
<style>
    .lm-calculator .box { border-top: 0; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08); }
    .lm-calculator .box-header { padding: 14px 16px; }
    .lm-calculator .box-body { padding: 16px; }
    .lm-calculator .form-group { margin-bottom: 12px; }
    .lm-calculator-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .lm-calculator-summary-card {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fff;
        padding: 12px 14px;
    }
    .lm-calculator-summary-card small {
        display: block;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 5px;
    }
    .lm-calculator-summary-card strong {
        display: block;
        color: #0f172a;
        font-size: 20px;
        line-height: 1.2;
    }
    .lm-calculator .table > thead > tr > th,
    .lm-calculator .table > tbody > tr > td,
    .lm-calculator .table > tfoot > tr > th {
        vertical-align: middle;
    }
    .lm-calculator-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 4px;
    }
    .lm-print-preview {
        background: #fff;
        color: #000;
        font-family: Arial, sans-serif;
    }
    .lm-print-page {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto;
        padding: 10mm 14mm 8mm;
        background: #fff;
        color: #000;
        font-size: 11px;
        line-height: 1.25;
    }
    .lm-print-brand {
        color: #ff8a00;
        font-size: 22px;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }
    .lm-print-tagline {
        margin-top: 2mm;
        padding-bottom: 1mm;
        border-bottom: 1.5px solid #ff8a00;
        text-align: center;
    }
    .lm-print-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 7mm;
        margin: 3mm 0 2mm;
    }
    .lm-print-info-table {
        width: 100%;
        border-collapse: collapse;
    }
    .lm-print-info-table td {
        padding: 1.5mm 0;
        border: 0;
        vertical-align: middle;
    }
    .lm-print-info-table .label {
        width: 47%;
        color: #7f9bb1;
        font-weight: 700;
    }
    .lm-print-info-table .value {
        font-weight: 700;
    }
    .lm-print-product-title {
        border: 1px solid #222;
        border-bottom: 0;
        padding: 1.5mm;
        text-align: center;
        font-weight: 700;
    }
    .lm-print-date-bar {
        float: right;
        min-width: 45mm;
        border-left: 1px solid #222;
        padding-left: 8mm;
    }
    .lm-print-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .lm-print-table th,
    .lm-print-table td {
        border: 1px solid #222;
        padding: 1.15mm 1.2mm;
        text-align: center;
        vertical-align: middle;
        font-size: 10px;
        line-height: 1.2;
    }
    .lm-print-table th {
        background: #f6f6f6;
        font-weight: 700;
    }
    .lm-print-table .money-cell,
    .lm-print-table .text-right {
        text-align: right;
        white-space: nowrap;
    }
    .lm-print-summary-cell {
        padding: 0 !important;
        text-align: left !important;
    }
    .lm-print-summary-inner {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .lm-print-summary-inner td {
        border: 0;
        padding: 0;
        vertical-align: top;
    }
    .lm-print-summary-terms-cell {
        width: 67%;
        border-right: 1px solid #222 !important;
        padding: 2mm 2.5mm !important;
    }
    .lm-print-summary-totals-cell {
        width: 33%;
    }
    .lm-print-summary-terms,
    .lm-print-summary-totals {
        width: 100%;
        border-collapse: collapse;
    }
    .lm-print-summary-terms td,
    .lm-print-summary-totals td {
        border: 0;
        padding: 1mm 1.5mm;
        font-weight: 700;
    }
    .lm-print-summary-totals td {
        border-bottom: 1px solid #222;
    }
    .lm-print-summary-totals tr:last-child td {
        border-bottom: 0;
    }
    .lm-print-red { color: red; }
    .lm-print-orange { color: #ff8a00; }
    .lm-print-muted { color: #666; }
    #loanCalculatorPrint {
        display: none;
    }
    @media print {
        @page { size: A4; margin: 0; }
        body * { visibility: hidden !important; }
        #loanCalculatorPrint,
        #loanCalculatorPrint * { visibility: visible !important; }
        #loanCalculatorPrint {
            display: block !important;
            position: absolute;
            left: 0;
            top: 0;
            width: 210mm;
            background: #fff;
        }
        .no-print { display: none !important; }
        .lm-print-page {
            margin: 0;
            box-shadow: none;
        }
    }
    @media (max-width: 991px) {
        .lm-calculator-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
    }
    @media (max-width: 575px) {
        .lm-calculator-summary { grid-template-columns: 1fr; }
    }
    @if(request()->boolean('_lm_modal'))
        html,
        body {
            background: #f8fafc !important;
        }
        .lm-sidebar,
        #loanManagementHeader,
        .lm-breadcrumb-wrap,
        .lm-footer {
            display: none !important;
        }
        .lm-app,
        .lm-main,
        .lm-content {
            display: block !important;
            width: 100% !important;
            min-height: auto !important;
            margin: 0 !important;
        }
        .lm-main,
        .lm-content {
            padding: 0 !important;
        }
        .content-header {
            padding: 14px 16px 0 !important;
        }
        .content.lm-calculator {
            padding: 14px 16px 18px !important;
        }
        .lm-calculator .box {
            margin-bottom: 14px;
        }
    @endif
</style>

<section class="content-header no-print">
    <h1>Installment Calculator <small>Generate installment result before creating loan</small></h1>
</section>

<section class="content lm-calculator no-print">
    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">Installment Input</h3>
        </div>
        <div class="box-body">
            <form id="loanCalculatorForm" class="row" autocomplete="off">
                <div class="col-sm-6 col-md-3">
                    <div class="form-group">
                        <label>Total Price</label>
                        <input type="number" step="0.01" min="0" id="calc_total_price" class="form-control" value="{{ $defaults['total_price'] }}">
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="form-group">
                        <label>Join Payment / កក់ខ្លះ</label>
                        <input type="number" step="0.01" min="0" id="calc_down_payment" class="form-control" value="{{ $defaults['down_payment'] }}">
                    </div>
                </div>
                <div class="col-sm-6 col-md-2">
                    <div class="form-group">
                        <label>Interest Rate (%)</label>
                        <input type="number" step="0.01" min="0" id="calc_interest_rate" class="form-control" value="{{ $defaults['interest_rate'] }}">
                    </div>
                </div>
                <div class="col-sm-6 col-md-2">
                    <div class="form-group">
                        <label>Interest Type</label>
                        <select id="calc_interest_type" class="form-control">
                            <option value="flat" {{ ($defaults['interest_type'] ?? 'flat') === 'flat' ? 'selected' : '' }}>បង់ថេរ</option>
                            <option value="reducing_balance" {{ ($defaults['interest_type'] ?? 'flat') === 'reducing_balance' ? 'selected' : '' }}>បង់ថយ</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-6 col-md-2">
                    <div class="form-group">
                        <label>Amount Month</label>
                        <input type="number" step="1" min="1" max="360" id="calc_duration_months" class="form-control" value="{{ $defaults['duration_months'] }}">
                    </div>
                </div>
                <div class="col-sm-6 col-md-2">
                    <div class="form-group">
                        <label>First Due Date</label>
                        <input type="date" id="calc_first_due_date" class="form-control" value="{{ $defaults['first_due_date'] }}">
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="lm-calculator-actions">
                        <button type="button" class="btn btn-primary" id="btnPrintLoanCalculator">
                            <i class="fa fa-print"></i> Print Calculate Installment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="lm-calculator-summary">
        <div class="lm-calculator-summary-card">
            <small>Principal After Deposit</small>
            <strong id="calc_principal_result">0.00</strong>
        </div>
        <div class="lm-calculator-summary-card">
            <small>Interest Per Month</small>
            <strong id="calc_interest_result">0.00</strong>
        </div>
        <div class="lm-calculator-summary-card">
            <small>Monthly Payment</small>
            <strong id="calc_monthly_result">0.00</strong>
        </div>
        <div class="lm-calculator-summary-card">
            <small>Total Payable</small>
            <strong id="calc_total_result">0.00</strong>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header">
            <h3 class="box-title">Generated Result</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped" id="loanCalculatorSchedule">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th class="text-right">Principal</th>
                        <th class="text-right">Interest</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th class="text-right" id="calc_total_principal">0.00</th>
                        <th class="text-right" id="calc_total_interest">0.00</th>
                        <th class="text-right" id="calc_total_payment">0.00</th>
                        <th class="text-right" id="calc_final_balance">0.00</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

<section id="loanCalculatorPrint">
    <div class="lm-print-page">
        <div class="lm-print-brand">{{ Session::get('business.name', 'Installment Management') }}</div>
        <div class="lm-print-tagline">Installment calculation preview | Generated from Installment Calculator</div>

        <div class="lm-print-info-grid">
            <table class="lm-print-info-table">
                <tr><td class="label">Document</td><td class="value lm-print-red" id="print_calc_doc_no">CALC</td></tr>
                <tr><td class="label">Installment Date</td><td class="value" id="print_calc_loan_date">-</td></tr>
                <tr><td class="label">First Due</td><td class="value" id="print_calc_first_due">-</td></tr>
            </table>
            <table class="lm-print-info-table">
                <tr><td class="label">Customer</td><td class="value">Calculator Preview</td></tr>
                <tr><td class="label">Phone</td><td class="value">-</td></tr>
                <tr><td class="label">Created By</td><td class="value">{{ auth()->user()->username ?? auth()->user()->first_name ?? '-' }}</td></tr>
            </table>
            <table class="lm-print-info-table">
                <tr><td class="label">Duration</td><td class="value"><span id="print_calc_duration">0</span> months</td></tr>
                <tr><td class="label">Interest Rate</td><td class="value lm-print-red"><span id="print_calc_rate">0.00</span>%</td></tr>
                <tr><td class="label">Printed At</td><td class="value" id="print_calc_printed_at">-</td></tr>
            </table>
        </div>

        <div class="lm-print-product-title">
            Installment Calculator Form
            <span class="lm-print-date-bar" id="print_calc_title_date">-</span>
        </div>
        <table class="lm-print-table">
            <thead>
                <tr>
                    <th style="width:7mm;">#</th>
                    <th style="width:30mm;">Code</th>
                    <th>Product / Form</th>
                    <th style="width:16mm;">Qty</th>
                    <th style="width:28mm;">Price</th>
                    <th style="width:30mm;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>CALC</td>
                    <td class="text-left">Calculate Installment</td>
                    <td>1</td>
                    <td class="money-cell">$<span id="print_calc_total_price_a">0.00</span></td>
                    <td class="money-cell">$<span id="print_calc_total_price_b">0.00</span></td>
                </tr>
                <tr>
                    <td colspan="6" class="lm-print-summary-cell">
                        <table class="lm-print-summary-inner">
                            <tr>
                                <td class="lm-print-summary-terms-cell">
                                    <table class="lm-print-summary-terms">
                                        <tr>
                                            <td>Duration Months</td>
                                            <td class="text-right" id="print_calc_duration_terms">0</td>
                                        </tr>
                                        <tr>
                                            <td>Deposit Percent</td>
                                            <td class="text-right"><span id="print_calc_deposit_percent">0.00</span>%</td>
                                        </tr>
                                        <tr>
                                            <td>Interest Rate</td>
                                            <td class="text-right lm-print-red"><span id="print_calc_rate_terms">0.00</span>%</td>
                                        </tr>
                                    </table>
                                </td>
                                <td class="lm-print-summary-totals-cell">
                                    <table class="lm-print-summary-totals">
                                        <tr>
                                            <td>Total Price</td>
                                            <td class="text-right">$<span id="print_calc_total_price_c">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td>Join Payment / Deposit</td>
                                            <td class="text-right">$<span id="print_calc_down_payment">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td>Balance / Principal</td>
                                            <td class="text-right lm-print-red">$<span id="print_calc_principal">0.00</span></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="lm-print-table" style="margin-top:0;">
            <thead>
                <tr>
                    <th style="width:7mm;">#</th>
                    <th style="width:25mm;">Due Date</th>
                    <th style="width:23mm;">Principal</th>
                    <th style="width:23mm;">Interest</th>
                    <th style="width:28mm;" class="lm-print-orange">Payment</th>
                    <th style="width:28mm;">Paid Date</th>
                    <th style="width:28mm;">Paid Amount</th>
                    <th style="width:22mm;">Balance</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody id="print_calc_schedule_body"></tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total</th>
                    <th class="text-right">$<span id="print_calc_total_principal">0.00</span></th>
                    <th class="text-right">$<span id="print_calc_total_interest">0.00</span></th>
                    <th class="text-right">$<span id="print_calc_total_payment">0.00</span></th>
                    <th colspan="4"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
@endsection

@section('javascript')
<script>
    (function($) {
        function numberValue(selector) {
            var value = parseFloat($(selector).val());
            return Number.isFinite(value) ? value : 0;
        }

        function money(value) {
            var number = parseFloat(value);
            return Number.isFinite(number) ? number.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) : '0.00';
        }

        function addMonths(date, months) {
            var next = new Date(date.getTime());
            var day = next.getDate();
            next.setMonth(next.getMonth() + months);
            if (next.getDate() !== day) {
                next.setDate(0);
            }
            return next;
        }

        function dateText(date) {
            return date.toISOString().slice(0, 10);
        }

        function nowText() {
            var date = new Date();
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') +
                ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }

        function setPrintText(selector, value) {
            $(selector).text(value);
        }

        function recalculateLoan() {
            var totalPrice = Math.max(0, numberValue('#calc_total_price'));
            var downPayment = Math.min(totalPrice, Math.max(0, numberValue('#calc_down_payment')));
            var principal = Math.max(0, totalPrice - downPayment);
            var ratePercent = Math.max(0, numberValue('#calc_interest_rate'));
            var rate = ratePercent / 100;
            var interestType = $('#calc_interest_type').val() || 'flat';
            var months = Math.max(1, parseInt($('#calc_duration_months').val(), 10) || 1);
            var firstDueValue = $('#calc_first_due_date').val();
            var firstDue = firstDueValue ? new Date(firstDueValue + 'T00:00:00') : addMonths(new Date(), 1);
            var today = dateText(new Date());
            var depositPercent = totalPrice > 0 ? (downPayment / totalPrice * 100) : 0;

            var principalPerMonth = Math.round((principal / months) * 100) / 100;
            var interestPerMonth = Math.round((principal * rate) * 100) / 100;
            var remaining = principal;
            var totalPrincipal = 0;
            var totalInterest = 0;
            var totalPayment = 0;
            var firstInterest = 0;
            var firstPayment = 0;
            var rows = '';
            var printRows = '';

            for (var i = 1; i <= months; i++) {
                var principalPart = i === months ? Math.round(remaining * 100) / 100 : principalPerMonth;
                var interest = interestType === 'reducing_balance'
                    ? Math.round((remaining * rate) * 100) / 100
                    : interestPerMonth;
                var rowTotal = Math.round((principalPart + interest) * 100) / 100;
                if (i === 1) {
                    firstInterest = interest;
                    firstPayment = rowTotal;
                }
                remaining = Math.max(0, Math.round((remaining - principalPart) * 100) / 100);
                totalPrincipal += principalPart;
                totalInterest += interest;
                totalPayment += rowTotal;

                rows += '<tr>' +
                    '<td>' + i + '</td>' +
                    '<td>' + dateText(addMonths(firstDue, i - 1)) + '</td>' +
                    '<td class="text-right">' + money(principalPart) + '</td>' +
                    '<td class="text-right">' + money(interest) + '</td>' +
                    '<td class="text-right">' + money(rowTotal) + '</td>' +
                    '<td class="text-right">' + money(remaining) + '</td>' +
                '</tr>';

                printRows += '<tr>' +
                    '<td>' + i + '</td>' +
                    '<td>' + dateText(addMonths(firstDue, i - 1)) + '</td>' +
                    '<td class="text-right">$ ' + money(principalPart) + '</td>' +
                    '<td class="text-right">$ ' + money(interest) + '</td>' +
                    '<td class="text-right">$ ' + money(rowTotal) + '</td>' +
                    '<td>-</td>' +
                    '<td>-</td>' +
                    '<td class="text-right">$ ' + money(remaining) + '</td>' +
                    '<td></td>' +
                '</tr>';
            }

            $('#calc_principal_result').text(money(principal));
            $('#calc_interest_result').text(money(firstInterest));
            $('#calc_monthly_result').text(money(firstPayment));
            $('#calc_total_result').text(money(totalPayment));
            $('#calc_total_principal').text(money(totalPrincipal));
            $('#calc_total_interest').text(money(totalInterest));
            $('#calc_total_payment').text(money(totalPayment));
            $('#calc_final_balance').text(money(remaining));
            $('#loanCalculatorSchedule tbody').html(rows);

            setPrintText('#print_calc_doc_no', 'CALC-' + today.replace(/-/g, ''));
            setPrintText('#print_calc_loan_date', today);
            setPrintText('#print_calc_first_due', dateText(firstDue));
            setPrintText('#print_calc_duration', months);
            setPrintText('#print_calc_duration_terms', months);
            setPrintText('#print_calc_rate', money(ratePercent));
            setPrintText('#print_calc_rate_terms', money(ratePercent));
            setPrintText('#print_calc_printed_at', nowText());
            setPrintText('#print_calc_title_date', today);
            setPrintText('#print_calc_total_price_a', money(totalPrice));
            setPrintText('#print_calc_total_price_b', money(totalPrice));
            setPrintText('#print_calc_total_price_c', money(totalPrice));
            setPrintText('#print_calc_down_payment', money(downPayment));
            setPrintText('#print_calc_principal', money(principal));
            setPrintText('#print_calc_deposit_percent', money(depositPercent));
            setPrintText('#print_calc_total_principal', money(totalPrincipal));
            setPrintText('#print_calc_total_interest', money(totalInterest));
            setPrintText('#print_calc_total_payment', money(totalPayment));
            $('#print_calc_schedule_body').html(printRows);
        }

        $(document).off('input.loanCalculator change.loanCalculator', '#loanCalculatorForm input, #loanCalculatorForm select')
            .on('input.loanCalculator change.loanCalculator', '#loanCalculatorForm input, #loanCalculatorForm select', recalculateLoan);
        $(document).off('click.loanCalculatorPrint', '#btnPrintLoanCalculator')
            .on('click.loanCalculatorPrint', '#btnPrintLoanCalculator', function() {
            var $button = $(this);
            if ($button.data('printing')) {
                return;
            }

            $button.data('printing', true).prop('disabled', true);
            recalculateLoan();

            var params = $.param({
                total_price: Math.max(0, numberValue('#calc_total_price')),
                down_payment: Math.max(0, numberValue('#calc_down_payment')),
                interest_rate: Math.max(0, numberValue('#calc_interest_rate')),
                interest_type: $('#calc_interest_type').val() || 'flat',
                duration_months: Math.max(1, parseInt($('#calc_duration_months').val(), 10) || 1),
                first_due_date: $('#calc_first_due_date').val(),
                auto_print: 1
            });
            var url = "{{ route('loan-management.loans.calculator.print') }}" + '?' + params;
            window.open(url, '_blank', 'noopener,width=1024,height=768');

            window.setTimeout(function() {
                $button.data('printing', false).prop('disabled', false);
            }, 1500);
        });
        $(function() {
            recalculateLoan();
        });
    })(jQuery);
</script>
@endsection
