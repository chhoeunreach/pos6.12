@php
    $columnWidth = max(1, (int) floor((11500 - 2850) / max(1, count($columns) - 1)));
    $font = max(12, min(16, (int) floor(108 / count($columns))));
@endphp
<w:tbl><w:tblPr><w:tblW w:w="11500" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="DCE4ED"/><w:left w:val="single" w:sz="4" w:color="DCE4ED"/><w:bottom w:val="single" w:sz="4" w:color="DCE4ED"/><w:right w:val="single" w:sz="4" w:color="DCE4ED"/><w:insideH w:val="single" w:sz="4" w:color="DCE4ED"/><w:insideV w:val="single" w:sz="4" w:color="DCE4ED"/></w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="75" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="75" w:type="dxa"/></w:tblCellMar></w:tblPr>
<w:tblGrid>@foreach($columns as $index => $column)<w:gridCol w:w="{{ $index === 0 ? 2850 : $columnWidth }}"/>@endforeach</w:tblGrid>
<w:tr><w:trPr><w:tblHeader/></w:trPr>@foreach($columns as $index => $column)<w:tc><w:tcPr><w:tcW w:w="{{ $index === 0 ? 2850 : $columnWidth }}" w:type="dxa"/><w:shd w:fill="102D52"/></w:tcPr><w:p><w:pPr><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="{{ $font }}"/><w:szCs w:val="{{ $font }}"/></w:rPr><w:t>{!! $xml($column) !!}</w:t></w:r></w:p><w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:color w:val="DCE4ED"/><w:sz w:val="12"/><w:szCs w:val="12"/></w:rPr><w:t>{!! $xml($headerUnits[$index]) !!}</w:t></w:r></w:p></w:tc>@endforeach</w:tr>
@foreach($tableRows as $rowIndex => $record)
    @if(isset($record['section']) || isset($record['notice']))
        <w:tr><w:trPr><w:cantSplit/></w:trPr><w:tc><w:tcPr><w:tcW w:w="11500" w:type="dxa"/><w:gridSpan w:val="{{ count($columns) }}"/><w:shd w:fill="{{ isset($record['section']) ? 'EDF2F8' : 'FFFFFF' }}"/></w:tcPr><w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr>@if(isset($record['section']))<w:b/>@endif<w:color w:val="173B67"/><w:sz w:val="{{ $font }}"/><w:szCs w:val="{{ $font }}"/></w:rPr><w:t>{!! $xml($record['section'] ?? $record['notice']) !!}</w:t></w:r></w:p></w:tc></w:tr>
    @else
        <w:tr><w:trPr><w:cantSplit/></w:trPr>
        @foreach($record['cells'] as $index => $cell)
            @php
                $result = $record['kind'] === 'result';
                $subtotal = $record['kind'] === 'subtotal';
                $last = $index === count($record['cells']) - 1;
                $fill = $result ? ($last ? '193F6B' : '102D52') : ($subtotal ? 'E8EFF8' : ($last ? 'EDF2F8' : ($rowIndex % 2 ? 'F6F8FB' : 'FFFFFF')));
                $color = $index > 0 && str_starts_with((string) $cell, '(') ? ($result ? 'FFD0D5' : 'B33C4A') : ($result ? 'FFFFFF' : '183E6F');
            @endphp
            <w:tc><w:tcPr><w:tcW w:w="{{ $index === 0 ? 2850 : $columnWidth }}" w:type="dxa"/><w:shd w:fill="{{ $fill }}"/></w:tcPr><w:p><w:pPr><w:spacing w:after="0"/><w:jc w:val="{{ $index === 0 ? 'left' : 'right' }}"/></w:pPr><w:r><w:rPr>@if($last || $subtotal || $result)<w:b/>@endif<w:color w:val="{{ $color }}"/><w:sz w:val="{{ $font }}"/><w:szCs w:val="{{ $font }}"/></w:rPr><w:t xml:space="preserve">{!! $xml($cell) !!}</w:t></w:r></w:p>
                @if($index === 0 && $record['hint'])<w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:color w:val="{{ $result ? 'DCE4ED' : '64748B' }}"/><w:sz w:val="11"/><w:szCs w:val="11"/></w:rPr><w:t>{!! $xml($record['hint']) !!}</w:t></w:r></w:p>@endif
            </w:tc>
        @endforeach
        </w:tr>
    @endif
@endforeach
</w:tbl>
