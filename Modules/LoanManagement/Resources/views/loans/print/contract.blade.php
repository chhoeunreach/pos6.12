<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>កិច្ចសន្យាឥណទាន #{{ $loanRow->loan_number ?? $loanRow->id }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700;800&family=Moul&display=swap">
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Kantumruy Pro', -apple-system, sans-serif;
            font-size: 11.5px;
            line-height: 1.6;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
        }
        .page-container {
            width: 100%;
            max-width: 820px;
            margin: 20px auto;
            background: #ffffff;
            padding: 40px 45px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            box-sizing: border-box;
        }
        .national-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .national-header h2 {
            font-family: 'Moul', serif;
            font-size: 15px;
            color: #0f172a;
            margin: 0 0 4px 0;
            font-weight: normal;
        }
        .national-header h3 {
            font-family: 'Moul', serif;
            font-size: 13px;
            color: #0f172a;
            margin: 0;
            font-weight: normal;
        }
        .contract-title {
            text-align: center;
            margin: 20px 0 25px 0;
        }
        .contract-title h1 {
            font-family: 'Moul', serif;
            font-size: 16px;
            color: #1e3a8a;
            margin: 0 0 4px 0;
            font-weight: normal;
        }
        .contract-title p {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .contract-ref {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .party-block {
            margin-bottom: 14px;
            text-align: justify;
        }
        .party-title {
            font-weight: 700;
            color: #0f172a;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .article {
            margin-bottom: 14px;
            text-align: justify;
        }
        .article-title {
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 4px;
        }
        table.contract-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        table.contract-table th, table.contract-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
        }
        table.contract-table th {
            background: #f8fafc;
            font-weight: 700;
            text-align: left;
        }
        .signatures-area {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .sig-box {
            text-align: center;
        }
        .sig-title {
            font-weight: 700;
            font-size: 11.5px;
            margin-bottom: 60px;
        }
        .thumbprint-circle {
            width: 55px;
            height: 70px;
            border: 1px dashed #94a3b8;
            border-radius: 50%;
            margin: 0 auto 10px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .sig-name {
            font-weight: 700;
            font-size: 11px;
            border-top: 1px dotted #94a3b8;
            padding-top: 4px;
        }
        .no-print {
            text-align: center;
            padding: 12px;
            background: #1e293b;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            margin-right: 10px;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">
        <i class="fa fa-print"></i> បោះពុម្ពកិច្ចសន្យា (Print Contract A4)
    </button>
    <button onclick="window.close()" style="background:#475569;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-weight:600;cursor:pointer;">
        បិទ (Close)
    </button>
</div>

<div class="page-container">
    <!-- NATIONAL MOTTO -->
    <div class="national-header">
        <h2>ព្រះរាជាណាចក្រកម្ពុជា</h2>
        <h3>ជាតិ សាសនា ព្រះមហាក្សត្រ</h3>
        <div style="font-size:14px;letter-spacing:4px;margin-top:2px;">--- 3 ---</div>
    </div>

    <!-- CONTRACT TITLE -->
    <div class="contract-title">
        <h1>កិច្ចសន្យាឥណទាន និងលក់បង់រំលស់</h1>
        <p>LOAN & CONSUMER INSTALLMENT FINANCING AGREEMENT</p>
    </div>

    <div class="contract-ref">
        <div>លេខកូដកិច្ចសន្យា៖ <strong>{{ $loanRow->loan_number ?? $loanRow->id }}</strong></div>
        <div>ធ្វើនៅ៖ <strong>{{ $locationName }}</strong>, ថ្ងៃទី {{ $loanRow->loan_date ? date('d/m/Y', strtotime($loanRow->loan_date)) : date('d/m/Y') }}</div>
    </div>

    <!-- PARTIES -->
    <div class="party-block">
        <div class="party-title">ភាគី “ក” (អ្នកឱ្យខ្ចី / ម្ចាស់បំណុល)៖</div>
        <strong>{{ $businessName }}</strong> តំណាងដោយ <strong>{{ $loanRow->staff_name_snapshot ?? ($loanRow->collector_name_snapshot ?? 'តំណាងស្របច្បាប់') }}</strong> ដែលមានអាសយដ្ឋានប្រតិបត្តិការនៅ <strong>{{ $locationName }}</strong>។
    </div>

    <div class="party-block">
        <div class="party-title">ភាគី “ខ” (អ្នកខ្ចី / កូនបំណុល)៖</div>
        ឈ្មោះ <strong>{{ $customer->name }}</strong> (ជាអក្សរឡាតាំង៖ <strong>{{ strtoupper($customer->latin_name) }}</strong>) ភេទ <strong>{{ $customer->gender === 'female' ? 'ស្រី' : 'ប្រុស' }}</strong> កើតថ្ងៃទី <strong>{{ $customer->dob ? date('d/m/Y', strtotime($customer->dob)) : '___/___/______' }}</strong> កាន់អត្តសញ្ញាណប័ណ្ណសញ្ជាតិខ្មែរលេខ <strong>{{ $customer->id_card }}</strong> មុខរបរ <strong>{{ $customer->occupation }}</strong> លេខទូរស័ព្ទ <strong>{{ $customer->mobile }}</strong> អាសយដ្ឋានបច្ចុប្បន្ន <strong>{{ $customer->address }}</strong>។
    </div>

    @if(!empty($guarantor->name) && $guarantor->name !== '-')
    <div class="party-block">
        <div class="party-title">ភាគី “គ” (អ្នកធានា / សហកូនបំណុល)៖</div>
        ឈ្មោះ <strong>{{ $guarantor->name }}</strong> ត្រូវជា <strong>{{ $guarantor->relationship }}</strong> របស់អ្នកខ្ចី កាន់អត្តសញ្ញាណប័ណ្ណលេខ <strong>{{ $guarantor->id_card }}</strong> លេខទូរស័ព្ទ <strong>{{ $guarantor->phone }}</strong> អាសយដ្ឋាន <strong>{{ $guarantor->address }}</strong>។
    </div>
    @endif

    <div style="margin: 12px 0; text-align: justify;">
        ភាគីទាំងអស់បានព្រមព្រៀងគ្នាយ៉ាងស្ម័គ្រចិត្ត ដោយគ្មានការបង្ខិតបង្ខំ ចុះកិច្ចសន្យាឥណទាន និងលក់បង់រំលស់តាមប្រការ និងលក្ខខណ្ឌដូចខាងក្រោម៖
    </div>

    <!-- ARTICLES -->
    <div class="article">
        <div class="article-title">ប្រការ ១៖ គោលបំណង និងទំហំទឹកប្រាក់ឥណទាន</div>
        ភាគី “ក” យល់ព្រមផ្តល់ឥណទានជូនភាគី “ខ” ហើយភាគី “ខ” យល់ព្រមទទួលឥណទានពីភាគី “ក” នូវទំហំទឹកប្រាក់ដើមចំនួន <strong>{{ number_format($loanRow->principal_amount ?? 0, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong> (ប្រាក់កក់មុនចំនួន {{ number_format($loanRow->down_payment ?? 0, 2) }} {{ $loanRow->currency ?? 'USD' }})។ ទឹកប្រាក់ឥណទាននេះត្រូវប្រើប្រាស់សម្រាប់ការទិញទំនិញ ឬការប្រើប្រាស់ស្របច្បាប់។
    </div>

    @if($products->isNotEmpty())
    <div class="article">
        <div class="article-title">ប្រការ ២៖ ព័ត៌មានទំនិញបង់រំលស់ ឬវត្ថុធានា</div>
        <table class="contract-table">
            <thead>
                <tr>
                    <th style="width:30px;">ល.រ</th>
                    <th>ឈ្មោះមុខទំនិញ / ផលិតផល</th>
                    <th style="width:80px;text-align:center;">ចំនួន</th>
                    <th style="width:130px;">លេខសម្គាល់ / IMEI / Serial</th>
                    <th style="width:110px;text-align:right;">តម្លៃសរុប</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $idx => $prod)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><strong>{{ $prod->product_name ?? ($prod->product_name_snapshot ?? '-') }}</strong></td>
                    <td style="text-align:center;">{{ number_format($prod->quantity ?? 1) }}</td>
                    <td>{{ $prod->serial_number ?? ($prod->imei ?? ($loanRow->imei_snapshot ?? '-')) }}</td>
                    <td style="text-align:right;">${{ number_format($prod->total_price ?? ($prod->unit_price ?? 0), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="article">
        <div class="article-title">ប្រការ ៣៖ អត្រាការប្រាក់ រយៈពេល និងកាលវិភាគសងប្រាក់</div>
        <ul>
            <li><strong>អត្រាការប្រាក់៖</strong> <strong>{{ number_format($loanRow->interest_rate ?? 0, 2) }}%</strong> ក្នុងមួយខែ។ ការប្រាក់សរុបមានចំនួន <strong>{{ number_format($loanRow->interest_amount ?? 0, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong>។</li>
            <li><strong>រយៈពេលបង់៖</strong> ចំនួន <strong>{{ $loanRow->duration_months ?? ($loanRow->installment_count ?? 12) }} ខែ</strong> (បង់ជារៀងរាល់ {{ ucfirst($loanRow->payment_frequency ?? 'monthly') }})។</li>
            <li><strong>ទឹកប្រាក់ត្រូវបង់ប្រចាំ kỳ៖</strong> <strong>{{ number_format($loanRow->total_amount && $loanRow->duration_months ? ($loanRow->total_amount / $loanRow->duration_months) : 0, 2) }} {{ $loanRow->currency ?? 'USD' }}</strong>។</li>
            <li><strong>កាលបរិច្ឆេទចាប់ផ្តើមបង់ដំបូង៖</strong> ថ្ងៃទី <strong>{{ $loanRow->first_due_date ? date('d/m/Y', strtotime($loanRow->first_due_date)) : '-' }}</strong>។</li>
        </ul>
    </div>

    <div class="article">
        <div class="article-title">ប្រការ ៤៖ ការយឺតយ៉ាវ និងប្រាក់ពិន័យ</div>
        ប្រសិនបើភាគី “ខ” មិនបានបង់ប្រាក់តាមកាលបរិច្ឆេទកំណត់នៃកាលវិភាគទេ ភាគី “ខ” ត្រូវទទួលរងប្រាក់ពិន័យបន្ថែមចំនួន <strong>{{ number_format($loanRow->penalty_amount ?? 0.50, 2) }} {{ $loanRow->currency ?? 'USD' }}/ថ្ងៃ</strong> ចាប់ពីថ្ងៃដែលហួសកាលកំណត់រហូតដល់ថ្ងៃបានទូទាត់រួចរាល់។ ប្រសិនបើយឺតយ៉ាវលើសពី ៣០ ថ្ងៃ ភាគី “ក” មានសិទ្ធិទាមទារប្រាក់ដើម ការប្រាក់ និងប្រាក់ពិន័យទាំងអស់មកវិញភ្លាមៗ ឬរឹបអូសទំនិញ/វត្ថុបញ្ចាំមកវិញដោយគ្មានលក្ខខណ្ឌ។
    </div>

    @if(!empty($guarantor->name) && $guarantor->name !== '-')
    <div class="article">
        <div class="article-title">ប្រការ ៥៖ ការទទួលខុសត្រូវរបស់អ្នកធានា</div>
        ភាគី “គ” (អ្នកធានា) យល់ព្រមទទួលខុសត្រូវរួមជាមួយភាគី “ខ” ក្នុងការសងបំណុលទាំងស្រុង (ប្រាក់ដើម ការប្រាក់ ប្រាក់ពិន័យ និងសោហ៊ុយតាមផ្លូវច្បាប់) ទៅឱ្យភាគី “ក” ក្នុងករណីដែលភាគី “ខ” ខកខាន ឬគេចវេះមិនព្រមសងបំណុល។
    </div>
    @endif

    <div class="article">
        <div class="article-title">ប្រការ ៦៖ វិធានការដោះស្រាយវិវាទ និងច្បាប់អនុវត្ត</div>
        កិច្ចសន្យានេះត្រូវបានគ្រប់គ្រង និងបកស្រាយស្របតាមច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា។ ក្នុងករណីមានវិវាទកើតឡើង ភាគីទាំងពីរនឹងព្យាយាមដោះស្រាយដោយការសម្របសម្រួល។ ប្រសិនបើមិនអាចដោះស្រាយបានក្នុងរយៈពេល ៣០ ថ្ងៃ វិវាទនេះនឹងត្រូវបញ្ជូនទៅតុលាការមានសមត្ថកិច្ចដើម្បីកាត់សេចក្តីតាមច្បាប់ជាធរមាន។
    </div>

    <div class="article">
        <div class="article-title">ប្រការ ៧៖ អានុភាពនៃកិច្ចសន្យា</div>
        កិច្ចសន្យានេះមានប្រសិទ្ធភាពចាប់ពីកាលបរិច្ឆេទចុះហត្ថលេខានេះតទៅ។ កិច្ចសន្យានេះត្រូវបានធ្វើឡើងជាភាសាខ្មែរចំនួន ០២ ច្បាប់ ដែលមានតម្លៃស្មើគ្នា (ភាគី “ក” រក្សាទុក ០១ ច្បាប់ និងភាគី “ខ” រក្សាទុក ០១ ច្បាប់)។
    </div>

    <!-- SIGNATURES & THUMBPRINTS -->
    <div class="signatures-area">
        <div class="sig-box">
            <div class="sig-title">តំណាងភាគី “ក” (អ្នកឱ្យខ្ចី)</div>
            <div style="height:70px;"></div>
            <div class="sig-name">{{ $loanRow->staff_name_snapshot ?? ($loanRow->collector_name_snapshot ?? $businessName) }}</div>
        </div>

        <div class="sig-box">
            <div class="sig-title">ភាគី “ខ” (អ្នកខ្ចី)</div>
            <div class="thumbprint-circle">ស្នាមមេដៃស្តាំ</div>
            <div class="sig-name">{{ $customer->name }}</div>
        </div>

        <div class="sig-box">
            <div class="sig-title">ភាគី “គ” (អ្នកធានា)</div>
            <div class="thumbprint-circle">ស្នាមមេដៃស្តាំ</div>
            <div class="sig-name">{{ (!empty($guarantor->name) && $guarantor->name !== '-') ? $guarantor->name : 'សាក្សី / Witness' }}</div>
        </div>
    </div>
</div>

</body>
</html>
