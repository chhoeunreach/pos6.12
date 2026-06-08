<?php
    $isAutostart = request()->boolean('autostart');
    $frameSrc = request()->boolean('autostart') ? $autoPrintUrl : $printUrl;
?>
<div class="modal-dialog modal-xl loan-print-preview-modal<?php echo e($isAutostart ? ' loan-print-preview-modal--autostart' : '', false); ?>" role="document" style="width: <?php echo e($isAutostart ? '98%' : '96%', false); ?>; max-width: <?php echo e($isAutostart ? '1280px' : '1180px', false); ?>;">
    <div class="modal-content<?php echo e($isAutostart ? ' loan-print-preview-modal__content--autostart' : '', false); ?>">
        <?php if(! $isAutostart): ?>
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo app('translator')->get('messages.close'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-print"></i> Print Loan #<?php echo e($loanRow->loan_number ?? $loanRow->id, false); ?>

            </h4>
        </div>
        <?php endif; ?>
        <div class="modal-body loan-print-preview-modal__body<?php echo e($isAutostart ? ' loan-print-preview-modal__body--autostart' : '', false); ?>">
            <iframe
                id="loan_print_preview_frame"
                src="<?php echo e($frameSrc, false); ?>"
                style="width: 100%; height: 100%; border: 0; display: block;"
                title="Loan print preview"></iframe>
        </div>
        <?php if(! $isAutostart): ?>
        <div class="modal-footer">
            <a href="<?php echo e($printUrl, false); ?>" target="_blank" rel="noopener" class="btn btn-default">
                <i class="fa fa-external-link"></i> Open Full Page
            </a>
            <button type="button" class="btn btn-primary" id="loan_print_preview_button">
                <i class="fa fa-print"></i> <?php echo app('translator')->get('messages.print'); ?>
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app('translator')->get('messages.close'); ?></button>
        </div>
        <?php endif; ?>
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
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/loans/print/modal.blade.php ENDPATH**/ ?>