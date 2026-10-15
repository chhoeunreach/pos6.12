<h3>{{ $title }} <small>{{ $rows->total() }} line(s)</small></h3>
<form method="get" action="{{ ssi_route('ssi.authority.details') }}" class="authority-details-filter">
    <input type="hidden" name="category" value="{{ $category }}">
    <div class="row">
        <div class="col-sm-8 form-group"><label for="authority_details_reference">Transfer or invoice reference</label>
            <input id="authority_details_reference" name="reference" class="form-control" value="{{ $reference }}" maxlength="100" placeholder="Search document reference"></div>
        <div class="col-sm-4 form-group" style="padding-top:25px"><button class="btn btn-primary">Search details</button></div>
    </div>
</form>
<div class="table-responsive"><table class="table table-bordered table-striped">
    <thead><tr><th>Document / line</th><th>Product</th><th>Line quantity</th><th>Allocated quantity</th><th>Selected receipt</th><th>Deducted receipts</th><th>Issues</th><th>Action</th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
        <td>{{ $row->ref_no ?: ($row->invoice_no ?: '#'.$row->transaction_id) }}<br>{{ $row->type === 'sell_transfer' ? 'Stock transfer' : 'Sale' }} · Line #{{ $row->id }}<br>{{ $row->transaction_date }}</td>
        <td>{{ $row->product_name ?: 'Product unavailable' }}<br>{{ $row->sub_sku }}</td>
        <td>{{ $row->quantity }}</td><td>{{ $row->allocated_quantity }}</td>
        <td>@if($row->selected_batch){{ $row->selected_batch->lot_number ?: 'No serial' }} (#{{ $row->lot_no_line_id }})@elseif($row->lot_no_line_id)Missing receipt #{{ $row->lot_no_line_id }}@else No selected receipt @endif</td>
        <td>@forelse($row->allocations as $allocation)
            <div>{{ $allocation->batch ? ($allocation->batch->lot_number ?: 'No serial') : 'Missing / unallocated' }} (#{{ $allocation->purchase_line_id ?: 0 }}) · Quantity {{ $allocation->quantity }}@if($allocation->qty_returned) · Returned {{ $allocation->qty_returned }}@endif</div>
            @empty No allocation @endforelse</td>
        <td><ul>@foreach($row->reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></td>
        <td>@if($row->type === 'sell_transfer')
            <a class="btn btn-primary btn-sm" href="{{ ssi_route('ssi.authority.index', ['transfer_id' => $row->transaction_id]) }}">Open correction form</a>
            @elseif(auth()->user()->can('sell.view'))
            <a class="btn btn-default btn-sm" href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id]) }}">View sale</a>
            @else Review sale allocation @endif</td>
    </tr>@empty<tr><td colspan="8">No issues match this category and reference.</td></tr>@endforelse</tbody>
</table></div>
{{ $rows->links() }}
