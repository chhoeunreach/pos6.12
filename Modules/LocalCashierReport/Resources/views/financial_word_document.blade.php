@php echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'; @endphp
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:pPr><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr><w:t>{!! $xml($businessName) !!}</w:t></w:r></w:p>
<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>{!! $xml($t('financial_dashboard')) !!}</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:sz w:val="15"/><w:szCs w:val="15"/><w:color w:val="64748B"/></w:rPr><w:t>{!! $xml($filters['start_date'].' — '.$filters['end_date'].' · '.$t('finance_source').': '.$t('finance_source_'.$filters['source']).' · '.$t('currency').': '.$currencySymbol) !!}</w:t></w:r></w:p>
@foreach(['missing_revenue' => 'finance_missing_selling_prices', 'missing_costs' => 'finance_missing_costs', 'estimated_costs' => 'finance_estimated_costs', 'unavailable' => 'finance_unavailable'] as $flag => $label)
    @if(!empty($report['total'][$flag]))<w:p><w:pPr><w:shd w:fill="FFF7E6"/></w:pPr><w:r><w:rPr><w:color w:val="9C6500"/><w:sz w:val="14"/><w:szCs w:val="14"/></w:rPr><w:t>{!! $xml($t($label)) !!}</w:t></w:r></w:p>@endif
@endforeach
@include('localcashierreport::partials.financial_word_cards')
<w:p><w:pPr><w:spacing w:after="90"/></w:pPr></w:p>
@if(!$report['branches'])
    <w:p><w:r><w:t>{!! $xml($t('finance_no_locations')) !!}</w:t></w:r></w:p>
@else
<w:tbl><w:tblPr><w:tblW w:w="15398" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="11500"/><w:gridCol w:w="260"/><w:gridCol w:w="3638"/></w:tblGrid><w:tr>
<w:tc><w:tcPr><w:tcW w:w="11500" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>
    <w:p><w:pPr><w:spacing w:after="100"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="173B67"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t>{!! $xml($t('financial_performance')) !!}</w:t></w:r></w:p>
    @include('localcashierreport::partials.financial_word_table')
    <w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p>
</w:tc>
<w:tc><w:tcPr><w:tcW w:w="260" w:type="dxa"/></w:tcPr><w:p/></w:tc>
<w:tc><w:tcPr><w:tcW w:w="3638" w:type="dxa"/><w:shd w:fill="F6F8FB"/><w:tcMar><w:top w:w="160" w:type="dxa"/><w:left w:w="180" w:type="dxa"/><w:bottom w:w="160" w:type="dxa"/><w:right w:w="180" w:type="dxa"/></w:tcMar><w:vAlign w:val="top"/></w:tcPr>
    @include('localcashierreport::partials.financial_word_chart')
    <w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p>
</w:tc>
</w:tr></w:tbl>
@endif
<w:p><w:pPr><w:spacing w:before="140"/></w:pPr><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="13"/><w:szCs w:val="13"/></w:rPr><w:t>{!! $xml($t('finance_basis')) !!}</w:t></w:r></w:p>
<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>
</w:body></w:document>
