<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">Authority correction — {{ $transfer->ref_no }}</h4>
</div>
<div class="modal-body">
@php $oldCorrections = collect(old('corrections', []))->keyBy('sell_line_id'); @endphp
<form id="authority_correction_form" method="post" data-recorded-date="{{ $transfer->transaction_date }}" data-reference="{{ $transfer->ref_no ?: '#'.$transfer->id }}" data-receiving-date="{{ \App\Transaction::where('business_id', $transfer->business_id)->where('type', 'purchase_transfer')->where('transfer_parent_id', $transfer->id)->value('transaction_date') }}" action="{{ ssi_route('ssi.authority.preview', ['id' => $transfer->id]) }}">
    @csrf
    <input type="hidden" name="date_basis" id="authority_date_basis" value="{{ old('date_basis') }}">
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">{{ $transfer->ref_no }} — choose corrections</h3></div>
        <div class="box-body">
            <p>Unique record-supported source receipts and receiving batches are selected automatically. Review these selections; choose the correct records for any unresolved line. Leave both empty to skip a line.</p>
            <button type="button" class="btn btn-success authority-auto-complete">Auto-complete and verify</button>
            <p class="authority-auto-status help-block" role="status" aria-live="polite">Supported records will be filled for you. Review proposed changes before applying.</p>
            <div class="table-responsive"><table class="table table-bordered">
                <thead><tr><th>Product / line</th><th>Recorded selection and deduction</th><th>Correct source receipt</th><th>Receiving batch</th></tr></thead>
                <tbody>@foreach($lines as $line)
                    @php
                        $lineId = $line['sell_line_id'];
                        $previous = $oldCorrections->has($lineId) ? $oldCorrections->get($lineId) : ($line['recommendation']['automatic_match'] ?? []);
                        $serials = $line['destinations']->pluck('lot_number')->filter()->unique();
                        $suggestedSerial = $line['selected_serial'] ?: ($serials->count() === 1 ? $serials->first() : '');
                    @endphp
                    <tr class="authority-line" data-line-id="{{ $lineId }}" data-receiving-group="{{ $products->get($lineId)->product_id }}:{{ $products->get($lineId)->variation_id }}" data-completion="{{ json_encode($line['completion'] ?? []) }}" data-quantity="{{ $line['quantity'] }}" data-recorded-source-date="{{ $line['recorded_source_date'] }}" data-missing-receiving="{{ $line['missing_receiving_quantity'] }}">
                        <td>{{ $products->get($lineId)->name ?? '' }}<br>{{ $products->get($lineId)->sub_sku ?? '' }}<br>Line #{{ $lineId }} · Quantity {{ $line['quantity'] }}
                            <input type="hidden" name="corrections[{{ $lineId }}][sell_line_id]" value="{{ $lineId }}"></td>
                        <td>Selected: {{ $line['selected_serial'] ?: ($line['sources']->contains('id', $line['selected_purchase_line_id']) ? 'No serial' : 'Missing or unselected') }} @if($line['selected_purchase_line_id'])(#{{ $line['selected_purchase_line_id'] }})@endif
                            <br>Deducted:
                            @forelse($allocations->get($lineId, collect()) as $allocation)
                                <div>{{ $allocation->batch_id ? ($allocation->lot_number ?: 'No serial') : 'Missing / unallocated' }} (#{{ $allocation->purchase_line_id }}) · {{ $allocation->quantity - $allocation->qty_returned }}</div>
                            @empty None @endforelse
                        </td>
                        <td style="min-width:240px"><label class="sr-only" for="authority_source_{{ $lineId }}">Correct source receipt for line {{ $lineId }}</label>
                            <select id="authority_source_{{ $lineId }}" class="form-control authority-receipt authority-source" name="corrections[{{ $lineId }}][source_id]">
                                <option value="">Choose source receipt</option>
                                @foreach($line['sources'] as $source)<option value="{{ $source->id }}" data-serial="{{ $source->lot_number }}" data-receipt-date="{{ $source->receipt_date }}" {{ (int) ($previous['source_id'] ?? 0) === (int) $source->id ? 'selected' : '' }}>{{ $source->lot_number ?: 'No serial' }} · Receipt #{{ $source->id }} · {{ $source->receipt_date }} · Available {{ $source->available }}</option>@endforeach
                                <option value="new" {{ ($previous['source_id'] ?? '') === 'new' ? 'selected' : '' }}>+ Add missing source receipt</option>
                            </select>
                            <div class="authority-existing-serial" style="display:none">
                                <label for="authority_verified_serial_{{ $lineId }}">Fill missing source serial (optional)</label>
                                <input id="authority_verified_serial_{{ $lineId }}" class="form-control authority-verified-serial" name="corrections[{{ $lineId }}][verified_serial]" value="{{ $previous['verified_serial'] ?? '' }}" maxlength="191">
                                <p class="help-block">Enter the verified serial to update this existing receipt without adding stock. Leave empty for a product with no serial; quantities are corrected by receipt and batch IDs.</p>
                            </div>
                            <p class="help-block">{{ $line['recommendation']['message'] }} Add a source receipt only when the actual receipt is missing from stock history.</p>
                            <div class="authority-new-source" style="display:none">
                                <label for="authority_new_serial_{{ $lineId }}">Verified serial / lot (optional)</label>
                                <input id="authority_new_serial_{{ $lineId }}" class="form-control authority-new-serial" name="corrections[{{ $lineId }}][new_source][serial]" value="{{ $previous['new_source']['serial'] ?? $suggestedSerial }}" maxlength="191">
                                @if($suggestedSerial)<p class="help-block">Suggested from recorded serial: {{ $suggestedSerial }}. Confirm against the receipt.</p>@endif
                                <label for="authority_new_quantity_{{ $lineId }}">Actual missing receipt quantity</label>
                                <input id="authority_new_quantity_{{ $lineId }}" class="form-control" type="number" min="{{ $line['quantity'] }}" max="1000000000" step="any" name="corrections[{{ $lineId }}][new_source][quantity]" value="{{ $previous['new_source']['quantity'] ?? $line['quantity'] }}" required>
                                <label for="authority_new_date_{{ $lineId }}">Actual source receipt date</label>
                                <input id="authority_new_date_{{ $lineId }}" class="form-control authority-new-source-date" name="corrections[{{ $lineId }}][new_source][date]" value="{{ $previous['new_source']['date'] ?? '' }}" placeholder="YYYY-MM-DD HH:MM:SS" required>
                                <label for="authority_new_cost_{{ $lineId }}">Verified unit cost</label>
                                <input id="authority_new_cost_{{ $lineId }}" class="form-control" type="number" min="0" max="1000000000" step="any" name="corrections[{{ $lineId }}][new_source][cost]" value="{{ $previous['new_source']['cost'] ?? '' }}" required>
                                <p class="help-block">Leave serial empty for a product without a serial. This creates an opening stock correction at the source location and increases its recorded stock by the receipt quantity. Include the original receipt reference in the reason below.</p>
                            </div>
                        </td>
                        <td style="min-width:220px"><label class="sr-only" for="authority_destination_{{ $lineId }}">Receiving batch for line {{ $lineId }}</label>
                            <div class="checkbox"><label><input type="checkbox" class="authority-change-receiving-serial" name="corrections[{{ $lineId }}][change_receiving_serial]" value="1" {{ !empty($previous['change_receiving_serial']) ? 'checked' : '' }}> Change receiving serial / lot</label></div>
                            <p class="help-block">Choose the correct serial / lot under Source receipt, then select the receiving batch to update. Enable this option to correct a batch with later sales. Connected transfers must be corrected together.</p>
                            <select id="authority_destination_{{ $lineId }}" class="form-control authority-receipt authority-destination" name="corrections[{{ $lineId }}][destination_id]">
                                <option value="">Choose receiving batch</option>
                                @foreach($line['destinations'] as $destination)<option value="{{ $destination->id }}" data-serial="{{ $destination->lot_number }}" data-quantity="{{ $destination->quantity }}" data-used="{{ $destination->used ? 1 : 0 }}" {{ (int) ($previous['destination_id'] ?? 0) === (int) $destination->id ? 'selected' : '' }}>{{ $destination->lot_number ?: 'No serial' }} · Batch #{{ $destination->id }} · Quantity {{ $destination->quantity }}{{ $destination->used ? ' · Has later usage' : '' }}</option>@endforeach
                                <option value="new" {{ ($previous['destination_id'] ?? '') === 'new' ? 'selected' : '' }} {{ $line['missing_receiving_quantity'] + 0.0001 < $line['quantity'] ? 'disabled' : '' }}>+ Add missing receiving batch</option>
                            </select>
                            <p class="help-block">Missing received quantity: {{ $line['missing_receiving_quantity'] }}. A new batch uses the selected source serial and this line's quantity. Existing receiving quantities must be corrected using their existing batch.</p>
                            @if($line['missing_receiving_quantity'] + 0.0001 >= $line['quantity'])
                                <p class="text-info authority-missing-receiving-help">A receiving quantity is missing for this product. Confirm which line needs a new batch; preserve existing receiving batches.</p>
                            @endif
                            <p class="help-block authority-selection-help" role="status">Select the source receipt to see matching receiving batches.</p>
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <div class="row">
                <div class="col-sm-8 form-group"><label for="authority_evidence">Reason and supporting records</label>
                    <textarea id="authority_evidence" name="evidence" class="form-control" rows="3" minlength="10" maxlength="{{ \Modules\SmartStockInventory\Http\Controllers\AuthorityController::EVIDENCE_MAX_LENGTH }}" required placeholder="Explain the correction and identify the delivery record or serial check used.">{{ old('evidence') }}</textarea></div>
                <div class="col-sm-4 form-group">
                    <input type="hidden" name="change_transaction_date" value="0">
                    <div class="checkbox"><label><input type="checkbox" id="authority_change_date" name="change_transaction_date" value="1" {{ old('change_transaction_date') ? 'checked' : '' }}> Change delivery date</label></div>
                    <label for="authority_date">Verified delivery date</label>
                    <input id="authority_date" name="transaction_date" class="form-control" value="{{ old('change_transaction_date') ? old('transaction_date') : '' }}" placeholder="YYYY-MM-DD HH:MM:SS" {{ old('change_transaction_date') ? 'required' : 'disabled' }}>
                    <p class="help-block">Recorded: {{ $transfer->transaction_date }}. Leave Change delivery date unchecked to preserve both document dates and repair allocations only.</p>
                    <button type="button" class="btn btn-default btn-sm authority-keep-dates">Keep recorded dates</button>
                    <div class="authority-date-guidance" role="status" aria-live="polite"></div>
                    <button type="button" class="btn btn-default btn-sm authority-use-earliest-date" style="display:none">Fill earliest permitted date</button>
                    <p class="help-block">The earliest permitted date is a limit, not proof of delivery. Enter the actual delivery date confirmed by your records. Used receiving batches may require dependent movements to be reconciled first.</p></div>
            </div>
            <p class="help-block">The preview includes serial / lot changes, dependent allocation repairs and any new receipts. Records are saved only when you apply the correction. Delivery dates remain unchanged unless you explicitly change them. Select existing records whenever available.</p>
            <div class="authority-preview-error alert alert-danger" role="alert" tabindex="-1" style="display:none"></div>
            <button class="btn btn-primary" {{ count($lines) ? '' : 'disabled' }}>Preview authority correction</button>
        </div>
    </div>
</form>
</div>
