<div class="modal-dialog modal-xl loan-pos-prefill-modal" role="document" style="width:96%; max-width:1280px;">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ lm_label('messages.close', 'Close', 'បិទ') }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-exchange"></i> Add Installment to POS
            </h4>
        </div>
        <div class="modal-body{{ empty($error) ? ' loan-pos-prefill-modal__body' : '' }}">
            @if(! empty($error))
                <div class="alert alert-danger" style="margin-bottom:0;">
                    <strong>Cannot add this loan to POS.</strong><br>
                    {{ $error }}
                </div>
            @else
                <iframe
                    src="{{ $posUrl }}"
                    title="POS for loan {{ $payload['loan_number'] ?? $loanId }}"
                    style="width:100%; height:100%; border:0; display:block;"></iframe>
            @endif
        </div>
        <div class="modal-footer">
            @if(empty($error) && ! empty($posUrl))
                <a href="{{ $posUrl }}" class="btn btn-default">
                    <i class="fa fa-external-link"></i> Open Full POS
                </a>
            @endif
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ lm_label('messages.close', 'Close', 'បិទ') }}</button>
        </div>
    </div>
</div>

<style>
    .loan-pos-prefill-modal__body {
        height: 82vh;
        padding: 0;
        background: #f4f6f8;
    }
</style>
