@php $purchaseHistoryTitle = \Modules\LocalCashierReport\Support\ReportLanguage::text('finance_purchase_price_history'); @endphp
<div class="modal fade no-print" id="purchase-price-history-modal" data-title="{{ $purchaseHistoryTitle }}" tabindex="-1" role="dialog" aria-labelledby="purchase-price-history-title" style="z-index:1070">
    <div class="modal-dialog" style="width:96%;max-width:1150px"><div class="modal-content">
        <div class="modal-header"><button class="close" type="button" data-dismiss="modal" aria-label="{{ __('messages.close') }}">×</button><h4 id="purchase-price-history-title" class="modal-title">{{ $purchaseHistoryTitle }}</h4></div>
        <div class="modal-body" style="padding:0"><p id="purchase-price-history-loading" role="status" style="padding:16px">{{ __('localcashierreport::report.loading') }}</p><iframe id="purchase-price-history-frame" src="about:blank" title="{{ $purchaseHistoryTitle }}" style="display:block;width:100%;height:70vh;border:0"></iframe></div>
        <div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">{{ __('messages.close') }}</button></div>
    </div></div>
</div>
