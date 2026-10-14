@php
    $stock_transfer_overselling = \App\Utils\BusinessUtil::allowsOverselling((int) session('user.business_id'));
@endphp
<input type="hidden" id="stock_transfer_allow_overselling" value="{{ $stock_transfer_overselling ? 1 : 0 }}">
@if($stock_transfer_overselling)
    <p class="text-muted"><span class="label label-info">Overselling enabled</span> Transfers can record negative source stock.</p>
@endif
