<div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">{{ $transfer->ref_no }} — review changes</h3></div>
    <div class="box-body">
        @if(!empty($input['date_basis']))
            <div class="alert alert-info">The delivery date is proposed from {{ $input['date_basis'] === 'recorded_receiving_date' ? 'the recorded receiving date' : 'source receipt dates' }}. Review this proposed date against your delivery records before applying.</div>
        @endif
        <p>This preview has not changed stock. Apply below to save these corrections together.</p>
        @if(!empty($plan['created_receipts']))
            <h4>Missing records to create</h4>
            <p>New source records are recorded as opening stock corrections. Receiving records restore this completed transfer's missing batches without moving the transfer quantity a second time. New record IDs shown in preview may change when saved.</p>
            <ul>@foreach($plan['created_receipts'] as $created)
                <li>Line #{{ $created['sell_line_id'] }}: new {{ $created['kind'] }} receipt · {{ $created['serial'] ?: 'No serial' }} · Quantity {{ $created['quantity'] }} · Location #{{ $created['location_id'] }} · {{ $created['date'] }}@if(isset($created['cost'])) · Unit cost {{ $created['cost'] }}@endif</li>
            @endforeach</ul>
        @endif
        @if(!empty($plan['filled_source_serials']))
            <h4>Missing source serials to fill</h4>
            <ul>@foreach($plan['filled_source_serials'] as $serialChange)
                <li>Receipt #{{ $serialChange['batch_id'] }}: No serial → {{ $serialChange['after'] }}. Receipt quantity stays unchanged.</li>
            @endforeach</ul>
        @endif
        @if(!empty($plan['replaced_missing_allocations']))
            <p>Deleted receipt references to replace using the newly entered source receipt:</p>
            <ul>@foreach($plan['replaced_missing_allocations'] as $replacement)
                <li>Line #{{ $replacement['sell_line_id'] }}: missing receipt #{{ $replacement['mapping']->purchase_line_id }} · Quantity {{ $replacement['mapping']->quantity }} → new source receipt #{{ $replacement['replacement_batch_id'] }}</li>
            @endforeach</ul>
        @endif
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr><th>Line</th><th>Selected receipt before</th><th>Deducted receipts before → after</th><th>Correct receipt / serial</th><th>Receiving serial before → after</th><th>Quantity</th></tr></thead>
            <tbody>@foreach($plan['changes'] as $change)<tr>
                <td>#{{ $change['sell_line_id'] }}</td><td>#{{ $change['selected_batch_before'] }}</td>
                <td>@forelse($change['allocations_before'] as $allocation)<div>Receipt #{{ $allocation->purchase_line_id }} · {{ $allocation->quantity - $allocation->qty_returned }}</div>@empty No allocation @endforelse
                    → Receipt #{{ $change['selected_batch_after'] }} · {{ $change['quantity'] }}</td>
                <td>#{{ $change['selected_batch_after'] }} · {{ $change['verified_serial'] ?: 'No serial' }}</td>
                <td>{{ $change['receiving_serial_before'] ?: 'No serial' }} → {{ $change['receiving_serial_after'] ?: 'No serial' }}
                    @if($change['receiving_mfg_date_before'] !== $change['receiving_mfg_date_after'])<div>Manufactured: {{ $change['receiving_mfg_date_before'] ?: 'None' }} → {{ $change['receiving_mfg_date_after'] ?: 'None' }}</div>@endif
                    @if($change['receiving_exp_date_before'] !== $change['receiving_exp_date_after'])<div>Expiry: {{ $change['receiving_exp_date_before'] ?: 'None' }} → {{ $change['receiving_exp_date_after'] ?: 'None' }}</div>@endif
                </td>
                <td>{{ $change['quantity'] }}</td>
            </tr>@endforeach</tbody>
        </table></div>
        @if($plan['date_change'])<p>Delivery date: {{ $plan['date_change']['transfer_before'] }} → {{ $plan['date_change']['transfer_after'] }}</p>@endif
        @if(count($plan['related_allocation_repairs']))
            <h4>Dependent deduction repairs</h4><ul>@foreach($plan['related_allocation_repairs'] as $repair)
                <li>Line #{{ $repair['sell_line_id'] }} → {{ $repair['serial'] }} (receipt #{{ $repair['selected_batch_id'] }}) · {{ $repair['quantity'] }}</li>
            @endforeach</ul>
        @endif
        @if(!empty($plan['receiving_repairs']['updated']) || !empty($plan['receiving_repairs']['inserted']) || !empty($plan['receiving_repairs']['deleted']))
            <h4>Related transfer receiving rebuilt</h4>
            <p>The related transfer receiving records are rebuilt so their serials corroborate the selected receipts.</p>
            <ul>@foreach($plan['receiving_repairs']['transfers'] ?? [] as $transferRepair)
                @foreach($transferRepair['rows'] ?? [] as $row)
                    <li>Receiving #{{ $row['id'] }} · {{ $transferRepair['reference'] ?? ('#'.($transferRepair['transfer_id'] ?? '')) }}: {{ ($row['before']['lot_number'] ?? '') !== '' ? $row['before']['lot_number'] : 'No serial' }} → {{ $row['action'] === 'deleted' ? 'Removed' : (($row['after']['lot_number'] ?? '') !== '' ? $row['after']['lot_number'] : 'No serial') }} · {{ $row['action'] }}</li>
                @endforeach
                @if(!empty($transferRepair['warnings']))<li>Warnings: {{ implode('; ', $transferRepair['warnings']) }}</li>@endif
            @endforeach</ul>
        @endif
        <h4>Lot counters</h4><ul>@foreach($plan['counter_repairs'] as $repair)
            <li>Receipt #{{ $repair['purchase_line_id'] }}: {{ $repair['quantity_sold_before'] }} → {{ $repair['quantity_sold_after'] }}</li>
        @endforeach</ul>
        @if(count($plan['remaining_stock_shortages']))<div class="alert alert-warning">{{ count($plan['remaining_stock_shortages']) }} source shortage(s) remain after these changes. Review their original receipts and movements.</div>@endif
        @if(count($plan['remaining_line_issues']) || count($plan['remaining_transfer_issues']))
            <div class="alert alert-warning">Other transfer issues remain. Review them in Mismatch Detector after applying this correction.</div>
        @endif
        <p><strong>Reason:</strong> {{ $input['evidence'] }}</p>
        <form method="post" action="{{ ssi_route('ssi.authority.apply', ['id' => $transfer->id]) }}">
            @csrf
            @if(array_key_exists('change_transaction_date', $input))<input type="hidden" name="change_transaction_date" value="{{ $input['change_transaction_date'] ? 1 : 0 }}">@endif
            <input type="hidden" name="date_basis" value="{{ $input['date_basis'] ?? '' }}">
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <input type="hidden" name="evidence" value="{{ $input['evidence'] }}">
            <input type="hidden" name="transaction_date" value="{{ $input['transaction_date'] ?? '' }}">
            @foreach($input['corrections'] as $index => $correction)
                @if(!empty($correction['change_receiving_serial']))
                    <input type="hidden" name="corrections[{{ $index }}][change_receiving_serial]" value="1">
                @endif
                @foreach(['sell_line_id', 'source_id', 'destination_id'] as $field)
                    <input type="hidden" name="corrections[{{ $index }}][{{ $field }}]" value="{{ $correction[$field] }}">
                @endforeach
                @if(trim((string) ($correction['verified_serial'] ?? '')) !== '')
                    <input type="hidden" name="corrections[{{ $index }}][verified_serial]" value="{{ $correction['verified_serial'] }}">
                @endif
                @if(($correction['source_id'] ?? null) === 'new')
                    @foreach(['serial', 'quantity', 'date', 'cost'] as $field)
                        <input type="hidden" name="corrections[{{ $index }}][new_source][{{ $field }}]" value="{{ $correction['new_source'][$field] ?? '' }}">
                    @endforeach
                @endif
            @endforeach
            <button class="btn btn-primary">Apply authority correction</button>
            <a class="btn btn-default" href="{{ ssi_route('ssi.authority.index', ['transfer_id' => $transfer->id]) }}">Back to records</a>
        </form>
    </div>
</div>
