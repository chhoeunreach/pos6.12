@php $costEditorLabels = $costEditorLabels ?? []; @endphp
<div class="modal fade no-print finance-no-print purchase-lot-price-modal" id="{{ $costEditorId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $costEditorId }}-title" style="z-index:1070">
    <div class="modal-dialog" role="document"><div class="modal-content" style="border-radius:10px;overflow:hidden">
        <div class="modal-header" style="background:#173b67;color:white">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ $costEditorLabels['cancel'] ?? 'Cancel' }}" style="color:white;opacity:.9">×</button>
            <h4 class="modal-title" id="{{ $costEditorId }}-title">{{ $costEditorLabels['title'] ?? 'Update Purchase Price' }}</h4>
        </div>
        <form class="purchase-lot-price-form">
            <div class="modal-body">
                <p class="text-muted" style="font-size:12px;line-height:1.7">{{ $costEditorLabels['shared'] ?? 'Updates the original purchase lot price for all related sales. Prices include item tax.' }}</p>
                <div class="purchase-lot-price-error alert alert-danger" role="alert" hidden></div>
                <p class="purchase-lot-price-loading" role="status">{{ $costEditorLabels['loading'] ?? 'Loading…' }}</p>
                <div class="purchase-lot-price-fields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default purchase-lot-price-cancel" data-dismiss="modal">{{ $costEditorLabels['cancel'] ?? 'Cancel' }}</button>
                <button type="submit" class="btn btn-primary purchase-lot-price-save">{{ $costEditorLabels['save'] ?? 'Save price' }}</button>
            </div>
        </form>
    </div></div>
</div>
