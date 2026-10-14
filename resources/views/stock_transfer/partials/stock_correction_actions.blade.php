<div class="form-group stock-correction-actions">
        <button type="button" class="btn btn-primary" id="auto_correct_stock"
            data-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'autoCorrectStock'], [$sell_transfer->id]) }}"
            title="Recalculate stock and align allocations with selected serials for administrators">
            <i class="fa fa-check-circle" aria-hidden="true"></i> Auto Correct
        </button>
        <button type="button" class="btn btn-default" id="generate_stock_correction_plan"
            data-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'generateCorrectionPlan'], [$sell_transfer->id]) }}"
            data-options-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'manualCorrectionOptions'], [$sell_transfer->id]) }}"
            data-preview-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'previewManualCorrection'], [$sell_transfer->id]) }}"
            data-manual-apply-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'applyManualCorrection'], [$sell_transfer->id]) }}"
            data-apply-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'applyCorrectionPlan'], [$sell_transfer->id]) }}">
            <i class="fa fa-refresh" aria-hidden="true"></i> Generate Correction Plan
        </button>
        <button type="button" class="btn btn-default" id="edit_verified_stock_correction"><i class="fa fa-edit" aria-hidden="true"></i> Correct Serials</button>
        <div id="auto_stock_correction_result" role="status" aria-live="polite" hidden style="margin-top:12px"></div>
    @can('stock_inventory.view')
        <a class="btn btn-default" href="{{ ssi_route('ssi.mismatch.index') }}">Mismatch Detector</a>
    @endcan
</div>
