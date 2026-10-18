@php
    $reconciliation = $plan['reconciliation'] ?? [];
    $remaining = $reconciliation['unresolved'] ?? [];
    $changed = $plan['changed_rows'] ?? 0;
@endphp
<div class="box box-danger" style="border-top-color:#c0392b">
    <div class="box-header with-border"><h3 class="box-title" style="color:#c0392b;font-weight:bold"><i class="fa fa-bolt"></i> Force business repair — preview</h3></div>
    <div class="box-body">
        <div class="alert alert-warning">
            <strong>This is a force repair.</strong> Unlike the reviewed repair, it rebuilds missing/incorrect transfer receiving
            serials automatically, then re-runs the reviewed engine. Nothing has been written yet — this is a preview only.
            Review every change below before applying.
        </div>
        <p>Business #{{ $plan['business_id'] }} — all locations. Reason: {{ $plan['reason'] }}</p>
        <p><strong>Verification:</strong> {{ !empty($plan['is_fully_verified']) ? 'All checked stock records agree after the proposed repair.' : 'Incomplete — unresolved records remain after the proposed repair.' }}</p>
        <p>Proposed: <strong>{{ count($plan['transfer_repairs']) }}</strong> transfer receiving document(s) reconciled ·
            <strong>{{ $changed }}</strong> receiving row(s) changed ({{ $plan['deleted_rows'] }} removed) ·
            <strong>{{ count($reconciliation['allocation_repairs'] ?? []) }}</strong> allocation repairs ·
            <strong>{{ count($reconciliation['counter_repairs'] ?? []) }}</strong> receipt counter repairs ·
            <strong>{{ count($reconciliation['location_repairs'] ?? []) }}</strong> location balance repairs.</p>

        <h4><i class="fa fa-calendar"></i> Dated source re-allocations ({{ count($plan['reallocations'] ?? []) }})</h4>
        <p class="text-muted">Allocations that pointed at a receipt delivered <em>after</em> the transfer are re-pointed to an earlier receipt that was on hand. Receiving serials are reconciled to follow the new lots.</p>
        <div class="table-responsive"><table class="table table-bordered table-condensed">
            <thead><tr><th>Transfer</th><th>Line</th><th>Source before → after</th><th>Quantity</th></tr></thead>
            <tbody>@forelse($plan['reallocations'] ?? [] as $row)
                <tr><td>{{ $row['reference'] }}</td><td>#{{ $row['line_id'] }}</td>
                    <td>#{{ $row['source_before'] }} → #{{ $row['source_after'] }}</td>
                    <td>{{ $row['quantity'] }}</td></tr>
            @empty<tr><td colspan="4">No dated allocations needed re-pointing.</td></tr>@endforelse</tbody>
        </table></div>

        <h4><i class="fa fa-truck"></i> Transfer receiving repairs</h4>
        <div class="table-responsive"><table class="table table-bordered table-condensed">
            <thead><tr><th>Transfer</th><th>Receiving doc</th><th>Action</th><th>Row</th><th>Lot</th><th>Mfg / Exp</th><th>Quantity</th></tr></thead>
            <tbody>
            @forelse($plan['transfer_repairs'] as $transfer)
                @php $first = true; @endphp
                @forelse($transfer['rows'] as $row)
                    @php
                        $before = $row['before'] ?? [];
                        $after = $row['after'] ?? [];
                        $lot = $row['action'] === 'deleted' ? ($before['lot_number'] ?? '') : ($after['lot_number'] ?? ($before['lot_number'] ?? ''));
                        $mfg = $row['action'] === 'deleted' ? ($before['mfg_date'] ?? '') : ($after['mfg_date'] ?? ($before['mfg_date'] ?? ''));
                        $exp = $row['action'] === 'deleted' ? ($before['exp_date'] ?? '') : ($after['exp_date'] ?? ($before['exp_date'] ?? ''));
                        $qty = $row['action'] === 'deleted' ? ($before['quantity'] ?? '') : ($after['quantity'] ?? ($before['quantity'] ?? ''));
                        $badge = ['updated' => 'warning', 'inserted' => 'success', 'deleted' => 'danger'][$row['action']] ?? 'default';
                    @endphp
                    <tr>
                        <td>@if($first)<strong>{{ $transfer['reference'] }}</strong><div class="text-muted">#{{ $transfer['transfer_id'] }}</div>@endif</td>
                        <td>@if($first) #{{ $transfer['destination_id'] }} @endif</td>
                        <td><span class="label label-{{ $badge }}">{{ ucfirst($row['action']) }}</span></td>
                        <td>{{ $row['action'] === 'inserted' ? 'new' : '#'.$row['id'] }}</td>
                        <td>{{ $lot !== '' ? $lot : '—' }}</td>
                        <td>{{ $mfg ?: '—' }} / {{ $exp ?: '—' }}</td>
                        <td>{{ $qty }}</td>
                    </tr>
                    @php $first = false; @endphp
                @empty
                    <tr><td colspan="7">No row changes for this transfer.</td></tr>
                @endforelse
                @foreach($transfer['warnings'] ?? [] as $warning)
                    <tr><td></td><td colspan="6" class="text-danger"><i class="fa fa-exclamation-triangle"></i> {{ $warning }}</td></tr>
                @endforeach
            @empty
                <tr><td colspan="7">No transfer receiving repairs required.</td></tr>
            @endforelse
            </tbody>
        </table></div>

        <h4><i class="fa fa-exchange"></i> Allocation repairs</h4>
        <div class="table-responsive"><table class="table table-bordered table-condensed">
            <thead><tr><th>Reference / line</th><th>Source after</th><th>Selected before → after</th><th>Quantity / returned</th></tr></thead>
            <tbody>@forelse($reconciliation['allocation_repairs'] ?? [] as $row)
                <tr><td>{{ $row['reference'] }} / #{{ $row['line_id'] }}</td>
                    <td>#{{ $row['source_id'] }} · {{ $row['serial'] ?: 'No serial' }}</td>
                    <td>#{{ $row['selected_before'] }} → #{{ $row['selected_after'] }}</td>
                    <td>{{ $row['quantity'] }} / {{ $row['quantity_returned'] }}</td></tr>
            @empty<tr><td colspan="4">No allocation changes.</td></tr>@endforelse</tbody>
        </table></div>

        <h4><i class="fa fa-calculator"></i> Receipt counters &amp; location balances</h4>
        <div class="table-responsive"><table class="table table-bordered table-condensed">
            <thead><tr><th>Type</th><th>Record</th><th>Before</th><th>After</th></tr></thead>
            <tbody>
            @forelse($reconciliation['counter_repairs'] ?? [] as $row)
                <tr><td>Counter</td><td>#{{ $row['receipt_id'] }} · {{ $row['serial'] ?: 'No serial' }}</td><td>{{ $row['before'] }}</td><td>{{ $row['after'] }}</td></tr>
            @endforeach
            @forelse($reconciliation['location_repairs'] ?? [] as $row)
                <tr><td>Location</td><td>Variation #{{ $row['variation_id'] }} / location #{{ $row['location_id'] }}</td><td>{{ $row['before'] }}</td><td>{{ $row['after'] }}</td></tr>
            @endforeach
            @if(empty($reconciliation['counter_repairs']) && empty($reconciliation['location_repairs']))
                <tr><td colspan="4">No counter or location changes.</td></tr>
            @endif
            </tbody>
        </table></div>

        @if(!empty($plan['remaining_receiving_mismatches']))
        <div class="alert alert-danger">
            <strong>Receiving quantity mismatches remain after the proposed repair.</strong>
            Applying the changes above will leave these stock-history warnings unresolved.
        </div>
        <div class="table-responsive"><table class="table table-bordered table-condensed">
            <thead><tr><th>Outgoing invoice</th><th>Receiving invoice</th><th>SKU</th><th>Sent</th><th>Received</th></tr></thead>
            <tbody>@foreach($plan['remaining_receiving_mismatches'] as $row)
                <tr><td>{{ $row['source_reference'] ?: '#'.$row['transfer_id'] }}</td>
                    <td>{{ $row['receiving_reference'] ?: '#'.$row['receipt_id'] }}</td><td>{{ $row['sku'] }}</td>
                    <td>{{ $row['sent_quantity'] }}</td><td>{{ $row['received_quantity'] }}</td></tr>
            @endforeach</tbody>
        </table></div>
        @endif

        <h4><i class="fa fa-exclamation-circle text-danger"></i> Still unresolved after force repair ({{ count($remaining) }})</h4>
        <p class="text-muted">These records cannot be fixed automatically and remain available on the Authority screen for individual manual correction.</p>
        <div class="table-responsive" style="max-height:400px;overflow:auto"><table class="table table-bordered table-condensed">
            <thead><tr><th>Key</th><th>Reference</th><th>Reason</th><th>Fix</th></tr></thead>
            <tbody>@forelse($remaining as $row)
                <tr><td><code>{{ $row['key'] ?? '' }}</code></td><td>{{ $row['reference'] }}</td><td>{{ $row['reason'] }}</td>
                    <td>@php $link = ($links ?? [])[$row['key'] ?? ''] ?? ''; @endphp
                        @if($link)<a class="btn btn-xs btn-warning" href="{{ $link }}"><i class="fa fa-wrench"></i> Correct</a>
                        @else<span class="text-muted">—</span>@endif</td></tr>
            @empty<tr><td colspan="4">No unresolved allocation or receipt-counter records were reported.</td></tr>@endforelse</tbody>
        </table></div>

        @if(!empty($plan['can_apply']))
        <form method="post" action="{{ ssi_route('ssi.authority.force_apply') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <input type="hidden" name="reason" value="{{ $plan['reason'] }}">
            <div class="checkbox"><label><input type="checkbox" name="confirmed" value="1" required> I understand this force repair will rewrite transfer receiving records and I have reviewed the changes above.</label></div>
            <button class="btn btn-danger"><i class="fa fa-bolt"></i> Apply force repair</button>
        </form>
        @else
        <p>No force repair changes are available to apply.</p>
        @endif
        <a class="btn btn-default" style="margin-top:12px" href="{{ ssi_route('ssi.authority.index') }}">Back to Authority</a>
    </div>
</div>
