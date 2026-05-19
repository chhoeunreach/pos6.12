<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Loan {{ $loanRow->loan_number ?? $loanRow->id }}</title>
    <style>
        @font-face { font-family: 'Roboto'; src: url('{{ asset("fonts/english/Roboto-Regular.ttf") }}') format('truetype'); }
        @font-face { font-family: 'RobotoBold'; src: url('{{ asset("fonts/english/Roboto-Bold.ttf") }}') format('truetype'); }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('{{ asset("fonts/khmer/Battambang-Regular.ttf") }}') format('truetype'); }

        :root {
            --orange: #ff8a00;
            --light-blue: #7f9bb1;
            --line: #222;
            --soft-line: #777;
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
        .kh-moul { font-family: 'Khmer OS Muol Light', 'Khmer OS Moul Light', 'Moul', 'Khmer OS Battambang', Arial, sans-serif; font-weight: 400; }
        .kh { font-family: 'Khmer OS Battambang', Arial, sans-serif; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bold { font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif; font-weight: 700; }
        .orange { color: var(--orange); }
        .blue-label { color: var(--light-blue); font-family: 'Khmer OS Battambang', Arial, sans-serif; font-size: 12px; font-weight: 700; }
        .red { color: red; }
        .muted { color: #666; }

        .header {
            position: relative;
            min-height: 24mm;
            padding-right: 6mm;
        }
        .brand-row {
            display: grid;
            grid-template-columns: 32mm 1fr 32mm;
            position: relative;
            align-items: center;
            min-height: 21mm;
        }
        .logo-wrap {
            width: 28mm;
            height: 21mm;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo {
            max-width: 28mm;
            max-height: 21mm;
            object-fit: contain;
        }
        .brand-title {
            color: var(--orange);
            font-family: 'Khmer OS Muol Light', 'Khmer OS Moul Light', 'Moul', 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 400;
            font-size: 22px;
            line-height: 1.1;
            text-align: center;
            white-space: nowrap;
        }
        .brand-spacer { width: 32mm; }
        .tagline {
            margin-top: 2mm;
            padding-bottom: 0.7mm;
            border-bottom: 1.5px solid var(--orange);
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
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
            vertical-align: middle;
            border: none;
            font-size: 11px;
        }
        .info-table .label {
            width: 42%;
            color: var(--light-blue);
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
            font-size: 11.5px;
        }
        .info-table .value {
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
        }
        .address-row {
            padding: 1.5mm 0 2.5mm;
            border-bottom: 1.5px solid var(--orange);
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
            font-family: 'Khmer OS Muol Light', 'Khmer OS Moul Light', 'Moul', 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 400;
        }
        .print-table .dotted td,
        .schedule-table td {
            border-style: dotted;
        }
        .print-table .solid td,
        .print-table .solid th {
            border-style: solid;
        }
        .product-title {
            border-left: 1px solid var(--line);
            border-right: 1px solid var(--line);
            border-top: 1px solid var(--line);
            text-align: center;
            font-family: 'Khmer OS Muol Light', 'Khmer OS Moul Light', 'Moul', 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 400;
            padding: 1.3mm;
        }
        .date-bar {
            float: right;
            min-width: 45mm;
            border-left: 1px solid var(--line);
            padding-left: 8mm;
            font-family: 'RobotoBold';
        }
        .summary-row td {
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
        }
        .summary-label {
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            text-align: left !important;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 1mm 1mm;
            font-size: 9.4px;
        }
        .schedule-table th {
            font-size: 10px;
        }
        .contact-line {
            color: blue;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
            font-size: 14px;
            text-align: right;
        }
        .warranty-line {
            font-family: 'Khmer OS Muol Light', 'Khmer OS Moul Light', 'Moul', 'Khmer OS Battambang', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.7;
        }
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 4mm;
            min-height: 23mm;
        }
        .signature-box {
            text-align: center;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
        }
        .signature-name {
            margin-top: 9mm;
            font-family: 'RobotoBold', 'Khmer OS Battambang', Arial, sans-serif;
        }
        .signature-line {
            width: 43mm;
            border-top: 1px solid #000;
            margin: 4mm auto 0;
        }
        .notice {
            border-top: 1.5px solid var(--orange);
            border-bottom: 1.5px solid var(--orange);
            padding: 5mm 0;
            text-align: center;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.7;
        }
        .notice .title {
            color: red;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
            font-size: 13px;
        }
        .payment-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12mm;
            padding-top: 5mm;
            align-items: end;
        }
        .payment-card {
            min-height: 41mm;
            text-align: center;
        }
        .payment-card .caption {
            color: #1244d8;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
            font-size: 10px;
            margin-bottom: 2mm;
        }
        .qr-large { max-width: 38mm; max-height: 38mm; }
        .qr-small { max-width: 30mm; max-height: 30mm; }
        .transfer-number {
            margin: 5mm 0 3mm;
            font-size: 13px;
        }
        .printed-date {
            margin-top: 3mm;
            color: #999;
            font-size: 9px;
            text-align: center;
        }
        .footer-bold {
            margin-top: 4mm;
            text-align: center;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            font-weight: 700;
            font-size: 10px;
        }
        .nowrap { white-space: nowrap; }

        @page { size: A4 portrait; margin: 5mm; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 6mm 10mm 5mm;
            }
            .print-table th,
            .print-table td { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
@php
    $productTotal = $products->sum(fn ($p) => (float) ($p->subtotal ?? ((float) ($p->quantity ?? 1) * (float) ($p->unit_price_inc_tax ?? 0))));
    $schedulePrincipalTotal = $installments->sum(fn ($row) => (float) ($row->installment_value ?? 0));
    $downPayment = (float) ($loanRow->down_payment ?? 0);
    $loanAmount = (float) ($loanRow->principal_amount ?? max(0, $productTotal - $downPayment));
    if ($productTotal <= 0 && ($loanAmount > 0 || $downPayment > 0)) {
        $productTotal = $loanAmount + $downPayment;
    }
    $paidAmount = (float) ($loanRow->paid_amount ?? $downPayment);
    $balanceAmount = $productTotal > 0
        ? max(0, $productTotal - $downPayment)
        : max(0, $loanAmount - $paidAmount);
    if ($balanceAmount <= 0 && $schedulePrincipalTotal > 0) {
        $balanceAmount = $schedulePrincipalTotal;
    }
    $currency = $loanRow->currency ?? 'USD';
    $loanDate = ! empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('m-d-Y') : '-';
    $loanDateTitle = ! empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('d-M-Y') : \Carbon\Carbon::now()->format('d-M-Y');
    $firstDueDate = $installments->first()?->installmentdate;
    $lastDueDate = $installments->last()?->installmentdate;
    $createdBy = $createdByName ?? ($loanRow->created_by_name_snapshot ?? '-');
    $duration = (int) ($loanRow->duration_months ?? max(1, $installments->count()));
    $interestRate = (float) ($loanRow->interest_rate ?? 0);
    $downPercent = $productTotal > 0 ? ($downPayment / max($productTotal, 1) * 100) : 0;
    $paymentsBySchedule = $payments->groupBy(fn ($payment) => $payment->_print_schedule_id ?? $payment->schedule_id ?? null);
    $printedAt = \Carbon\Carbon::now()->format('d-M-Y H:i:s');
@endphp

<div class="no-print">
    <button type="button" onclick="window.print()">Print Loan</button>
    <button type="button" onclick="window.close()">Close</button>
</div>

<div class="page">
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
            លក់ដុំ-រាយ និងសេវាកម្ម | សម្រាប់ព័ត៌មានបង់ប្រាក់ Telegram លេខ 0717221349
        </div>
    </div>

    <div class="info-grid">
        <table class="info-table">
            <tr><td class="label">លេខកិច្ចសន្យា</td><td class="value red">{{ $loanRow->loan_number ?? $loanRow->id }}</td></tr>
            <tr><td class="label">កាលបរិច្ឆេទខ្ចីប្រាក់</td><td class="value">{{ $loanDate }}</td></tr>
            <tr><td class="label">កាលបរិច្ឆេទបញ្ចប់</td><td class="value">{{ $lastDueDate ? \Carbon\Carbon::parse($lastDueDate)->format('m-d-Y') : '-' }}</td></tr>
        </table>
        <table class="info-table">
            <tr><td class="label">ឈ្មោះអតិថិជន</td><td class="value">{{ $customer->name ?? '-' }}</td></tr>
            <tr><td class="label">លេខទូរស័ព្ទ</td><td class="value">{{ $customer->mobile ?? '-' }}</td></tr>
            <tr><td class="label">លេខសម្គាល់</td><td class="value">{{ $customer->custom_field1 ?? '-' }}</td></tr>
        </table>
        <table class="info-table">
            <tr><td class="label">កាលបរិច្ឆេទទី១</td><td class="value">{{ $firstDueDate ? \Carbon\Carbon::parse($firstDueDate)->format('m-d-Y') : '-' }}</td></tr>
            <tr><td class="label">អ្នករួមខ្ចី</td><td class="value">{{ $customer->co_borrower ?? '-' }}</td></tr>
            <tr><td class="label">លេខអ្នករួមខ្ចី</td><td class="value">{{ $customer->co_borrower_phone ?? '-' }}</td></tr>
        </table>
    </div>
    <div class="address-row">
        <span class="blue-label">អាសយដ្ឋាន</span>
        <span class="bold">{{ $customer->address_line_1 ?? '-' }}</span>
    </div>

    <div class="product-title">
        វិក្កយបត្រកម្ចី
        <span class="date-bar">{{ $loanDateTitle }}</span>
    </div>
    <table class="print-table product-table">
        <thead>
            <tr>
                <th style="width:7mm;">ល.រ</th>
                <th style="width:25mm;">លេខទំនិញ</th>
                <th>ឈ្មោះផលិតផល</th>
                <th style="width:12mm;">ចំនួន</th>
                <th style="width:26mm;" colspan="2">តម្លៃ</th>
                <th style="width:28mm;" colspan="2">សរុប</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $i => $p)
                @php
                    $qty = (float) ($p->quantity ?? 1);
                    $price = (float) ($p->unit_price_inc_tax ?? 0);
                    $subtotal = (float) ($p->subtotal ?? ($qty * $price));
                    $imei = trim((string) ($p->imei ?? ''));
                    $serial = trim((string) ($p->serial ?? ''));
                    $showImei = $imei !== '' && $imei !== '-';
                    $showSerial = $serial !== '' && $serial !== '-' && strcasecmp($serial, $imei) !== 0;
                @endphp
                <tr>
                    <td class="bold">{{ $i + 1 }}</td>
                    <td class="bold">{{ $p->product_sku ?? '-' }}</td>
                    <td class="text-left bold">
                        {{ $p->product_name ?? '-' }}
                        @if($showImei) / IMEI: {{ $imei }} @endif
                        @if($showSerial) / Serial: {{ $serial }} @endif
                    </td>
                    <td class="bold">{{ number_format($qty, 0) }}</td>
                    <td class="bold">$</td>
                    <td class="text-right bold">{{ number_format($price, 2) }}</td>
                    <td class="bold">$</td>
                    <td class="text-right bold">{{ number_format($subtotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No products</td></tr>
            @endforelse
            <tr class="summary-row">
                <td colspan="4" rowspan="4" class="summary-label">
                    រយៈពេលបង់(ខែ) <span style="margin-left:30mm;">{{ $duration }}</span><br>
                    ភាគរយបង់មុន <span style="margin-left:29mm;">{{ number_format($downPercent, 2) }}%</span><br>
                    អត្រាការប្រាក់ <span style="margin-left:30mm;" class="red">{{ number_format($interestRate, 2) }}%</span>
                </td>
                <td colspan="2" class="summary-label">តម្លៃសរុប</td>
                <td>$</td>
                <td class="text-right">{{ number_format($productTotal, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="2" class="summary-label">ប្រាក់ចូលរួមមុន</td>
                <td>$</td>
                <td class="text-right">{{ number_format($downPayment, 2) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="2" class="summary-label">ប្រាក់នៅខ្វះ</td>
                <td colspan="2" class="red text-center">${{ number_format($balanceAmount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="print-table schedule-table" style="margin-top:0;">
        <thead>
            <tr>
                <th style="width:7mm;">ល.រ</th>
                <th style="width:24mm;">ថ្ងៃ-ខែ-ឆ្នាំ</th>
                <th style="width:21mm;">ប្រាក់ដើម</th>
                <th style="width:21mm;">ការប្រាក់</th>
                <th style="width:25mm;" class="orange">ទឹកប្រាក់ត្រូវបង់</th>
                <th style="width:29mm;">កាលបរិច្ឆេទ</th>
                <th style="width:29mm;">បង់ប្រាក់</th>
                <th style="width:16mm;">សរុប</th>
                <th>ចំណាំ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($installments as $row)
                @php
                    $rowTotal = (float) ($row->amount_due ?? 0);
                    if ($rowTotal <= 0) {
                        $rowTotal = round((float) $row->installment_value + (float) $row->benefit_value, 2);
                    }
                    $rowPayments = $paymentsBySchedule->get($row->id, collect());
                    $paid = (float) ($row->paid_value ?? $rowPayments->sum(fn ($p) => (float) ($p->total_paid_base ?? $p->amount ?? 0)));
                    $paymentDates = $rowPayments
                        ->map(fn ($p) => $p->paid_date ?? $p->paid_at ?? null)
                        ->filter()
                        ->map(fn ($date) => \Carbon\Carbon::parse($date)->format('d-m-Y'))
                        ->unique()
                        ->values();
                    $paymentLines = $rowPayments
                        ->reduce(function ($carry, $p) {
                            $amount = (float) ($p->_print_amount ?? $p->total_paid_base ?? $p->amount ?? 0);
                            if ($amount <= 0) {
                                return $carry;
                            }

                            $method = trim((string) ($p->payment_method_snapshot ?? $p->method ?? $p->channel ?? ''));
                            if ($method === '') {
                                $method = 'Payment';
                            }

                            $carry[$method] = ($carry[$method] ?? 0) + $amount;

                            return $carry;
                        }, collect())
                        ->map(fn ($amount, $method) => e($method).'='.number_format($amount, 2))
                        ->values();
                    if ($paymentLines->isEmpty() && $paid > 0) {
                        $paymentLines = collect([number_format($paid, 2)]);
                    }
                    if ($paymentDates->isEmpty() && ! empty($row->paid_at)) {
                        $paymentDates = collect([\Carbon\Carbon::parse($row->paid_at)->format('d-m-Y')]);
                    }
                @endphp
                <tr>
                    <td class="bold">{{ $row->installment_number }}</td>
                    <td class="bold nowrap">{{ $row->installmentdate ? \Carbon\Carbon::parse($row->installmentdate)->format('d-m-Y') : '-' }}</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->installment_value, 2) }}</td>
                    <td class="text-right bold">$ {{ number_format((float) $row->benefit_value, 2) }}</td>
                    <td class="text-right bold">$ {{ number_format($rowTotal, 2) }}</td>
                    <td class="bold nowrap">{!! $paymentDates->implode('<br>') !!}</td>
                    <td class="text-right">{!! $paymentLines->implode(' ') !!}</td>
                    <td class="text-right">{{ $paid > 0 ? '$ '.number_format($paid, 2) : '' }}</td>
                    <td>{{ ucfirst($row->status ?? '') }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No schedule</td></tr>
            @endforelse
            <tr class="solid">
                <td colspan="2"></td>
                <td class="text-right bold">$ {{ number_format($installments->sum(fn ($row) => (float) $row->installment_value), 2) }}</td>
                <td colspan="6" class="contact-line">សម្រាប់បង់លុយទំនាក់ទំនងតាម Telegram លេខ 0717221349</td>
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
            <div style="margin-top:5mm;">
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
    window.addEventListener('load', function () {
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.print();
        }
    });
</script>
</body>
</html>
