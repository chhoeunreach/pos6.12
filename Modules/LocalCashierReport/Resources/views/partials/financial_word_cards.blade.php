<w:tbl><w:tblPr><w:tblW w:w="15398" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="160" w:type="dxa"/><w:left w:w="180" w:type="dxa"/><w:bottom w:w="160" w:type="dxa"/><w:right w:w="180" w:type="dxa"/></w:tblCellMar></w:tblPr>
<w:tblGrid><w:gridCol w:w="3684"/><w:gridCol w:w="220"/><w:gridCol w:w="3684"/><w:gridCol w:w="220"/><w:gridCol w:w="3684"/><w:gridCol w:w="220"/><w:gridCol w:w="3684"/></w:tblGrid>
<w:tr><w:trPr><w:cantSplit/></w:trPr>
@foreach($cards as $card)
    <w:tc><w:tcPr><w:tcW w:w="3684" w:type="dxa"/><w:shd w:fill="{{ $card['fill'] }}"/><w:vAlign w:val="top"/></w:tcPr>
        <w:p><w:pPr><w:spacing w:after="90"/></w:pPr><w:r><w:rPr><w:color w:val="{{ $card['color'] }}"/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr><w:t>{!! $xml($card['label']) !!}</w:t></w:r></w:p>
        <w:p><w:pPr><w:spacing w:after="90"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="{{ $card['color'] }}"/><w:sz w:val="{{ $loop->last ? 26 : 36 }}"/><w:szCs w:val="{{ $loop->last ? 26 : 36 }}"/></w:rPr><w:t>{!! $xml($card['value']) !!}</w:t></w:r></w:p>
        <w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr><w:color w:val="{{ $card['color'] }}"/><w:sz w:val="13"/><w:szCs w:val="13"/></w:rPr><w:t>{!! $xml($card['note']) !!}</w:t></w:r></w:p>
    </w:tc>
    @if(!$loop->last)<w:tc><w:tcPr><w:tcW w:w="220" w:type="dxa"/></w:tcPr><w:p/></w:tc>@endif
@endforeach
</w:tr></w:tbl>
