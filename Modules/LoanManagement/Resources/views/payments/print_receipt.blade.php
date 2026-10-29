<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $payment->receipt_number ?? $payment->id }} - {{ $location->name ?? 'Loan Receipt' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: 'Kantumruy Pro', -apple-system, sans-serif;
            color: #0f172a;
        }

        /* Top Action Bar (hidden when printing) */
        .no-print-bar {
            background: #1e293b;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .btn-act {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-act:hover { background: #2563eb; color: #fff; }
        .btn-act-outline {
            background: transparent;
            border: 1px solid #64748b;
            color: #e2e8f0;
        }
        .btn-act-outline:hover { background: #334155; color: #fff; }

        /* Thermal 80mm Mode */
        .thermal-wrap {
            width: 78mm;
            max-width: 100%;
            margin: 20px auto;
            background: #ffffff;
            padding: 16px 14px;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            font-size: 12px;
            line-height: 1.4;
        }
        .thermal-center { text-align: center; }
        .thermal-title {
            font-size: 15px;
            font-weight: 800;
            margin: 0 0 4px;
            text-transform: uppercase;
        }
        .thermal-sub {
            font-size: 11px;
            color: #475569;
            margin: 0 0 10px;
        }
        .thermal-sep {
            border: none;
            border-top: 1px dashed #64748b;
            margin: 10px 0;
        }
        .thermal-sep-solid {
            border: none;
            border-top: 1px solid #0f172a;
            margin: 10px 0;
        }
        .thermal-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .thermal-row span:first-child { color: #475569; }
        .thermal-row span:last-child { font-weight: 700; color: #0f172a; text-align: right; }
        .thermal-total {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 800;
            padding: 6px 0;
        }
        .thermal-qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 12px;
        }
        .thermal-qr img {
            width: 90px;
            height: 90px;
            border: 1px solid #e2e8f0;
            padding: 4px;
            border-radius: 6px;
        }
        .thermal-footer {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            margin-top: 12px;
        }

        /* A4 Mode */
        .a4-wrap {
            width: 210mm;
            min-height: 148mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border-radius: 8px;
        }
        .a4-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .a4-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .a4-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 18px;
        }
        .a4-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .a4-table th, .a4-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-size: 13px;
        }
        .a4-table th { background: #f1f5f9; font-weight: 700; text-align: left; }
        .a4-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 10px;
        }
        .a4-sign-col {
            text-align: center;
            width: 200px;
        }
        .a4-sign-line {
            border-top: 1px solid #94a3b8;
            margin-top: 60px;
            font-size: 12px;
            color: #64748b;
        }

        /* Print Media */
        @media print {
            body { background: #fff !important; }
            .no-print-bar { display: none !important; }
            .thermal-wrap {
                width: 78mm !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 4px 2px !important;
            }
            .a4-wrap {
                width: 100% !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10px !important;
            }
            @page {
                margin: 2mm;
            }
        }
    </style>
</head>
<body>

    <!-- TOP TOOLBAR -->
    <div class="no-print-bar">
        <div style="display:flex;align-items:center;gap:12px;">
            <strong style="font-size:16px;">
                <i class="fa fa-print"></i>
                {{ $format === 'a4' ? 'A4 Official Voucher' : 'POS 80mm Thermal Receipt' }}
            </strong>
            <span style="font-size:12px;color:#94a3b8;">Receipt: {{ $payment->receipt_number ?? ('PMT-'.$payment->id) }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            @if($format === 'thermal')
                <a href="{{ route('loan-management.payments.receipt', ['payment' => $payment->id, 'format' => 'a4']) }}" class="btn-act btn-act-outline">
                    <i class="fa fa-file-text-o"></i> Switch to A4 Format
                </a>
            @else
                <a href="{{ route('loan-management.payments.receipt', ['payment' => $payment->id, 'format' => 'thermal']) }}" class="btn-act btn-act-outline">
                    <i class="fa fa-ticket"></i> Switch to 80mm Thermal
                </a>
            @endif

            <button type="button" class="btn-act" onclick="window.print();">
                <i class="fa fa-print"></i> Print Now
            </button>
            <a href="{{ route('loan-management.loans.view', $loan->id ?? 0) }}" class="btn-act btn-act-outline">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    @php
        $curr = $loan->currency ?? 'USD';
        $paidAmount = (float) ($payment->amount ?? 0);
        $receiptNo = $payment->receipt_number ?? ('PMT-'.$payment->id);
        $paidDate = $payment->paid_date ? \Carbon\Carbon::parse($payment->paid_date)->format('d-M-Y H:i') : now()->format('d-M-Y H:i');
        $custName = $customer->khmer_name ?? ($customer->name ?? ($loan->customer_name_snapshot ?? 'Customer'));
        $custPhone = $customer->phone ?? ($loan->customer_phone_snapshot ?? '-');
        $loanNo = $loan->loan_number ?? ('#'.$loan->id);
        $cashier = $payment->received_by ?? auth()->user()->name ?? 'Cashier';
        $verifyUrl = route('loan-management.loans.view', $loan->id ?? 0);
    @endphp

    @if($format === 'thermal')
        <!-- ==========================================
             POS 80MM / 58MM THERMAL RECEIPT
             ========================================== -->
        <div class="thermal-wrap">
            <div class="thermal-center">
                <div class="thermal-title">{{ $location->name ?? 'LOAN INSTALLMENT' }}</div>
                <div class="thermal-sub">
                    {{ $location->location_code ? 'Branch: '.$location->location_code.' | ' : '' }}
                    Tel: {{ $location->telegram_number ?? '023 999 888' }}
                </div>
                <div style="font-weight:800;font-size:13px;letter-spacing:1px;text-transform:uppercase;">
                    បង្កាន់ដៃទទួលប្រាក់ / PAYMENT RECEIPT
                </div>
            </div>

            <hr class="thermal-sep-solid">

            <div class="thermal-row">
                <span>Receipt #:</span>
                <span>{{ $receiptNo }}</span>
            </div>
            <div class="thermal-row">
                <span>Date:</span>
                <span>{{ $paidDate }}</span>
            </div>
            <div class="thermal-row">
                <span>Cashier/Officer:</span>
                <span>{{ $cashier }}</span>
            </div>

            <hr class="thermal-sep">

            <div class="thermal-row">
                <span>Customer:</span>
                <span>{{ $custName }}</span>
            </div>
            <div class="thermal-row">
                <span>Phone:</span>
                <span>{{ $custPhone }}</span>
            </div>
            <div class="thermal-row">
                <span>Loan Account:</span>
                <span>{{ $loanNo }}</span>
            </div>

            <hr class="thermal-sep">

            @if($schedule)
            <div class="thermal-row">
                <span>Installment Term:</span>
                <span>Term #{{ $schedule->installment_no }}</span>
            </div>
            <div class="thermal-row">
                <span>Principal Paid:</span>
                <span>{{ number_format($schedule->principal_due ?? 0, 2) }} {{ $curr }}</span>
            </div>
            <div class="thermal-row">
                <span>Interest Paid:</span>
                <span>{{ number_format($schedule->interest_due ?? 0, 2) }} {{ $curr }}</span>
            </div>
            @endif

            @if(!empty($payment->penalty_amount) && (float)$payment->penalty_amount > 0)
            <div class="thermal-row">
                <span>Penalty Fee:</span>
                <span>{{ number_format($payment->penalty_amount, 2) }} {{ $curr }}</span>
            </div>
            @endif

            <hr class="thermal-sep-solid">

            <div class="thermal-total">
                <span>TOTAL PAID:</span>
                <span>{{ number_format($paidAmount, 2) }} {{ $curr }}</span>
            </div>
            <div class="thermal-row">
                <span>Payment Method:</span>
                <span>{{ strtoupper($payment->payment_method ?? 'CASH') }}</span>
            </div>

            <hr class="thermal-sep">

            <div class="thermal-row">
                <span>Remaining Balance:</span>
                <span>{{ number_format((float)($loan->balance_amount ?? 0), 2) }} {{ $curr }}</span>
            </div>
            @if($nextSchedule)
            <div class="thermal-row">
                <span>Next Due Date:</span>
                <span>{{ \Carbon\Carbon::parse($nextSchedule->due_date)->format('d-M-Y') }}</span>
            </div>
            <div class="thermal-row">
                <span>Next Due Amount:</span>
                <span>{{ number_format($nextSchedule->amount_balance, 2) }} {{ $curr }}</span>
            </div>
            @else
            <div class="thermal-row" style="color:#16a34a;">
                <span>Loan Status:</span>
                <span>FULLY COMPLETED</span>
            </div>
            @endif

            <div class="thermal-qr">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($verifyUrl) }}" alt="QR Verification">
                <span style="font-size:10px;color:#94a3b8;margin-top:4px;">Scan to Verify Account</span>
            </div>

            <div class="thermal-footer">
                <div>សូមអរគុណចំពោះការទូទាត់ទាន់ពេលវេលា!</div>
                <div>Thank you for your prompt payment!</div>
                <div style="margin-top:6px;font-size:9px;color:#cbd5e1;">Generated by NTL Loan Management</div>
            </div>
        </div>
    @else
        <!-- ==========================================
             OFFICIAL A4 / VOUCHER RECEIPT
             ========================================== -->
        <div class="a4-wrap">
            <div class="a4-header">
                <div>
                    <h2 style="margin:0;font-size:22px;color:#0f172a;">{{ $location->name ?? 'LOAN MANAGEMENT SYSTEM' }}</h2>
                    <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                        {{ $location->location_code ? 'Branch: '.$location->location_code.' | ' : '' }}
                        Official Repayment Receipt Voucher
                    </p>
                </div>
                <div style="text-align:right;">
                    <h3 style="margin:0;color:#2563eb;font-size:18px;">RECEIPT #{{ $receiptNo }}</h3>
                    <p style="margin:4px 0 0;color:#64748b;font-size:12px;">Date: {{ $paidDate }}</p>
                </div>
            </div>

            <div class="a4-grid">
                <div class="a4-box">
                    <strong style="display:block;margin-bottom:6px;color:#475569;text-transform:uppercase;font-size:11px;">Customer Information</strong>
                    <div style="font-size:15px;font-weight:800;color:#0f172a;">{{ $custName }}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:2px;"><i class="fa fa-phone"></i> {{ $custPhone }}</div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">Address: {{ $customer->address ?? '-' }}</div>
                </div>
                <div class="a4-box">
                    <strong style="display:block;margin-bottom:6px;color:#475569;text-transform:uppercase;font-size:11px;">Loan Account Summary</strong>
                    <div style="font-size:15px;font-weight:800;color:#0f172a;">Loan Account: {{ $loanNo }}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:2px;">Product: {{ $loan->product_name_snapshot ?? 'General Installment' }}</div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">Collector / Cashier: {{ $cashier }}</div>
                </div>
            </div>

            <table class="a4-table">
                <thead>
                    <tr>
                        <th>Description / បរិយាយ</th>
                        <th class="text-center" style="width:100px;">Term #</th>
                        <th class="text-right" style="width:120px;">Method</th>
                        <th class="text-right" style="width:140px;">Amount ({{ $curr }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Installment Repayment</strong>
                            @if($schedule)
                                <br><small class="text-muted">Due date: {{ \Carbon\Carbon::parse($schedule->due_date)->format('d-M-Y') }} (Principal: {{ number_format($schedule->principal_due, 2) }}, Interest: {{ number_format($schedule->interest_due, 2) }})</small>
                            @endif
                        </td>
                        <td class="text-center">{{ $schedule->installment_no ?? '-' }}</td>
                        <td class="text-right">{{ strtoupper($payment->payment_method ?? 'Cash') }}</td>
                        <td class="text-right font-bold">{{ number_format($paidAmount, 2) }}</td>
                    </tr>
                    @if(!empty($payment->penalty_amount) && (float)$payment->penalty_amount > 0)
                    <tr>
                        <td><strong>Late Payment Penalty Fee</strong></td>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right font-bold">{{ number_format($payment->penalty_amount, 2) }}</td>
                    </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align:right;font-size:14px;">TOTAL COLLECTED:</th>
                        <th style="text-align:right;font-size:16px;color:#16a34a;">${{ number_format($paidAmount, 2) }} {{ $curr }}</th>
                    </tr>
                    <tr>
                        <td colspan="4" style="background:#f8fafc;padding:12px 14px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;">
                                <span>Remaining Loan Principal Balance: <strong>${{ number_format((float)($loan->balance_amount ?? 0), 2) }} {{ $curr }}</strong></span>
                                @if($nextSchedule)
                                    <span>Next Schedule Due: <strong>{{ \Carbon\Carbon::parse($nextSchedule->due_date)->format('d-M-Y') }} (${{ number_format($nextSchedule->amount_balance, 2) }})</strong></span>
                                @else
                                    <span style="color:#16a34a;font-weight:700;">Loan Completed & Settled</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="a4-signatures">
                <div class="a4-sign-col">
                    <div style="font-weight:700;font-size:13px;">អតិថិជន / Customer</div>
                    <div class="a4-sign-line">ហត្ថលេខា ឬផ្តិតមេដៃ</div>
                </div>
                <div style="text-align:center;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={{ urlencode($verifyUrl) }}" alt="QR" style="border:1px solid #e2e8f0;padding:4px;border-radius:4px;">
                    <div style="font-size:10px;color:#94a3b8;margin-top:4px;">QR Verification</div>
                </div>
                <div class="a4-sign-col">
                    <div style="font-weight:700;font-size:13px;">បេឡាករ / Cashier</div>
                    <div class="a4-sign-line">{{ $cashier }}</div>
                </div>
            </div>
        </div>
    @endif

</body>
</html>
