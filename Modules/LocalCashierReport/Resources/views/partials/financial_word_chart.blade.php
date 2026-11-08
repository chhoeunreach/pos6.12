<w:p><w:pPr><w:spacing w:after="60"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="173B67"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t>{!! $xml($t('expense_mix')) !!}</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="14"/><w:szCs w:val="14"/></w:rPr><w:t>{!! $xml($t('expense_share')) !!}</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="14"/><w:szCs w:val="14"/></w:rPr><w:t>{!! $xml($t('finance_expenses')) !!}</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:b/><w:color w:val="173B67"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr><w:t>{!! $xml($money($report['total']['expenses'])) !!}</w:t></w:r></w:p>
@if($report['expense_filter_active'])<w:p><w:r><w:rPr><w:sz w:val="13"/><w:szCs w:val="13"/></w:rPr><w:t>{!! $xml($t('finance_expense_filter_note')) !!}</w:t></w:r></w:p>@endif
@if($report['unavailable'] || !$chartRows)
    <w:p><w:r><w:t>{!! $xml($t($report['unavailable'] ? 'finance_unavailable' : 'finance_no_expenses')) !!}</w:t></w:r></w:p>
@else
    @foreach($chartRows as $index => $expense)
        @php
            $fill = max(1, min(3099, (int) round(3100 * $expense['share'] / 100)));
            $color = ['173B67', '3876B5', 'C25261'][$index % 3];
            $label = $expense['name'] === '__uncategorized__' ? $t('uncategorized') : $expense['name'];
        @endphp
        <w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="3100"/></w:tabs><w:spacing w:before="130" w:after="60"/></w:pPr><w:r><w:rPr><w:sz w:val="15"/><w:szCs w:val="15"/></w:rPr><w:t>{!! $xml($label) !!}</w:t></w:r><w:r><w:tab/></w:r><w:r><w:rPr><w:b/><w:sz w:val="15"/><w:szCs w:val="15"/></w:rPr><w:t>{{ number_format($expense['share'], 1) }}%</w:t></w:r></w:p>
        <w:tbl><w:tblPr><w:tblW w:w="3100" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="{{ $fill }}"/><w:gridCol w:w="{{ 3100 - $fill }}"/></w:tblGrid><w:tr><w:trPr><w:cantSplit/><w:trHeight w:val="100" w:hRule="exact"/></w:trPr>
        @foreach([[$fill, $color], [3100 - $fill, 'E9EEF4']] as [$width, $shade])
            <w:tc><w:tcPr><w:tcW w:w="{{ $width }}" w:type="dxa"/><w:shd w:fill="{{ $shade }}"/></w:tcPr><w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="20" w:lineRule="exact"/></w:pPr><w:r><w:rPr><w:sz w:val="2"/><w:szCs w:val="2"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r></w:p></w:tc>
        @endforeach
        </w:tr></w:tbl>
        <w:p><w:pPr><w:spacing w:before="40" w:after="60"/></w:pPr><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="14"/><w:szCs w:val="14"/></w:rPr><w:t>{!! $xml($money($expense['total'])) !!}</w:t></w:r></w:p>
    @endforeach
    <w:p><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="12"/><w:szCs w:val="12"/></w:rPr><w:t>{!! $xml($t('finance_chart_note')) !!}</w:t></w:r></w:p>
@endif
