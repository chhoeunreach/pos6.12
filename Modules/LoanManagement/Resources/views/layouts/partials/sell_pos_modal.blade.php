@php
    $sellPosUrl = Route::has('pos.create') ? route('pos.create') : url('/pos/create');
@endphp

<div class="modal fade no-print" id="loanSellPosModal" tabindex="-1" role="dialog" aria-labelledby="loanSellPosModalLabel">
    <div class="modal-dialog" role="document" style="width: 98%; max-width: 1500px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="loanSellPosModalLabel">Sell POS</h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <iframe
                    id="loanSellPosFrame"
                    title="Sell POS"
                    src="about:blank"
                    data-pos-url="{{ $sellPosUrl }}"
                    style="width:100%; height:78vh; border:0; display:block;"
                ></iframe>
            </div>
            <div class="modal-footer">
                <span class="pull-left text-muted">The invoice receipt will print after the sale is saved.</span>
                <a href="{{ $sellPosUrl }}" target="_blank" class="btn btn-default">
                    <i class="fa fa-external-link"></i> Open Full Page
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
