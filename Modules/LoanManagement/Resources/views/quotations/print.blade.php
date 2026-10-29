<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>Quotation #{{ $quotation->quotation_no }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700;800&display=swap">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Kantumruy Pro', Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            font-size: 9pt;
            line-height: 1.35;
            background: #e5e7eb;
            overflow-wrap: anywhere;
        }
        .print-container {
            width: 100%;
            max-width: 210mm;
            margin: 6mm auto;
            padding: 12mm;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            gap: 6mm;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
            break-inside: avoid;
        }
        .header > div { min-width: 0; flex: 1; }
        .header h1 {
            margin: 0;
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0 0 0;
            color: #64748b;
            font-size: 8pt;
        }
        .qtn-title-block {
            text-align: right;
        }
        .qtn-title-block h2 {
            margin: 0;
            font-size: 12.5pt;
            color: #2563eb;
            font-weight: 800;
        }
        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4mm;
            gap: 4mm;
            break-inside: avoid;
        }
        .info-card {
            flex: 1;
            min-width: 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 2.5mm 3mm;
        }
        .info-card h4 {
            margin: 0 0 2mm 0;
            font-size: 8.5pt;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .info-card table {
            width: 100%;
            font-size: 7.5pt;
            table-layout: fixed;
        }
        .info-card table td {
            padding: 2px 0;
            vertical-align: top;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 8pt;
            table-layout: fixed;
        }
        table.data-table th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 0.7mm 1.5mm;
            text-align: left;
            font-weight: 700;
        }
        table.data-table td {
            border: 1px solid #e2e8f0;
            padding: 0.7mm 1.5mm;
            vertical-align: top;
        }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        .section-heading { break-after: avoid; page-break-after: avoid; }
        .summary-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 4mm;
            break-inside: avoid;
        }
        .summary-table {
            width: 112mm;
            max-width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .summary-table td {
            padding: 1mm 1.5mm;
            vertical-align: top;
        }
        .summary-table td:last-child { width: 30%; white-space: nowrap; }
        .summary-table tr.total-row {
            font-weight: 800;
            font-size: 9pt;
            background: #f1f5f9;
            border-top: 1px solid #0f172a;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 4mm;
            padding-top: 2mm;
            gap: 10mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .sig-block {
            width: 65mm;
            max-width: 45%;
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-top: 9mm;
            padding-top: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .print-container { max-width: none; margin: 0; padding: 0; }
            p { orphans: 3; widows: 3; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f1f5f9;padding:12px;text-align:center;border-bottom:1px solid #cbd5e1;margin-bottom:20px;">
    <button type="button" onclick="document.fonts.ready.then(function () { window.print(); })" style="background:#2563eb;color:#fff;border:none;padding:8px 24px;border-radius:6px;font-weight:700;cursor:pointer;font-size:14px;">
        Print Quotation / បោះពុម្ពសម្រង់តម្លៃ
    </button>
    <a href="{{ route('loan-management.quotations.show', $quotation->id) }}" style="display:inline-block;margin-left:12px;color:#334155;">Back to Quotation / ត្រឡប់ទៅសម្រង់តម្លៃ</a>
</div>

<div class="print-container">
    <div class="header">
        <div>
            <h1>{{ session('business.name', 'LOAN MANAGEMENT SYSTEM') }}</h1>
            <p>{{ $quotation->location_name_snapshot ?: ($quotation->location->name ?? 'Head Office') }}</p>
            <p>Phone: {{ $quotation->location->phone ?? '-' }}</p>
        </div>
        <div class="qtn-title-block">
            <h2>INSTALLMENT QUOTATION</h2>
            <p><strong>សម្រង់តម្លៃបង់រំលស់</strong></p>
            <p>No: <strong>{{ $quotation->quotation_no }}</strong></p>
            <p>Date: {{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : date('d/m/Y') }}</p>
            @if($quotation->valid_until)
                <p>Valid Until: {{ $quotation->valid_until->format('d/m/Y') }}</p>
            @endif
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <h4>CUSTOMER INFORMATION / អតិថិជន</h4>
            <table>
                <tr>
                    <td style="width:65px;color:#64748b;">Name:</td>
                    <td><strong>{{ $quotation->customer_name_snapshot }}</strong></td>
                </tr>
                <tr>
                    <td style="color:#64748b;">Phone:</td>
                    <td>{{ $quotation->customer_phone_snapshot }}</td>
                </tr>
                <tr>
                    <td style="color:#64748b;">Address:</td>
                    <td>{{ $quotation->customer_address_snapshot ?: '-' }}</td>
                </tr>
            </table>
        </div>

        <div class="info-card">
            <h4>INSTALLMENT TERMS / លក្ខខណ្ឌកម្ចី</h4>
            <table>
                <tr>
                    <td style="width:90px;color:#64748b;">Interest Rate:</td>
                    <td><strong>{{ number_format($quotation->interest_rate, 2) }}% / month</strong></td>
                </tr>
                <tr>
                    <td style="color:#64748b;">Interest Type:</td>
                    <td>{{ $quotation->interest_type === 'flat_rate' ? 'Flat Rate' : 'Declining Balance' }}</td>
                </tr>
                <tr>
                    <td style="color:#64748b;">Term Duration:</td>
                    <td><strong>{{ $quotation->duration_months }} Months</strong> ({{ ucfirst($quotation->payment_frequency) }})</td>
                </tr>
                <tr>
                    <td style="color:#64748b;">1st Due Date:</td>
                    <td>{{ $quotation->first_due_date ? $quotation->first_due_date->format('d/m/Y') : '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- PRODUCTS TABLE -->
    <h4 class="section-heading" style="margin:0 0 2mm 0;font-size:9pt;color:#0f172a;text-transform:uppercase;">1. Products / Services</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Product Description</th>
                <th style="width:60px;text-align:center;">Qty</th>
                <th style="width:100px;text-align:right;">Unit Price ($)</th>
                <th style="width:110px;text-align:right;">Total ($)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quotation->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name_snapshot }}</strong>
                        @if($item->sku_snapshot) ({{ $item->sku_snapshot }}) @endif
                    </td>
                    <td style="text-align:center;">{{ number_format($item->quantity) }}</td>
                    <td style="text-align:right;">${{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align:right;"><strong>${{ number_format($item->line_total, 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td>1</td>
                    <td>Personal / Consumer Loan Financing</td>
                    <td style="text-align:center;">1</td>
                    <td style="text-align:right;">${{ number_format($quotation->total_amount, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($quotation->total_amount, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SUMMARY -->
    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td>Subtotal Value:</td>
                <td style="text-align:right;">${{ number_format($quotation->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Down Payment (បង់មុន):</td>
                <td style="text-align:right;">-${{ number_format($quotation->down_payment, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Financed Principal (ប្រាក់កម្ចីដើម):</strong></td>
                <td style="text-align:right;"><strong>${{ number_format($quotation->loan_amount, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Total Interest (ការប្រាក់សរុប):</td>
                <td style="text-align:right;">+${{ number_format($quotation->total_interest, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Payable (ទឹកប្រាក់ត្រូវបង់សរុប):</td>
                <td style="text-align:right;color:#2563eb;">${{ number_format($quotation->total_payable, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Monthly Installment (បង់ប្រចាំខែ):</strong></td>
                <td style="text-align:right;font-weight:800;color:#16a34a;font-size:13px;">${{ number_format($quotation->installment_amount, 2) }} / mo</td>
            </tr>
        </table>
    </div>

    <!-- SCHEDULE (FIRST 12 CYCLES) -->
    <h4 class="section-heading" style="margin:0 0 2mm 0;font-size:9pt;color:#0f172a;text-transform:uppercase;">2. Repayment Schedule Preview (កាលវិភាគបង់ប្រាក់សង្ខេប)</h4>
    <table class="data-table" style="font-size:10px;">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Due Date</th>
                <th style="text-align:right;">Principal ($)</th>
                <th style="text-align:right;">Interest ($)</th>
                <th style="text-align:right;">Installment ($)</th>
                <th style="text-align:right;">Remaining ($)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->schedules->take(12) as $s)
                <tr>
                    <td>{{ $s->installment_no }}</td>
                    <td>{{ $s->due_date ? $s->due_date->format('d/m/Y') : '-' }}</td>
                    <td style="text-align:right;">${{ number_format($s->principal_amount, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($s->interest_amount, 2) }}</td>
                    <td style="text-align:right;font-weight:700;">${{ number_format($s->schedule_amount, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($s->balance_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if($quotation->schedules->count() > 12)
        <p style="font-size:10px;color:#64748b;margin-top:-10px;">* Showing first 12 installments. Full schedule is available upon loan contract execution.</p>
    @endif

    <!-- TERMS -->
    @if($quotation->terms)
        <div style="margin-top:2mm;border-top:1px solid #e2e8f0;padding-top:2mm;font-size:8pt;color:#64748b;white-space:pre-line;">
            <strong>Terms & Conditions / លក្ខខណ្ឌកម្ចី:</strong> {{ $quotation->terms }}
        </div>
    @endif

    <!-- SIGNATURES -->
    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line">Prepared By / អ្នករៀបចំ</div>
            <p style="margin:2px 0 0 0;font-size:11px;">Credit / Sales Officer</p>
        </div>
        <div class="sig-block">
            <div class="sig-line">Customer Acceptance / អតិថិជនយល់ព្រម</div>
            <p style="margin:2px 0 0 0;font-size:11px;">{{ $quotation->customer_name_snapshot }}</p>
        </div>
    </div>
</div>

<script id="quotation-print-navigation">
    window.addEventListener('afterprint', function () {
        window.location.replace(@json(route('loan-management.quotations.show', $quotation->id)));
    });
</script>
</body>
</html>
