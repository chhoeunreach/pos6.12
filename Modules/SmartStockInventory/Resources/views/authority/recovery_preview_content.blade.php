<div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Original receiving lot recovery — {{ $transfer->ref_no }}</h3></div>
<div class="box-body">
    <p>No stock changes have been saved. Review the original receipt IDs, serials, quantities and consumption below, followed by the full business reconciliation.</p>
    <p>{{ count($plan['receipt_repairs']) }} receiving records will be restored or corrected. Reason: {{ $plan['reason'] }}</p>
    <p>Backup checksum: <code>{{ $plan['backup_sha256'] }}</code></p>
    <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Original receipt ID</th><th>Current serial / quantity</th><th>Restored serial / quantity</th><th>Costs before → after</th><th>Dates before → after</th><th>Linked consumption before → after recovery</th></tr></thead><tbody>
    @forelse($plan['receipt_repairs'] as $row)
        <tr><td>#{{ $row['receipt_id'] }}</td><td>@if($row['before']) {{ $row['before']['lot_number'] }} / {{ $row['before']['quantity'] }} @else Deleted receipt @endif</td>
            <td>{{ $row['after']['lot_number'] }} / {{ $row['after']['quantity'] }}</td>
            <td>{{ $row['before']['purchase_price_inc_tax'] ?? 'Deleted' }} → {{ $row['after']['purchase_price_inc_tax'] }}</td>
            <td>{{ $row['before']['mfg_date'] ?? '—' }} / {{ $row['before']['exp_date'] ?? '—' }} → {{ $row['after']['mfg_date'] ?? '—' }} / {{ $row['after']['exp_date'] ?? '—' }}</td>
            <td>Before: {{ $row['before']['quantity_sold'] ?? 'Deleted' }} sold, {{ $row['before']['quantity_adjusted'] ?? 'Deleted' }} adjusted, {{ $row['before']['mfg_quantity_used'] ?? 'Deleted' }} manufactured.<br>After — sold/transferred: {{ $row['after']['quantity_sold'] }}; adjusted: {{ $row['after']['quantity_adjusted'] }}; manufactured: {{ $row['after']['mfg_quantity_used'] }}</td></tr>
    @empty<tr><td colspan="6">No receiving lot changes are necessary.</td></tr>@endforelse
    </tbody></table></div>
</div></div>
@include('smartstockinventory::authority.full_preview_content', ['plan' => $plan['reconciliation'], 'hideApply' => true])
@if($plan['can_apply'])
<form method="post" action="{{ ssi_route('ssi.authority.recovery_apply', ['id' => $transfer->id]) }}" class="well">
    @csrf
    <input type="hidden" name="payload" value="{{ $payload }}">
    <input type="hidden" name="reason" value="{{ $plan['reason'] }}">
    <input type="hidden" name="token" value="{{ $plan['token'] }}">
    <div class="checkbox"><label><input type="checkbox" name="confirmed" value="1" required> I reviewed the original receiving lot identities and all business reconciliation changes shown above.</label></div>
    <button class="btn btn-danger">Apply reviewed receipt recovery and full repair</button>
</form>
@endif
