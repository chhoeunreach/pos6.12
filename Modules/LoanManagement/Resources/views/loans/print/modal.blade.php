<div class="modal-dialog modal-xl loan-print-preview-modal" role="document" style="width: 96%; max-width: 1180px;">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-print"></i> Print Loan #{{ $loanRow->loan_number ?? $loanRow->id }}
            </h4>
        </div>
        <div class="modal-body" style="height: 78vh; padding: 0; background: #3f454b;">
            <iframe
                id="loan_print_preview_frame"
                src="{{ $printUrl }}"
                style="width: 100%; height: 100%; border: 0; display: block;"
                title="Loan print preview"></iframe>
        </div>
        <div class="modal-footer">
            <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-default">
                <i class="fa fa-external-link"></i> Open Full Page
            </a>
            <button type="button" class="btn btn-primary" id="loan_print_preview_button">
                <i class="fa fa-print"></i> @lang('messages.print')
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
        </div>
    </div>
</div>

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
