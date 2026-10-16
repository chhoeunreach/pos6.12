<div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Full business repair — preview</h3></div>
    <div class="box-body">
        <p>This preview has not changed stock. It covers business #{{ $plan['business_id'] }} and all its locations.</p>
        <p>Reason: {{ $plan['reason'] }}</p>
        <p>Proposed: <strong>{{ count($plan['allocation_repairs']) }}</strong> allocation repairs,
            <strong>{{ count($plan['counter_repairs']) }}</strong> receipt counter repairs,
            <strong>{{ count($plan['location_repairs']) }}</strong> location balance repairs.</p>
        <p>Review the proposed receipt selections against your records. @if(empty($hideApply)) Receipt quantities, prices, receiving serials and delivery dates remain as recorded. @else The receipt recovery changes shown above are included when calculating this proposal. @endif Unresolved records are listed below.</p>
        <h4>Allocation repairs</h4>
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr><th>Reference / line</th><th>Allocations before</th><th>Source after</th><th>Selected receipt before → after</th><th>Quantity / returned</th></tr></thead>
            <tbody>@forelse($plan['allocation_repairs'] as $row)
                <tr><td>{{ $row['reference'] }} / #{{ $row['line_id'] }}</td>
                    <td>@forelse($row['before'] as $before)<div>Receipt #{{ $before['purchase_line_id'] }} · {{ $before['quantity'] }} / returned {{ $before['qty_returned'] }}</div>@empty No allocation @endforelse</td>
                    <td>#{{ $row['source_id'] }} · {{ $row['serial'] ?: 'No serial' }}</td>
                    <td>#{{ $row['selected_before'] }} → #{{ $row['selected_after'] }}</td>
                    <td>{{ $row['quantity'] }} / {{ $row['quantity_returned'] }}</td></tr>
            @empty<tr><td colspan="5">No allocation changes proposed.</td></tr>@endforelse</tbody>
        </table></div>
        <h4>Receipt counters</h4>
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr><th>Receipt / serial</th><th>Consumed before</th><th>Consumed after</th></tr></thead>
            <tbody>@forelse($plan['counter_repairs'] as $row)
                <tr><td>#{{ $row['receipt_id'] }} · {{ $row['serial'] ?: 'No serial' }}</td><td>{{ $row['before'] }}</td><td>{{ $row['after'] }}</td></tr>
            @empty<tr><td colspan="3">No receipt counter changes proposed.</td></tr>@endforelse</tbody>
        </table></div>
        <h4>Location balances</h4>
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr><th>Variation / location</th><th>Quantity before</th><th>Quantity after</th><th>Existing balance rows</th></tr></thead>
            <tbody>@forelse($plan['location_repairs'] as $row)
                <tr><td>#{{ $row['variation_id'] }} / #{{ $row['location_id'] }}</td><td>{{ $row['before'] }}</td><td>{{ $row['after'] }}</td><td>{{ count($row['before_rows']) }}</td></tr>
            @empty<tr><td colspan="4">No location changes proposed.</td></tr>@endforelse</tbody>
        </table></div>
        <h4>Unresolved records — require individual correction</h4>
        <table class="table table-bordered"><thead><tr><th>Reference</th><th>Reason</th><th>Fix</th></tr></thead><tbody>
            @forelse($plan['unresolved'] as $row)<tr><td>{{ $row['reference'] }}</td><td>{{ $row['reason'] }}</td>
                <td>@php $link = ($links ?? [])[$row['key'] ?? ''] ?? ''; @endphp
                    @if($link)<a class="btn btn-xs btn-warning" href="{{ $link }}"><i class="fa fa-wrench"></i> Correct</a>
                    @else<span class="text-muted">—</span>@endif</td></tr>
            @empty<tr><td colspan="3">No unresolved records found by this scan.</td></tr>@endforelse
        </tbody></table>
        @if($plan['can_apply'] && empty($hideApply))
        <form method="post" action="{{ ssi_route('ssi.authority.fix_mismatches') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <input type="hidden" name="reason" value="{{ $plan['reason'] }}">
            <div class="checkbox"><label><input type="checkbox" name="confirmed" value="1" required> I reviewed these exact changes and the supporting records.</label></div>
            <button class="btn btn-primary">Apply reviewed full repair</button>
        </form>
        @elseif(empty($hideApply))<p>No validated changes are available to apply. Review the unresolved records through their individual correction forms.</p>@endif
        <a class="btn btn-default" style="margin-top:12px" href="{{ ssi_route('ssi.authority.index') }}">Back to Authority</a>
    </div>
</div>
