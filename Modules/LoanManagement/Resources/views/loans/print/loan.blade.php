<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Loan {{ $loanRow->loan_number ?? $loanRow->id }}</title>
    <style>
        @font-face { font-family: 'Noto Sans Khmer'; src: url('{{ asset('fonts/khmer/NotoSansKhmer-Regular.ttf') }}') format('truetype'); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('{{ asset('fonts/khmer/KhmerOSbattambang.ttf') }}') format('truetype'); font-weight: 400; font-style: normal; font-display: swap; }

        :root {
            --orange: #ff8a00;
            --light-blue: #7f9bb1;
            --line: #1f2933;
            --grid-line: #9aa4ad;
            --soft-line: #c7ced4;
            --header-bg: #f4f6f8;
            --row-alt: #fbfcfd;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #3f454b;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            font-size: 11.5px;
            line-height: 1.35;
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
        .no-print .copy-status {
            display: inline-block;
            margin-left: 10px;
            color: #fff;
            font-size: 12px;
            vertical-align: middle;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px;
            padding: 8mm 8mm 6mm;
            background: #fff;
        }
        .kh-moul { font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif; font-weight: 400; }
        .kh { font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bold { font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif; font-weight: 700; }
        .orange { color: #ff8a00; }
        .blue-label { color: #7f9bb1; font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif; font-size: 12px; font-weight: 700; }
        .red { color: red; }
        .muted { color: #666; }

        .header {
            position: relative;
            min-height: 28mm;
        }
        .brand-row {
            display: grid;
            grid-template-columns: 31mm 1fr 31mm;
            position: relative;
            align-items: center;
            min-height: 24mm;
        }
        .logo-wrap {
            width: 24mm;
            height: 22mm;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo {
            max-width: 24mm;
            max-height: 22mm;
            object-fit: contain;
        }
        .brand-title {
            color: #ff8a00;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 29px;
            line-height: 1.16;
            text-align: center;
            white-space: nowrap;
        }
        .brand-spacer {
            width: 32mm;
        }
        .tagline {
            margin-top: 2mm;
            padding-bottom: 1mm;
            border-bottom: 1.5px solid #ff8a00;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 10.4px;
            line-height: 1.32;
            text-align: left;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1.08fr 1.25fr 1.08fr;
            gap: 9mm;
            margin-top: 7mm;
            margin-bottom: 3mm;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .info-table td {
            padding: 1mm 0;
            vertical-align: middle;
            border: none;
            font-size: 11.8px;
            line-height: 1.35;
        }
        .info-table .label {
            width: 42%;
            color: #7f9bb1;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 11.8px;
        }
        .info-table .value {
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
        }
        .address-row {
            padding: 1.3mm 0 2.4mm;
            border-bottom: 1.5px solid #ff8a00;
        }
        .address-row .blue-label {
            display: inline-block;
            margin-right: 2.5mm;
        }
        table.print-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #1f2933;
            padding: 0.55mm 0.68mm;
            text-align: center;
            vertical-align: middle;
            font-size: 9.3px;
            line-height: 1.22;
            font-weight: 700;
        }
        .print-table th {
            background: #f4f6f8;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
        }
        .print-table .dotted td,
        .schedule-table td {
            border: 1px solid #9aa4ad;
        }
        .print-table .solid td,
        .print-table .solid th {
            border-style: solid;
        }
        .product-title {
            border-left: 1px solid #1f2933;
            border-right: 1px solid #1f2933;
            border-top: 1.8px solid #ff8a00;
            background: #fff;
            display: grid;
            grid-template-columns: 1fr 46mm;
            align-items: center;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 400;
            padding: 0;
        }
        .product-title-text {
            padding: 1.55mm 1.8mm 1.35mm;
            text-align: center;
            font-size: 15.5px;
            line-height: 1.2;
            font-weight: 700;
        }
        .date-bar {
            border-left: 1px solid #1f2933;
            padding: 1.55mm 2mm 1.35mm;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            text-align: center;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 700;
        }
        .summary-row td {
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
        }
        .money-cell {
            text-align: right !important;
            white-space: nowrap;
        }
        .summary-label {
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            text-align: left !important;
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
            border-right: 1px solid #c7ced4 !important;
            padding: 1.7mm 2.3mm !important;
        }
        .summary-totals-cell {
            width: 33%;
        }
        .summary-terms {
            width: 65mm;
            border-collapse: collapse;
        }
        .print-table .summary-terms td {
            border: 0;
            padding: 0.28mm 0;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 9.8px;
            text-align: left;
            line-height: 1.32;
        }
        .print-table .summary-terms .term-value {
            width: 18mm;
            text-align: right;
        }
        .summary-totals {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .print-table .summary-totals td {
            border: 0;
            border-bottom: 1px solid #c7ced4;
            padding: 0.76mm 1.25mm;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 9.7px;
            line-height: 1.2;
        }
        .print-table .summary-totals tr:last-child td {
            border-bottom: 0;
        }
        .summary-total-label {
            text-align: left !important;
        }
        .summary-method-line {
            display: block;
            margin-top: 0.2mm;
            color: #333;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 8.8px;
            line-height: 1.25;
            word-break: break-word;
        }
        .summary-currency {
            width: 12mm;
            text-align: center !important;
        }
        .summary-amount {
            width: 24mm;
            text-align: right !important;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 0.46mm 0.56mm;
            font-size: 8.8px;
            line-height: 1.18;
            font-weight: 700;
        }
        .schedule-table th {
            background: #f4f6f8;
            font-size: 9px;
            border-top: 1.7px solid #1f2933;
            border-bottom: 1.35px solid #1f2933;
        }
        .schedule-table tbody tr {
            height: 5.2mm;
        }
        .schedule-table tbody tr:nth-child(even) td {
            background: #fbfcfd;
        }
        .schedule-table tbody tr.schedule-total-row td {
            background: #fff;
            border-top: 1.4px solid #1f2933;
            border-bottom: 1px solid #1f2933;
        }
        .schedule-table .payment-method-cell {
            color: #222;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            line-height: 1.24;
        }
        .schedule-table .payment-date-cell {
            line-height: 1.24;
        }
        .schedule-table .amount-cell {
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            white-space: nowrap;
        }
        .schedule-table .status-cell {
            text-align: center;
        }
        .status-pill {
            display: inline-block;
            min-width: 11.5mm;
            padding: 0.15mm 0.7mm;
            border: 1px solid #c7ced4;
            border-radius: 1.5mm;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 8px;
            line-height: 1.18;
            text-align: center;
            white-space: nowrap;
            background: #fff;
        }
        .status-paid {
            color: #075e34;
            border-color: #9cc9ad;
        }
        .status-partial {
            color: #9a5a00;
            border-color: #d9b76b;
        }
        .status-unpaid {
            color: #444;
            border-color: #b7bdc3;
        }
        .contact-line {
            color: blue;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 10.2px;
            text-align: right;
        }
        .warranty-line {
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 8.65px;
            line-height: 1.35;
        }
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 3.4mm;
            min-height: 22mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 14px;
            line-height: 1.35;
        }
        .signature-name {
            margin-top: 7.2mm;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 11.5px;
        }
        .signature-line {
            width: 37mm;
            border-top: 1.1px solid #000;
            margin: 3.2mm auto 0;
        }
        .notice {
            border-top: 1.6px solid #ff8a00;
            border-bottom: 1.6px solid #ff8a00;
            padding: 2mm 3.4mm;
            text-align: center;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-size: 8.5px;
            line-height: 1.38;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .notice .title {
            color: red;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 10.2px;
        }
        .payment-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18mm;
            padding-top: 3.5mm;
            align-items: end;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .payment-card {
            min-height: 42mm;
            text-align: center;
        }
        .payment-card .caption {
            color: #1244d8;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 9px;
            margin-bottom: 1mm;
        }
        .qr-large { max-width: 36mm; max-height: 36mm; }
        .qr-small { max-width: 28mm; max-height: 28mm; }
        .transfer-number {
            margin: 2.2mm 0 2.4mm;
            font-size: 12px;
        }
        .telegram-qr-block {
            margin-top: 1.8mm;
        }
        .printed-date {
            margin-top: 1.4mm;
            color: #999;
            font-size: 7.8px;
            text-align: center;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .footer-bold {
            margin-top: 4mm;
            text-align: center;
            font-family: 'Noto Sans Khmer', 'Khmer OS Battambang', 'Khmer UI', Arial, sans-serif;
            font-weight: 700;
            font-size: 10px;
        }
        .nowrap { white-space: nowrap; }
        .page.loan-print-compact {
            padding: 6mm 7.2mm 4.8mm;
        }
        .loan-print-compact .header {
            min-height: 28mm;
        }
        .loan-print-compact .brand-row {
            min-height: 23.5mm;
        }
        .loan-print-compact .logo-wrap {
            width: 23mm;
            height: 21.5mm;
        }
        .loan-print-compact .logo {
            max-width: 23mm;
            max-height: 21.5mm;
        }
        .loan-print-compact .brand-title {
            font-size: 28px;
            line-height: 1.16;
        }
        .loan-print-compact .tagline {
            margin-top: 1.6mm;
            padding-bottom: 1mm;
            font-size: 10.2px;
            line-height: 1.3;
        }
        .loan-print-compact .info-grid {
            margin-top: 6.8mm;
            margin-bottom: 2.8mm;
            gap: 8mm;
        }
        .loan-print-compact .info-table {
            width: 100%;
        }
        .loan-print-compact .info-table td {
            padding: 0.9mm 0;
            font-size: 11.2px;
            line-height: 1.28;
        }
        .loan-print-compact .info-table .label {
            font-size: 11.2px;
        }
        .loan-print-compact .address-row {
            padding: 1mm 0 2.2mm;
            font-size: 11.4px;
        }
        .loan-print-compact .product-title-text,
        .loan-print-compact .date-bar {
            padding-top: 0.8mm;
            padding-bottom: 0.7mm;
            font-size: 12px;
            line-height: 1.18;
        }
        .loan-print-compact .product-title-text {
            font-size: 14px;
        }
        .loan-print-compact .summary-terms-cell {
            padding: 1.1mm 2.1mm !important;
        }
        .loan-print-compact .print-table .summary-terms td {
            padding: 0.25mm 0;
        }
        .loan-print-compact .print-table .summary-totals td {
            padding: 0.68mm 1.2mm;
        }
        .loan-print-compact .print-table th,
        .loan-print-compact .print-table td {
            padding: 0.46mm 0.56mm;
            font-size: 8.8px;
            line-height: 1.16;
        }
        .loan-print-compact .schedule-table tbody tr {
            height: 5.15mm;
        }
        .loan-print-compact .schedule-table th,
        .loan-print-compact .schedule-table td {
            padding: 0.42mm 0.5mm;
            font-size: 8.45px;
            line-height: 1.14;
        }
        .loan-print-compact .schedule-table th {
            font-size: 8.75px;
        }
        .loan-print-compact .payment-method-cell,
        .loan-print-compact .payment-date-cell {
            line-height: 1.22;
        }
        .loan-print-compact .status-pill {
            min-width: 10.8mm;
            padding: 0.16mm 0.6mm;
            font-size: 7.65px;
            border-radius: 1.25mm;
        }
        .loan-print-compact .signature-row {
            margin-top: 3.2mm;
            min-height: 22mm;
        }
        .loan-print-compact .signature-name {
            margin-top: 6mm;
        }
        .loan-print-compact .notice {
            padding: 1.7mm 3mm;
            font-size: 8.25px;
            line-height: 1.34;
        }
        .loan-print-compact .notice .title {
            font-size: 10px;
        }
        .loan-print-compact .warranty-line {
            font-size: 7.95px;
            line-height: 1.25;
        }
        .loan-print-compact .payment-area {
            padding-top: 3mm;
            gap: 17mm;
        }
        .loan-print-compact .payment-card {
            min-height: 41mm;
        }
        .loan-print-compact .payment-card .caption {
            font-size: 8.75px;
            margin-bottom: 0.6mm;
        }
        .loan-print-compact .qr-large {
            max-width: 35mm;
            max-height: 35mm;
        }
        .loan-print-compact .qr-small {
            max-width: 28mm;
            max-height: 28mm;
        }
        .loan-print-compact .transfer-number {
            margin: 1mm 0 0.8mm;
            font-size: 11.3px;
        }
        .loan-print-compact .telegram-qr-block {
            margin-top: 2mm;
        }
        .loan-print-compact .printed-date {
            margin-top: 0.85mm;
            font-size: 7.55px;
        }

        @page { size: A4 portrait; margin: 5mm; }
        @media print {
            body {
                background: #fff;
                font-size: 11.2px;
                line-height: 1.34;
            }
            .no-print { display: none !important; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 3.5mm 5.6mm 3mm;
            }
            .page.loan-print-compact {
                padding: 3.2mm 5.2mm 2.8mm;
            }
            .print-table th,
            .print-table td { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .info-grid {
                gap: 7mm;
                margin-top: 6.2mm;
                margin-bottom: 2.5mm;
            }
            .info-table {
                width: 100%;
            }
            .address-row {
                padding: 0.85mm 0 2mm;
            }
            .schedule-table th,
            .schedule-table td {
                padding: 0.42mm 0.5mm;
                font-size: 8.45px;
                line-height: 1.14;
            }
            .loan-print-compact .schedule-table th,
            .loan-print-compact .schedule-table td {
                padding: 0.38mm 0.48mm;
                font-size: 8.25px;
                line-height: 1.12;
            }
            .loan-print-compact .schedule-table tbody tr {
                height: 5mm;
            }
            .contact-line {
                font-size: 9.5px;
            }
            .signature-row {
                margin-top: 3mm;
                min-height: 21mm;
            }
            .signature-name {
                margin-top: 5.8mm;
            }
            .signature-line {
                margin-top: 2.2mm;
            }
            .notice {
                padding: 1.55mm 3mm;
                font-size: 8.1px;
                line-height: 1.28;
            }
            .notice .title {
                font-size: 9.9px;
            }
            .warranty-line {
                font-size: 7.8px;
                line-height: 1.22;
            }
            .payment-area {
                gap: 16mm;
                padding-top: 2.6mm;
            }
            .payment-card {
                min-height: 40mm;
            }
            .payment-card .caption {
                font-size: 8.65px;
                margin-bottom: 0.7mm;
            }
            .qr-large {
                max-width: 34mm;
                max-height: 34mm;
            }
            .qr-small {
                max-width: 27mm;
                max-height: 27mm;
            }
            .transfer-number {
                margin: 1.6mm 0 1mm;
                font-size: 11.2px;
            }
            .telegram-qr-block {
                margin-top: 1.5mm;
            }
            .printed-date {
                margin-top: 0.85mm;
                font-size: 7.45px;
            }
        }
    </style>
</head>
<body>
@php
    $productTotal = $products->sum(fn ($p) => (float) ($p->subtotal ?? ((float) ($p->quantity ?? 1) * (float) ($p->unit_price_inc_tax ?? 0))));
    $fixedScheduleRowCount = 12;
    $scheduleRowsForPrint = $installments->take($fixedScheduleRowCount)->values();
    $schedulePrincipalTotal = $scheduleRowsForPrint->sum(fn ($row) => (float) ($row->installment_value ?? 0));
    $scheduleInterestTotal = $scheduleRowsForPrint->sum(fn ($row) => (float) ($row->benefit_value ?? $row->interest_due ?? $row->interest_amount ?? 0));
    $scheduleTotalAmount = $scheduleRowsForPrint->sum(function ($row) {
        $total = round((float) ($row->installment_value ?? 0) + (float) ($row->benefit_value ?? 0), 2);
        if ($total <= 0) {
            $total = (float) ($row->amount_due ?? $row->schedule_amount ?? 0);
        }

        $paid = (float) ($row->paid_value ?? $row->paid_amount ?? $row->amount_paid ?? 0);
        $balance = round((float) ($row->balance_amount ?? $row->amount_balance ?? max(0, $total - $paid)), 2);

        return $paid <= 0 && $balance > 0 && $balance < $total ? $balance : $total;
    });
    $downPayment = (float) ($loanRow->down_payment ?? 0);
    $loanAmount = (float) ($loanRow->principal_amount ?? max(0, $productTotal - $downPayment));
    if ($productTotal <= 0 && ($loanAmount > 0 || $downPayment > 0)) {
        $productTotal = $loanAmount + $downPayment;
    }
    $paidAmount = (float) ($loanRow->paid_amount ?? $downPayment);
    $balanceAmount = max(0, round($productTotal - $downPayment, 2));
    $currency = $loanRow->currency ?? 'USD';
    $loanDate = ! empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('m-d-Y') : '-';
    $loanDateTitle = ! empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('d-M-Y') : \Carbon\Carbon::now()->format('d-M-Y');
    $firstDueDate = $installments->first()?->installmentdate;
    $lastDueDate = $installments->last()?->installmentdate;
    $createdBy = $createdByName ?? ($loanRow->created_by_name_snapshot ?? '-');
    $loanMeta = !empty($loanRow->meta_json) ? (json_decode((string) $loanRow->meta_json, true) ?: []) : [];
    $duration = max(
        (int) ($loanRow->duration_months ?? 0),
        (int) ($loanMeta['duration_months'] ?? 0),
        (int) ($loanRow->installment_count ?? 0),
        max(1, $installments->count())
    );
    $interestRate = (float) ($loanRow->interest_rate ?? ($loanMeta['interest_rate'] ?? ($loanMeta['raw_import_row']['interest_rate'] ?? 0)));
    $downPercent = $productTotal > 0 ? ($downPayment / max($productTotal, 1) * 100) : 0;
    $paymentsBySchedule = $payments->groupBy(fn ($payment) => $payment->_print_schedule_id ?? $payment->schedule_id ?? null);
    $isCompactPrint = true;
    $paymentTypes = [];
    try {
        $paymentTypes = app(\App\Utils\TransactionUtil::class)->payment_types(
            $loanRow->main_location_id ?? null,
            true,
            (int) (session('user.business_id') ?? 0)
        );
    } catch (\Throwable $e) {
        $paymentTypes = [];
    }
    $paymentMethodDisplayName = function ($method) use ($paymentTypes) {
        $method = trim((string) $method);
        if ($method === '') {
            return 'Payment';
        }

        $normalized = strtolower(str_replace([' ', '-', '_'], '', $method));
        if (strpos($normalized, 'aba') !== false) {
            return 'ABA';
        }
        if (strpos($normalized, 'wing') !== false) {
            return 'Wing';
        }
        $knownNames = [
            'aba' => 'ABA',
            'ababank' => 'ABA',
            'abapay' => 'ABA',
            'wing' => 'Wing',
            'wingmoney' => 'Wing',
            'cash' => 'Cash',
        ];

        return $knownNames[$normalized]
            ?? (string) ($paymentTypes[$method] ?? ucfirst(str_replace('_', ' ', $method)));
    };
    $paymentMethodNameForPrint = function ($payment) use ($paymentMethodDisplayName) {
        $method = trim((string) ($payment->payment_method_snapshot ?? ''));
        if ($method !== '') {
            return $paymentMethodDisplayName($method);
        }

        return $paymentMethodDisplayName($payment->method ?? $payment->channel ?? '');
    };
    $paymentLinesForPrint = function ($rows) use ($paymentMethodNameForPrint) {
        return collect($rows)
            ->reduce(function ($carry, $payment) use ($paymentMethodNameForPrint) {
                $amount = (float) ($payment->_print_amount ?? $payment->total_paid_base ?? $payment->amount ?? 0);
                if ($amount <= 0) {
                    return $carry;
                }

                $method = $paymentMethodNameForPrint($payment);
                $carry[$method] = ($carry[$method] ?? 0) + $amount;

                return $carry;
            }, collect())
            ->map(fn ($amount, $method) => e($method).' $'.number_format($amount, 2))
            ->values();
    };
    $downPaymentLines = collect();
    if ($downPayment > 0) {
        $downPaymentRows = $payments
            ->filter(fn ($payment) => empty($payment->schedule_id) && empty($payment->_print_schedule_id))
            ->values();
        $downPaymentLines = $paymentLinesForPrint($downPaymentRows);

        if ($downPaymentLines->isEmpty()) {
            $remainingDownPayment = $downPayment;
            $downPaymentRows = $payments
                ->sortBy(fn ($payment) => ($payment->paid_date ?? $payment->paid_at ?? '').'-'.str_pad((string) ($payment->id ?? 0), 10, '0', STR_PAD_LEFT))
                ->map(function ($payment) use (&$remainingDownPayment) {
                    if ($remainingDownPayment <= 0) {
                        return null;
                    }

                    $amount = (float) ($payment->total_paid_base ?? $payment->amount ?? 0);
                    if ($amount <= 0) {
                        return null;
                    }

                    $line = clone $payment;
                    $line->_print_amount = min($amount, $remainingDownPayment);
                    $remainingDownPayment -= $line->_print_amount;

                    return $line;
                })
                ->filter()
                ->values();
            $downPaymentLines = $paymentLinesForPrint($downPaymentRows);
        }
    }
    $printedAt = \Carbon\Carbon::now()->format('d-M-Y H:i:s');
@endphp

<div class="no-print">
    <button type="button" id="copy_loan_as_image_button">Copy as Image</button>
    <button type="button" onclick="window.print()">Print Loan</button>
    <button type="button" onclick="window.close()">Close</button>
    <span class="copy-status" id="copy_loan_as_image_status"></span>
</div>

<div class="page {{ $isCompactPrint ? 'loan-print-compact' : '' }}">
    <div class="header">
        <div class="brand-row">
            <div class="logo-wrap">
                @if(! empty($logo))
                    <img class="logo" src="{{ $logo }}" alt="logo" onerror="this.style.display='none'">
                @endif
            </div>
            <div class="brand-title">{{ $businessName }}</div>
            <div class="brand-spacer"></div>
        </div>
        <div class="tagline">
            លក់ដុំ-រាយ និងសេវាកម្ម | សម្រាប់ព័ត៌មានបង់ប្រាក់ Telegram លេខ {{ $telegramNumber ?? '0717221349' }}
        </div>
    </div>

    <div class="info-grid">
        <table class="info-table">
            <tr><td class="label">លេខកិច្ចសន្យា</td><td class="value red">{{ $loanRow->loan_number ?? $loanRow->id }}</td></tr>
            <tr><td class="label">Invoice No</td><td class="value">{{ $sourceInvoiceDisplay ?? '-' }}</td></tr>
            <tr><td class="label">កាលបរិច្ឆេទទី១</td><td class="value">{{ $firstDueDate ? \Carbon\Carbon::parse($firstDueDate)->format('m-d-Y') : '-' }}</td></tr>
            <tr><td class="label">កាលបរិច្ឆេទបញ្ចប់</td><td class="value">{{ $lastDueDate ? \Carbon\Carbon::parse($lastDueDate)->format('m-d-Y') : '-' }}</td></tr>
        </table>
        <table class="info-table">
            <tr><td class="label">ឈ្មោះអតិថិជន</td><td class="value">{{ $customer->name ?? '-' }}</td></tr>
            <tr><td class="label">លេខទូរស័ព្ទ</td><td class="value">{{ $customer->mobile ?? '-' }}</td></tr>
            <tr><td class="label">លេខសម្គាល់</td><td class="value">{{ $customer->custom_field1 ?? '-' }}</td></tr>
        </table>
        <table class="info-table info-table-last">
            <tr><td class="label">កាលបរិច្ឆេទខ្ចីប្រាក់</td><td class="value">{{ $loanDate }}</td></tr>
            <tr><td class="label">អ្នករួមខ្ចី</td><td class="value">{{ $customer->co_borrower ?? '-' }}</td></tr>
            <tr><td class="label">លេខអ្នករួមខ្ចី</td><td class="value">{{ $customer->co_borrower_phone ?? '-' }}</td></tr>
        </table>
    </div>
    <div class="address-row">
        <span class="blue-label">អាសយដ្ឋាន</span>
        <span class="bold">{{ $customer->address_line_1 ?? '-' }}</span>
    </div>

    <div class="product-title">
        <span class="product-title-text">វិក្កយបត្រកម្ចី</span>
        <span class="date-bar">{{ $loanDateTitle }}</span>
    </div>
    <table class="print-table product-table">
        <colgroup>
            <col style="width:7mm;">
            <col style="width:25mm;">
            <col>
            <col style="width:12mm;">
            <col style="width:13mm;">
            <col style="width:13mm;">
            <col style="width:14mm;">
            <col style="width:14mm;">
        </colgroup>
        <thead>
            <tr>
                <th>ល.រ</th>
                <th>លេខទំនិញ</th>
                <th>ឈ្មោះផលិតផល</th>
                <th>ចំនួន</th>
                <th colspan="2">តម្លៃ</th>
                <th colspan="2">សរុប</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $i => $p)
                @php
                    $qty = (float) ($p->quantity ?? 1);
                    $price = (float) ($p->unit_price_inc_tax ?? 0);
                    $subtotal = round((float) ($p->subtotal ?? ($qty * $price)), 2);
                    $imei = trim((string) ($p->imei ?? ''));
                    $serial = trim((string) ($p->serial ?? ''));
                    $color = trim((string) ($p->color ?? ''));
                    $storage = trim((string) ($p->storage ?? ''));
                    $showImei = $imei !== '' && $imei !== '-';
                    $showSerial = $serial !== '' && $serial !== '-' && strcasecmp($serial, $imei) !== 0;
                    $showColor = $color !== '' && $color !== '-';
                    $showStorage = $storage !== '' && $storage !== '-';
                @endphp
                <tr>
                    <td class="bold">{{ $i + 1 }}</td>
                    <td class="bold">{{ $p->product_sku ?? '-' }}</td>
                    <td class="text-left bold">
                        {{ $p->product_name ?? '-' }}
                        @if($showColor) / Color: {{ $color }} @endif
                        @if($showStorage) / Storage: {{ $storage }} @endif
                        @if($showImei) / IMEI: {{ $imei }} @endif
                        @if($showSerial) / Serial: {{ $serial }} @endif
                    </td>
                    <td class="bold">{{ number_format($qty, 0) }}</td>
                    <td colspan="2" class="money-cell bold">${{ number_format($price, 2) }}</td>
                    <td colspan="2" class="money-cell bold">${{ number_format($subtotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No products</td></tr>
            @endforelse
            <tr class="summary-row">
                <td colspan="8" class="product-summary-cell">
                    <table class="summary-inner">
                        <tr>
                            <td class="summary-terms-cell">
                                <table class="summary-terms">
                                    <tr>
                                        <td>រយៈពេលបង់(ខែ)</td>
                                        <td class="term-value">{{ $duration }}</td>
                                    </tr>
                                    <tr>
                                        <td>ភាគរយបង់មុន</td>
                                        <td class="term-value">{{ number_format($downPercent, 2) }}%</td>
                                    </tr>
                                    <tr>
                                        <td>អត្រាការប្រាក់</td>
                                        <td class="term-value red">{{ number_format($interestRate, 2) }}%</td>
                                    </tr>
                                </table>
                            </td>
                            <td class="summary-totals-cell">
                                <table class="summary-totals">
                                    <tr>
                                        <td class="summary-total-label">តម្លៃសរុប</td>
                                        <td class="summary-currency">$</td>
                                        <td class="summary-amount">{{ number_format($productTotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-total-label">
                                            ប្រាក់ចូលរួមមុន
                                            @if($downPaymentLines->isNotEmpty())
                                                <span class="summary-method-line">{!! $downPaymentLines->implode(' ') !!}</span>
                                            @endif
                                        </td>
                                        <td class="summary-currency">$</td>
                                        <td class="summary-amount">{{ number_format($downPayment, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-total-label">ប្រាក់នៅខ្វះ</td>
                                        <td colspan="2" class="summary-amount red">${{ number_format($balanceAmount, 2) }}</td>
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
        <colgroup>
            <col style="width:7mm;">
            <col style="width:24mm;">
            <col style="width:21mm;">
            <col style="width:21mm;">
            <col style="width:25mm;">
            <col style="width:29mm;">
            <col style="width:29mm;">
            <col style="width:16mm;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>ល.រ</th>
                <th>ថ្ងៃ-ខែ-ឆ្នាំ</th>
                <th>ប្រាក់ដើម</th>
                <th>ការប្រាក់</th>
                <th class="orange">ទឹកប្រាក់ត្រូវបង់</th>
                <th>កាលបរិច្ឆេទ</th>
                <th>បង់ប្រាក់</th>
                <th>សរុប</th>
                <th>ចំណាំ</th>
            </tr>
        </thead>
        <tbody>
            @for($slot = 0; $slot < $fixedScheduleRowCount; $slot++)
                @php
                    $row = $scheduleRowsForPrint->get($slot);
                    $rowTotal = 0;
                    $rowPayments = collect();
                    $paid = 0;
                    $rowStatus = '';
                    $rowStatusClass = 'status-unpaid';
                    $paymentDates = collect();
                    $paymentLines = collect();

                    if ($row) {
                        $rowTotal = round((float) $row->installment_value + (float) $row->benefit_value, 2);
                        if ($rowTotal <= 0) {
                            $rowTotal = (float) ($row->amount_due ?? 0);
                        }
                        $rowPayments = $paymentsBySchedule->get($row->id, collect());
                        $rowPaymentAmount = round((float) $rowPayments->sum(fn ($p) => (float) ($p->_print_amount ?? $p->total_paid_base ?? $p->amount ?? 0)), 2);
                        $storedPaid = (float) ($row->paid_value ?? $row->paid_amount ?? $row->amount_paid ?? 0);
                        $rowBalance = round((float) ($row->balance_amount ?? $row->amount_balance ?? max(0, $rowTotal - $storedPaid)), 2);
                        $isCreditOnlyRow = $rowPaymentAmount <= 0 && $storedPaid > 0 && $rowBalance > 0 && $rowBalance < $rowTotal;
                        $paid = $isCreditOnlyRow ? 0 : ($rowPaymentAmount > 0 ? $rowPaymentAmount : $storedPaid);
                        if ($isCreditOnlyRow || ($paid <= 0 && $rowBalance > 0 && $rowBalance < $rowTotal)) {
                            $rowTotal = $rowBalance;
                        }
                        $discount = (float) ($row->discount_amount ?? 0);
                        $storedStatus = strtolower((string) ($row->status ?? ''));
                        $isPayOff = in_array($storedStatus, ['pay off', 'pay_off', 'payoff'], true);
                        $isPaidWithinRounding = $paid > 0 && $rowTotal > 0 && round($rowTotal - $paid - $discount, 2) <= 0.02;
                        $rowStatus = $isCreditOnlyRow
                            ? 'Unpaid'
                            : (($isPayOff || $isPaidWithinRounding || ($paid + $discount >= $rowTotal && $rowTotal > 0))
                            ? ($isPayOff ? 'Pay Off' : 'Paid')
                            : ($paid > 0 ? 'Partial' : ucfirst($row->status ?? '')));
                        $rowStatusClass = in_array(strtolower($rowStatus), ['paid', 'pay off'], true)
                            ? 'status-paid'
                            : (strtolower($rowStatus) === 'partial' ? 'status-partial' : 'status-unpaid');
                        $paymentDates = $rowPayments
                            ->map(fn ($p) => $p->paid_date ?? $p->paid_at ?? null)
                            ->filter()
                            ->map(fn ($date) => \Carbon\Carbon::parse($date)->format('Y-m-d').'|'.\Carbon\Carbon::parse($date)->format('d-m-Y'))
                            ->unique()
                            ->sort()
                            ->map(fn ($date) => explode('|', $date, 2)[1] ?? $date)
                            ->values();
                        $paymentLines = $paymentLinesForPrint($rowPayments);
                        if ($paymentLines->isEmpty() && $paid > 0) {
                            $paymentLines = collect(['Payment $'.number_format($paid, 2)]);
                        }
                        if ($paymentDates->isEmpty() && ! empty($row->paid_at)) {
                            $paymentDates = collect([\Carbon\Carbon::parse($row->paid_at)->format('d-m-Y')]);
                        }
                    }
                @endphp
                <tr>
                    <td class="bold">{{ $row->installment_number ?? ($slot + 1) }}</td>
                    <td class="bold nowrap">{{ ! empty($row?->installmentdate) ? \Carbon\Carbon::parse($row->installmentdate)->format('d-m-Y') : '' }}</td>
                    <td class="text-right amount-cell">{{ $row ? '$ '.number_format((float) $row->installment_value, 2) : '' }}</td>
                    <td class="text-right amount-cell">{{ $row ? '$ '.number_format((float) $row->benefit_value, 2) : '' }}</td>
                    <td class="text-right amount-cell">{{ $row ? '$ '.number_format($rowTotal, 2) : '' }}</td>
                    <td class="bold nowrap payment-date-cell">{!! $paymentDates->implode('<br>') !!}</td>
                    <td class="text-center payment-method-cell">{!! $paymentLines->implode('<br>') !!}</td>
                    <td class="text-right amount-cell">{{ $paid > 0 ? '$ '.number_format($paid, 2) : '' }}</td>
                    <td class="status-cell">
                        @if($rowStatus !== '')
                            <span class="status-pill {{ $rowStatusClass }}">{{ $rowStatus }}</span>
                        @endif
                    </td>
                </tr>
            @endfor
            <tr class="solid schedule-total-row">
                <td colspan="2" class="text-right bold">សរុប</td>
                <td class="text-right bold">$ {{ number_format($schedulePrincipalTotal, 2) }}</td>
                <td class="text-right bold">$ {{ number_format($scheduleInterestTotal, 2) }}</td>
                <td class="text-right bold">$ {{ number_format($scheduleTotalAmount, 2) }}</td>
                <td colspan="4" class="contact-line">សម្រាប់បង់លុយទំនាក់ទំនងតាម Telegram លេខ {{ $telegramNumber ?? '0717221349' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-row">
        <div class="signature-box">
            <div>ហត្ថលេខាអ្នកខ្ចី</div>
            <div class="signature-name">{{ $customer->name ?? '-' }}</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <div>ហត្ថលេខាអ្នកផ្ដល់កម្ចី</div>
            <div class="signature-name">{{ $createdBy }}</div>
        </div>
    </div>

    <div class="notice">
        <span class="title kh-moul">ចំណាំ:</span>
         ខ្ញុំទទួលខុសត្រូវចំពោះការបង់ប្រាក់ឲ្យបានទៀងទាត់ ក្នុងករណីយឺតយាវ​ ខ្ញុំយល់ព្រមឲ្យហាង គ្នាយើង ផាកពិន័យ ២០០០រៀលក្នុងមួយថ្ងៃ។
          ខ្ញុំយល់ព្រមទទួលខុសត្រូវចំពេាះមុខច្បាប់ក្នុងករណីគេចវេសមិនព្រមបង់ប្រាក់ឲ្យហាង គ្នាយើង។  <br>
        <div class="warranty-line"><span class="red">សម្រាប់ការធាន១ឆ្នាំ</span>គឺធានា ១ខែដំបូងដូដើមថ្មី និង១១ខែបន្ទាប់ជួសជុល សរុប១២ខែ <span class="red">មិនធានាលើការធ្លាក់បាក់បែកចូលទឹកគៀបកិនឡើយ។</span></div>
    </div>

    <div class="payment-area">
        <div class="payment-card">
            <div class="caption">ស្កេន ដើម្បីបង់ប្រាក់</div>
            @if(! empty($paymentQr))
                <img src="{{ $paymentQr }}" class="qr-large" alt="QR payment">
            @elseif(file_exists(public_path('img/qr-code-aba.png')))
                <img src="{{ asset('img/qr-code-aba.png') }}" class="qr-large" alt="QR payment">
            @else
                <div class="muted">Payment QR not set</div>
            @endif
        </div>
        <div class="payment-card">
            <div class="caption orange">លេខវេរលុយតែមួយគត់</div>
            <div class="transfer-number">070923681</div>
            @if(file_exists(public_path('img/payment-method.png')))
                <img src="{{ asset('img/payment-method.png') }}" style="max-width:33mm;max-height:12mm;" alt="Payment methods">
            @endif
            <div class="telegram-qr-block">
                <span class="caption orange">សូមស្កេន QR Telegram ខាងក្រោម</span><br>
                @if(! empty($telegramQr))
                    <img src="{{ $telegramQr }}" class="qr-small" alt="Telegram QR">
                @elseif(file_exists(public_path('img/telegram-qr.png')))
                    <img src="{{ asset('img/telegram-qr.png') }}" class="qr-small" alt="Telegram QR">
                @else
                    <span class="muted">Telegram QR not set</span>
                @endif
            </div>
        </div>
    </div>

    <div class="printed-date">Printed date&nbsp;&nbsp;&nbsp;&nbsp;{{ $printedAt }}</div>
    
</div>

<script>
    function buildLoanImageSvg(target) {
        var rect = target.getBoundingClientRect();
        var width = Math.ceil(rect.width || target.offsetWidth || 794);
        var a4Height = Math.round(width * 297 / 210);
        var serializer = new XMLSerializer();
        var clone = target.cloneNode(true);
        clone.style.width = width + 'px';
        clone.style.height = 'auto';
        clone.style.minHeight = a4Height + 'px';
        clone.style.margin = '0';
        clone.style.boxSizing = 'border-box';
        clone.style.overflow = 'visible';
        clone.style.background = '#fff';

        var measurer = document.createElement('div');
        measurer.style.position = 'absolute';
        measurer.style.left = '-100000px';
        measurer.style.top = '0';
        measurer.style.width = width + 'px';
        measurer.style.background = '#fff';
        measurer.style.overflow = 'visible';
        measurer.appendChild(clone);
        document.body.appendChild(measurer);

        var cloneRect = clone.getBoundingClientRect();
        var height = Math.ceil(Math.max(
            a4Height,
            rect.height || 0,
            target.offsetHeight || 0,
            target.scrollHeight || 0,
            cloneRect.height || 0,
            clone.offsetHeight || 0,
            clone.scrollHeight || 0,
            measurer.offsetHeight || 0,
            measurer.scrollHeight || 0
        ));
        clone.style.minHeight = height + 'px';

        var styles = Array.from(document.querySelectorAll('style'))
            .map(function(styleTag) { return styleTag.textContent || ''; })
            .join('\n');
        var html = serializer.serializeToString(clone);
        if (measurer.parentNode) {
            measurer.parentNode.removeChild(measurer);
        }

        var svg = ''
            + '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">'
            + '<foreignObject width="100%" height="100%">'
            + '<div xmlns="http://www.w3.org/1999/xhtml" style="width:' + width + 'px;min-height:' + height + 'px;background:#fff;overflow:visible;">'
            + '<style>' + styles + '</style>'
            + html
            + '</div>'
            + '</foreignObject>'
            + '</svg>';

        return {
            width: width,
            height: height,
            url: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg)
        };
    }

    async function buildLoanPrintImageBlob() {
        var target = document.querySelector('.page');
        if (!target) {
            throw new Error('Loan print page was not found.');
        }

        await waitForLoanPrintAssets();
        var payload = buildLoanImageSvg(target);
        var image = new Image();
        image.decoding = 'async';

        await new Promise(function(resolve, reject) {
            image.onload = resolve;
            image.onerror = reject;
            image.src = payload.url;
        });

        var canvas = document.createElement('canvas');
        canvas.width = payload.width * 2;
        canvas.height = payload.height * 2;
        var context = canvas.getContext('2d');
        context.scale(2, 2);
        context.drawImage(image, 0, 0, payload.width, payload.height);

        var blob = await new Promise(function(resolve) {
            canvas.toBlob(resolve, 'image/png');
        });

        if (!blob) {
            throw new Error('Unable to create image');
        }

        return blob;
    }

    window.loanManagementBuildLoanPrintImageBlob = buildLoanPrintImageBlob;

    async function copyLoanAsImage() {
        var button = document.getElementById('copy_loan_as_image_button');
        var status = document.getElementById('copy_loan_as_image_status');
        if (!button || !status) {
            return;
        }

        button.disabled = true;
        status.textContent = 'Preparing image...';

        try {
            var blob = await buildLoanPrintImageBlob();

            if (navigator.clipboard && window.ClipboardItem) {
                await navigator.clipboard.write([
                    new ClipboardItem({
                        'image/png': blob
                    })
                ]);
                status.textContent = 'Copied. Paste it in Telegram.';
            } else {
                var fallbackLink = document.createElement('a');
                fallbackLink.href = URL.createObjectURL(blob);
                fallbackLink.download = 'loan-' + {{ \Illuminate\Support\Js::from((string) ($loanRow->loan_number ?? $loanRow->id)) }} + '.png';
                fallbackLink.click();
                status.textContent = 'Image downloaded. Send it in Telegram.';
                setTimeout(function() {
                    URL.revokeObjectURL(fallbackLink.href);
                }, 1000);
            }
        } catch (error) {
            status.textContent = 'Copy failed. Try Print or screenshot.';
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('copy_loan_as_image_button')?.addEventListener('click', function () {
        copyLoanAsImage();
    });

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
