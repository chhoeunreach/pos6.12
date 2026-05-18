@once
<style>
@font-face {
    font-family: 'Roboto';
    src: url('{{ asset("fonts/english/Roboto-Regular.ttf") }}') format('truetype');
}
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

body {
    margin: 0;
}

/* Card container */
.warranty-card {
    position: relative;
    width: 85.6mm;
    height: 53.98mm;
    font-family: Arial, sans-serif;
    box-sizing: border-box;
    transform: rotate(180deg);
    transform-origin: center center;
    page-break-after: always;
    break-after: page;
}

.warranty-card:last-child {
    page-break-after: auto;
    break-after: auto;
}

/* ======================
   ROW DEFINITIONS
   ====================== */

/* Field 1 row */
.row-1 {
    position: absolute;
    width: 25mm;
    height: 5mm;
    font-size: 9px;
    line-height: 5mm;
    white-space: nowrap;
}

/* Field 2 independent row */
.row-1b {
    position: absolute;
    width: 25mm;
    height: 5mm;
    font-size: 9px;
    line-height: 5mm;
    white-space: nowrap;
}

/* Other rows */
.row-2 {
    position: absolute;
    width: 62mm;
    height: 5mm;
    font-size: 9px;
    line-height: 5mm;
    white-space: nowrap;
}

.row-3 {
    position: absolute;
    width: 66mm;
    height: 5mm;
    font-size: 9px;
    line-height: 5mm;
    white-space: nowrap;
}

.row-4 {
    position: absolute;
    width: 20mm;
    height: 5mm;
    font-size: 9px;
    line-height: 5mm;
    white-space: nowrap;
}

/* ======================
   FIELD POSITIONS
   ====================== */

.field-1 {
    left: 14mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}

.field-2 {
    right: 3mm;
    top: 8.8mm;
    font-family: 'NewTimes', serif;
    font-weight: bold;
    font-size: 12px;
    padding-left: 2mm;
}

.field-3 {
    right: 3mm;
    top: 15.5mm;
    font-weight: bold;
    font-family: 'NewTimes', serif;
    font-size: 12px;
    padding-left: 2mm;
}

.field-4 {
    right: 3mm;
    top: 22mm;
    font-family: 'Khmer OS Battambang', serif;
    font-weight: bold;
    font-size: 9px;
    padding-left: 2mm;
}

.field-5 {
    right: 3mm;
    top: 28mm;
    font-weight: bold;
    font-family: 'NewTimes', serif;
    font-size: 12px;
    padding-left: 0.7mm;
}

.field-6 {
    right: 3mm;
    top: 33.5mm;
    font-weight: bold;
    font-family: 'NewTimes', serif;
    font-size: 12px;
    padding-left: 0.7mm;
}

.text-left {
    text-align: left;
}
</style>
@endonce

@foreach($receipt_details->lines as $line)
<div class="warranty-card">
    <!-- <div class="row-1 field-1 text-left"> {{$line['name'] ?? ''}}</div> -->
     @php
    $text = $line['name'] ?? '';

    // lowercase first
    $text = strtolower($text);

    // remove special characters
    $text = preg_replace('/[^a-z0-9 ]/i', '', $text);

    // words to remove (colors & noise)
    $remove = ['gold','black','white','space','spac','color','colour','edition'];

    $words = explode(' ', $text);
    $short = '';

    foreach ($words as $word) {
        if ($word === '' || in_array($word, $remove)) continue;

        // keep full numbers like 12, 128gb
        if (preg_match('/[0-9]/', $word)) {
            $short .= preg_replace('/[^0-9]/', '', $word);
        } 
        // keep 2-letter codes like LA, SC
        elseif (strlen($word) <= 2) {
            $short .= $word;
        } 
        else {
            // take first letter of long words
            $short .= substr($word, 0, 1);
        }
    }

    $short = strtoupper($short);
@endphp

<div class="row-1 field-1 text-left">
    {{ $short }}
</div>
    <!-- <div class="row-1b field-2 text-left"> {{ $receipt_details->contact ?? '' }}</div> -->
    @php
        $topRightText = $receipt_details->staff_note ?? '';
        if (trim($topRightText) === '') {
            $topRightText = $receipt_details->additional_notes ?? '';
        }
    @endphp
    <div class="row-1b field-2 text-left"> {{ $topRightText }}</div>
    <!-- <div class="row-2 field-3 text-left"> {{ $line['lot_number'] ?? '' }} </div> -->
     @php
        $middleText = $line['sell_line_note_raw'] ?? strip_tags($line['sell_line_note'] ?? '');

        if (trim($middleText) === '') {
            $middleText = $line['lot_number_raw'] ?? $line['lot_number'] ?? '';
        }
     @endphp
     <div class="row-2 field-3 text-left">{!! nl2br(e($middleText)) !!}</div>
    <!-- <div class="row-3 field-4 text-left"> {{ $line['name'] ?? '' }}  {{$line['variation']}}</div> -->
     <div class="row-3 field-4 text-left">{!! '&#x1798;&#x17C9;&#x17B6;&#x179F;&#x17CA;&#x17B8;&#x1793;&#x1790;&#x17D2;&#x1798;&#x200B;&#x200B;&#x20;&#x1793;&#x17B7;&#x1784;&#x1798;&#x17B7;&#x1793;&#x1792;&#x17B6;&#x1793;&#x17B6;&#x1781;&#x17BB;&#x179F;&#x1785;&#x17C6;&#x1796;&#x17C4;&#x17C7;&#x1791;&#x17C6;&#x179A;&#x1784;&#x17CB;&#x178A;&#x17BE;&#x1798;&#x1793;&#x17B9;&#x1784;&#x1798;&#x17B7;&#x1793;&#x1792;&#x17B6;&#x1793;&#x17B6;&#x1780;&#x17B6;&#x179A;&#x1785;&#x17BC;&#x179B;&#x1791;&#x17B9;&#x1780;' !!}</div>
     <!-- <div class="row-3 field-4 text-left">
        {{ mb_strlen(($line['name'] ?? '') . ' ' . ($line['variation'] ?? '')) > 50
            ? mb_substr(($line['name'] ?? '') . ' ' . ($line['variation'] ?? ''), 0, 50)
            : (($line['name'] ?? '') . ' ' . ($line['variation'] ?? ''))
        }}
    </div> -->

	<div class="row-4 field-5">
		{{ $receipt_details->invoice_date ? \Carbon\Carbon::parse($receipt_details->invoice_date)->format('  d  /  m  /  Y') : '' }}
	</div>
	<div class="row-4 field-6">
		{{ $receipt_details->invoice_date ? \Carbon\Carbon::parse($receipt_details->invoice_date)->addYear()->format('  d  /  m  /  Y') : '' }}
	</div>
</div>
@endforeach
