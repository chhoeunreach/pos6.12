@php
    $isAutostart = request()->boolean('autostart');
    $frameSrc = request()->boolean('autostart') ? $autoPrintUrl : $printUrl;
@endphp
<div class="modal-dialog modal-xl loan-print-preview-modal{{ $isAutostart ? ' loan-print-preview-modal--autostart' : '' }}" role="document" style="width: {{ $isAutostart ? '98%' : '96%' }}; max-width: {{ $isAutostart ? '1280px' : '1180px' }};">
    <div class="modal-content{{ $isAutostart ? ' loan-print-preview-modal__content--autostart' : '' }}">
        @if(! $isAutostart)
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ lm_label('messages.close', 'Close', 'បិទ') }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-print"></i> Print Installment #{{ $loanRow->loan_number ?? $loanRow->id }}
            </h4>
        </div>
        @endif
        <div class="modal-body loan-print-preview-modal__body{{ $isAutostart ? ' loan-print-preview-modal__body--autostart' : '' }}">
            <iframe
                id="loan_print_preview_frame"
                src="{{ $frameSrc }}"
                style="width: 100%; height: 100%; border: 0; display: block;"
                title="Installment print preview"></iframe>
        </div>
        @if(! $isAutostart)
        <div class="modal-footer">
            <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-default">
                <i class="fa fa-external-link"></i> Open Full Page
            </a>
            <button type="button" class="btn btn-primary" id="loan_print_preview_button">
                <i class="fa fa-print"></i> {{ lm_label('messages.print', 'Print', 'បោះពុម្ព') }}
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ lm_label('messages.close', 'Close', 'បិទ') }}</button>
        </div>
        @endif
    </div>
</div>

<style>
    .loan-print-preview-modal__body {
        height: 78vh;
        padding: 0;
        background: #3f454b;
    }
    .loan-print-preview-modal--autostart .modal-content {
        border: 0;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
    }
    .loan-print-preview-modal__body--autostart {
        height: 88vh;
        background: #ffffff;
    }
</style>

<script>
$(function () {
    $('#loan_print_preview_button').off('click.loanPrintPreview').on('click.loanPrintPreview', function () {
        var frame = document.getElementById('loan_print_preview_frame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    });
});
</script>
