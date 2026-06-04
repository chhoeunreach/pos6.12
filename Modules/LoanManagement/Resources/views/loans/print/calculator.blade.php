<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Calculate Loan</title>
    <style>
        @font-face { font-family: 'Roboto'; src: url('{{ asset("fonts/english/Roboto-Regular.ttf") }}') format('truetype'); }
        @font-face { font-family: 'RobotoBold'; src: url('{{ asset("fonts/english/Roboto-Bold.ttf") }}') format('truetype'); }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('{{ asset("fonts/khmer/Battambang-Regular.ttf") }}') format('truetype'); }

        :root {
            --orange: #ff8a00;
            --light-blue: #7f9bb1;
            --line: #222;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #3f454b;
            font-family: 'Roboto', 'Khmer OS Battambang', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.25;
        }
        .no-print {
            width: 210mm;
            margin: 0 auto;
            padding: 8px 0;
            text-align: right;
        }
        .no-print button {
            border: 1px solid #777;
            background: #fff;
            padding: 6px 12px;
            cursor: pointer;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px;
            padding: 10mm 14mm 8mm;
            background: #fff;
        }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .bold { font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif; font-weight: 700; }
        .orange { color: var(--orange); }
        .blue-label { color: var(--light-blue); font-weight: 700; }
        .red { color: red; }
        .muted { color: #666; }
        .nowrap { white-space: nowrap; }

        .header { min-height: 24mm; }
        .brand-row {
            display: grid;
            grid-template-columns: 32mm 1fr 32mm;
            align-items: center;
            min-height: 21mm;
        }
        .brand-title {
            color: var(--orange);
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
            font-size: 22px;
            line-height: 1.1;
            text-align: center;
            white-space: nowrap;
        }
        .tagline {
            margin-top: 2mm;
            padding-bottom: 0.7mm;
            border-bottom: 1.5px solid var(--orange);
            font-size: 9.6px;
            text-align: center;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1.08fr 1.25fr 1.08fr;
            gap: 7mm;
            margin-top: 1.8mm;
            margin-bottom: 1.5mm;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .info-table td {
            padding: 1.5mm 0;
            border: none;
            vertical-align: middle;
            font-size: 11px;
        }
        .info-table .label {
            width: 47%;
            color: var(--light-blue);
            font-weight: 700;
        }
        .info-table .value {
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
        }
        .address-row {
            padding: 1.5mm 0 2.5mm;
            border-bottom: 1.5px solid var(--orange);
        }
        .product-title {
            border-left: 1px solid var(--line);
            border-right: 1px solid var(--line);
            border-top: 1px solid var(--line);
            text-align: center;
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
            padding: 1.3mm;
        }
        .date-bar {
            float: right;
            min-width: 45mm;
            border-left: 1px solid var(--line);
            padding-left: 8mm;
        }
        table.print-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .print-table th,
        .print-table td {
            border: 1px solid var(--line);
            padding: 1.15mm 1.2mm;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
            line-height: 1.2;
        }
        .print-table th {
            background: #f6f6f6;
            font-weight: 700;
        }
        .money-cell {
            text-align: right !important;
            white-space: nowrap;
        }
        .product-summary-cell {
            padding: 0 !important;
            text-align: left !important;
        }
        .summary-inner {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .print-table .summary-inner > tbody > tr > td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .summary-terms-cell {
            width: 67%;
            border-right: 1px solid var(--line) !important;
            padding: 2mm 2.5mm !important;
        }
        .summary-totals-cell { width: 33%; }
        .summary-terms,
        .summary-totals {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .print-table .summary-terms td,
        .print-table .summary-totals td {
            border: 0;
            padding: 0.8mm 0;
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
            font-size: 10px;
            text-align: left;
        }
        .print-table .summary-totals td {
            border-bottom: 1px solid var(--line);
            padding: 1.4mm 1.6mm;
        }
        .print-table .summary-totals tr:last-child td { border-bottom: 0; }
        .summary-amount { text-align: right !important; }
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20mm;
            margin-top: 9mm;
            text-align: center;
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
        }
        .signature-line {
            margin: 16mm auto 0;
            width: 48mm;
            border-top: 1px solid #000;
        }
        .notice {
            margin-top: 7mm;
            border: 1px solid #222;
            padding: 2mm;
            font-size: 9.5px;
        }
        .printed-date {
            margin-top: 1.5mm;
            font-size: 8px;
        }
        @page { size: A4 portrait; margin: 5mm; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 5mm 8mm 4mm;
            }
            .print-table th,
            .print-table td { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Calculate Loan</button>
    <button type="button" onclick="window.close()">Close</button>
</div>

<div class="page">
    <div class="header">
        <div class="brand-row">
            <div></div>
            <div class="brand-title">{{ $businessName }}</div>
            <div></div>
        </div>
        <div class="tagline">
            Loan calculation form preview | Generated from Loan Calculator
        </div>
    </div>

    <div class="info-grid">
        <table class="info-table">
            <tr><td class="label">Contract No</td><td class="value red">CALC-{{ now()->format('Ymd') }}</td></tr>
            <tr><td class="label">Invoice No</td><td class="value">-</td></tr>
            <tr><td class="label">Loan Date</td><td class="value">{{ $loanDate }}</td></tr>
            <tr><td class="label">End Date</td><td class="value">{{ $lastDueDate ? \Carbon\Carbon::parse($lastDueDate)->format('m-d-Y') : '-' }}</td></tr>
        </table>
        <table class="info-table">
            <tr><td class="label">Customer</td><td class="value">Calculator Preview</td></tr>
            <tr><td class="label">Phone</td><td class="value">-</td></tr>
            <tr><td class="label">ID No</td><td class="value">-</td></tr>
        </table>
        <table class="info-table">
            <tr><td class="label">First Due Date</td><td class="value">{{ \Carbon\Carbon::parse($firstDueDate)->format('m-d-Y') }}</td></tr>
            <tr><td class="label">Co-borrower</td><td class="value">-</td></tr>
            <tr><td class="label">Co-phone</td><td class="value">-</td></tr>
        </table>
    </div>
    <div class="address-row">
        <span class="blue-label">Address</span>
        <span class="bold">-</span>
    </div>

    <div class="product-title">
        Loan Calculator Form
        <span class="date-bar">{{ $loanDateTitle }}</span>
    </div>
    <table class="print-table product-table">
        <thead>
            <tr>
                <th style="width:7mm;">#</th>
                <th style="width:25mm;">Code</th>
                <th>Product / Description</th>
                <th style="width:12mm;">Qty</th>
                <th style="width:26mm;" colspan="2">Price</th>
                <th style="width:28mm;" colspan="2">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="bold">1</td>
                <td class="bold">CALC</td>
                <td class="text-left bold">Calculate Loan</td>
                <td class="bold">1</td>
                <td colspan="2" class="money-cell bold">${{ number_format($totalPrice, 2) }}</td>
                <td colspan="2" class="money-cell bold">${{ number_format($totalPrice, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="8" class="product-summary-cell">
                    <table class="summary-inner">
                        <tr>
                            <td class="summary-terms-cell">
                                <table class="summary-terms">
                                    <tr>
                                        <td>Duration Months</td>
                                        <td class="summary-amount">{{ $durationMonths }}</td>
                                    </tr>
                                    <tr>
                                        <td>Deposit Percent</td>
                                        <td class="summary-amount">{{ number_format($downPercent, 2) }}%</td>
                                    </tr>
                                    <tr>
                                        <td>Interest Rate</td>
                                        <td class="summary-amount red">{{ number_format($interestRate, 2) }}%</td>
                                    </tr>
                                </table>
                            </td>
                            <td class="summary-totals-cell">
                                <table class="summary-totals">
                                    <tr>
                                        <td>Total Price</td>
                                        <td class="summary-amount">${{ number_format($totalPrice, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Join Payment / Deposit</td>
                                        <td class="summary-amount">${{ number_format($downPayment, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Balance / Principal</td>
                                        <td class="summary-amount red">${{ number_format($principal, 2) }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="print-table schedule-table" style="margin-top:0;">
        <thead>
            <tr>
                <th style="width:7mm;">#</th>
                <th style="width:24mm;">Due Date</th>
                <th style="width:21mm;">Principal</th>
                <th style="width:21mm;">Interest</th>
                <th style="width:25mm;" class="orange">Payment</th>
                <th style="width:29mm;">Paid Date</th>
                <th style="width:29mm;">Paid Amount</th>
                <th style="width:16mm;">Balance</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($installments as $row)
                <tr>
                    <td class="bold">{{ $row->installment_number }}</td>
                    <td class="bold nowrap">{{ \Carbon\Carbon::parse($row->installmentdate)->format('d-m-Y') }}</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->installment_value, 2) }}</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->benefit_value, 2) }}</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->amount_due, 2) }}</td>
                    <td>-</td>
                    <td>-</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->balance, 2) }}</td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" class="text-right bold">Total</td>
                <td class="text-right bold">$ {{ number_format($schedulePrincipalTotal, 2) }}</td>
                <td class="text-right bold">$ {{ number_format($scheduleInterestTotal, 2) }}</td>
                <td class="text-right bold">$ {{ number_format($scheduleTotalAmount, 2) }}</td>
                <td colspan="4" class="muted">Calculator preview only</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-row">
        <div>
            <div>Borrower Signature</div>
            <div class="signature-line"></div>
        </div>
        <div>
            <div>Lender Signature</div>
            <div class="signature-line"></div>
        </div>
    </div>

    <div class="notice">
        <span class="bold">Note:</span>
        This document is a loan calculation preview. Confirm customer, product, payment, and loan terms before creating an official loan.
    </div>

    <div class="printed-date">Printed date&nbsp;&nbsp;&nbsp;&nbsp;{{ $printedAt }}</div>
</div>

<script>
    var loanCalculatorAutoPrintStarted = false;

    function waitForLoanPrintAssets() {
        var imagePromises = Array.prototype.map.call(document.images || [], function (image) {
            if (image.complete) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        });

        var fontPromise = Promise.resolve();
        if (document.fonts && typeof document.fonts.ready !== 'undefined') {
            fontPromise = document.fonts.ready.catch(function () {
                return null;
            });
        }

        return Promise.all([fontPromise].concat(imagePromises));
    }

    function triggerAutoPrint() {
        if (loanCalculatorAutoPrintStarted) {
            return;
        }

        loanCalculatorAutoPrintStarted = true;
        waitForLoanPrintAssets().finally(function () {
            window.setTimeout(function () {
                window.focus();
                window.print();
            }, 350);
        });
    }

    window.addEventListener('load', function () {
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            triggerAutoPrint();
        }
    });
</script>
</body>
</html>
