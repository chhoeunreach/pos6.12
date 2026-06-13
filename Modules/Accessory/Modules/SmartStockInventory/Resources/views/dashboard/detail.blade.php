@extends('smartstockinventory::layouts.master')
@section('page_title', $title)
@section('module_content')
<div class="box box-primary">
    <div class="box-header">
        <h4>{{ $title }}</h4>
        <div class="pull-right">
            <button onclick="window.print()" class="btn btn-default btn-sm">Print</button>
        </div>
    </div>
    @if(in_array($metric, ['total_products', 'total_stock_qty', 'total_stock_value']))
        @component('components.filters', ['title' => __('report.filters')])
            <form method="get" class="row">
                <input type="hidden" name="metric" value="{{ $metric }}">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Business Location:</label><br>
                        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#ssiDetailLocationFilterModal">
                            <i class="fa fa-map-marker"></i> Select Locations
                        </button>
                        <small class="help-block">Selected: <span id="ssi_detail_selected_locations_count">{{ count((array)$locationIds) }}</span></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="qty_filter">@lang('report.filters') (Qty Available):</label>
                        <select class="form-control select2" id="qty_filter" name="qty_filter[]" multiple style="width:100%;">
                            <option value="non_zero" {{ in_array('non_zero', (array)request('qty_filter', [])) ? 'selected' : '' }}>Non-Zero</option>
                            <option value="zero" {{ in_array('zero', (array)request('qty_filter', [])) ? 'selected' : '' }}>Zero</option>
                            <option value="positive" {{ in_array('positive', (array)request('qty_filter', [])) ? 'selected' : '' }}>Positive (&gt; 0)</option>
                            <option value="negative" {{ in_array('negative', (array)request('qty_filter', [])) ? 'selected' : '' }}>Negative (&lt; 0)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <br>
                        <button type="button" class="btn btn-primary" id="ssi_apply_detail_filters">Apply</button>
                        <a class="btn btn-default" href="{{ route('ssi.dashboard.detail', ['metric' => $metric, 'location_ids' => request('location_ids', [])]) }}">Clear</a>
                    </div>
                </div>
            </form>
        @endcomponent
    @endif
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped datatable" id="ssi_dashboard_detail_table">
            <thead>
                <tr>
                    @foreach($headers as $h)<th>{{ $h }}</th>@endforeach
                    @if(in_array($metric, ['total_products', 'total_stock_qty', 'total_stock_value', 'pending_transfers']))<th>Action</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @if(in_array($metric, ['total_products', 'total_stock_qty', 'total_stock_value']))
                            <td>{{ $row->sku ?? '' }}</td>
                            <td>{{ $row->product ?? '' }}</td>
                            <td>{{ $row->variation ?? '' }}</td>
                            <td>{{ $row->location ?? '' }}</td>
                            <td>{{ $row->qty_available ?? '' }}</td>
                            <td>{{ $row->unit_cost ?? '' }}</td>
                            <td>{{ $row->stock_value ?? '' }}</td>
                            <td>
                                <a class="btn btn-xs btn-info"
                                   href="{{ route('ssi.movement.index', ['product' => $row->product ?? '', 'product_id' => $row->product_id ?? '', 'variation_id' => $row->variation_id ?? '', 'location_id' => $row->location_id ?? '']) }}">
                                    View History
                                </a>
                            </td>
                        @elseif($metric === 'pending_transfers')
                            <td>{{ $row->transaction_date ?? '' }}</td>
                            <td>{{ $row->ref_no ?? '' }}</td>
                            <td>{{ $row->from_location ?? '' }}</td>
                            <td>{{ $row->to_location ?? '' }}</td>
                            <td>{{ $row->status ?? '' }}</td>
                            <td>{{ $row->created_by ?? '' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-xs btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        Actions <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                        <li><a href="{{ url('/stock-transfers/' . (int)($row->transfer_id ?? 0)) }}" target="_blank"><i class="fa fa-eye"></i> View</a></li>
                                        <li><a href="{{ url('/stock-transfers/' . (int)($row->transfer_id ?? 0) . '/edit') }}" target="_blank"><i class="fa fa-pencil"></i> Edit</a></li>
                                        @if(auth()->user()->can('stock_transfer.delete') && !in_array((string)($row->status ?? ''), ['final', 'completed'], true))
                                            <li>
                                                <a href="#" class="ssi-delete-transfer" data-transfer-id="{{ (int)($row->transfer_id ?? 0) }}">
                                                    <i class="fa fa-trash text-danger"></i> Delete
                                                </a>
                                            </li>
                                        @endif
                                        <li class="divider"></li>
                                        <li><a href="#" class="ssi-post-action" data-form-id="pt_completed_{{ $loop->index }}" data-confirm-msg="Mark this transfer as completed?"><i class="fa fa-check text-success"></i> Set Completed</a></li>
                                        <li><a href="#" class="ssi-post-action" data-form-id="pt_in_transit_{{ $loop->index }}" data-confirm-msg="Mark this transfer as in transit?"><i class="fa fa-truck text-warning"></i> Set In Transit</a></li>
                                        <li><a href="#" class="ssi-post-action" data-form-id="pt_pending_{{ $loop->index }}" data-confirm-msg="Mark this transfer as pending?"><i class="fa fa-clock-o text-info"></i> Set Pending</a></li>
                                    </ul>
                                </div>
                                <form id="pt_completed_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . (int)($row->transfer_id ?? 0)) }}" style="display:none;">@csrf<input type="hidden" name="status" value="completed"></form>
                                <form id="pt_in_transit_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . (int)($row->transfer_id ?? 0)) }}" style="display:none;">@csrf<input type="hidden" name="status" value="in_transit"></form>
                                <form id="pt_pending_{{ $loop->index }}" method="POST" action="{{ url('/stock-transfers/update-status/' . (int)($row->transfer_id ?? 0)) }}" style="display:none;">@csrf<input type="hidden" name="status" value="pending"></form>
                            </td>
                        @else
                            @foreach((array)$row as $v)<td>{{ $v }}</td>@endforeach
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) + (in_array($metric, ['total_products', 'total_stock_qty', 'total_stock_value', 'pending_transfers']) ? 1 : 0) }}" class="text-center">No data found</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    @foreach($headers as $h)
                        <th>
                            @if($h === 'Qty Available')<span id="ssi_total_qty">0</span>
                            @elseif($h === 'Stock Value')<span id="ssi_total_value">0</span>
                            @endif
                        </th>
                    @endforeach
                    @if(in_array($metric, ['total_products', 'total_stock_qty', 'total_stock_value', 'pending_transfers']))<th></th>@endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="modal fade" id="ssiTxFixModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="width:95%; max-width:1400px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Edit Sold Transaction</h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <iframe id="ssiTxFixFrame" src="about:blank" style="width:100%; height:75vh; border:0;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ssiDetailLocationFilterModal" tabindex="-1" role="dialog" aria-labelledby="ssiDetailLocationFilterModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ssiDetailLocationFilterModalLabel">Select Business Locations</h4>
            </div>
            <div class="modal-body">
                <select class="form-control select2" id="location_ids_modal" multiple style="width:100%;">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ in_array((int)$loc->id, (array)$locationIds, true) ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="ssi_apply_detail_location_modal">Apply Locations</button>
            </div>
        </div>
    </div>
</div>
@endsection
@section('module_js')
<script>
$(function(){
    $(document).on('click', '.ssi-post-action', function(e){
        e.preventDefault();
        var formId = $(this).data('form-id');
        var confirmMsg = $(this).data('confirm-msg') || 'Are you sure?';
        if (!formId) return;
        if (confirm(confirmMsg)) {
            $('#' + formId).submit();
        }
    });

    $(document).on('click', '.ssi-delete-transfer', function(e){
        e.preventDefault();
        var transferId = parseInt($(this).data('transfer-id'), 10) || 0;
        if (!transferId) return;
        if (!confirm('Delete this pending transfer?')) return;

        $.ajax({
            url: '/stock-transfers/' + transferId,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'DELETE'
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(resp){
                alert((resp && resp.msg) ? resp.msg : 'Deleted successfully');
                window.location.reload();
            },
            error: function(xhr){
                var msg = 'Delete failed';
                if (xhr.responseJSON && xhr.responseJSON.msg) {
                    msg = xhr.responseJSON.msg;
                }
                alert(msg);
            }
        });
    });

    $('#ssiTxFixModal').on('hidden.bs.modal', function(){
        $('#ssiTxFixFrame').attr('src', 'about:blank');
    });

    function n(v){ return parseFloat(String(v).replace(/[^0-9.-]/g,'')) || 0; }
    $('#ssi_dashboard_detail_table').DataTable({
        pageLength:25,
        footerCallback: function(row, data){
            var api = this.api();
            var qtyIdx = -1, valIdx = -1;
            api.columns().every(function(i){
                var h = $(api.column(i).header()).text().trim();
                if(h === 'Qty Available'){ qtyIdx = i; }
                if(h === 'Stock Value'){ valIdx = i; }
            });
            if(qtyIdx >= 0){
                var qtyTotal = api.column(qtyIdx, {search:'applied'}).data().reduce(function(a,b){ return n(a)+n(b); }, 0);
                $('#ssi_total_qty').text(qtyTotal.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}));
            }
            if(valIdx >= 0){
                var valTotal = api.column(valIdx, {search:'applied'}).data().reduce(function(a,b){ return n(a)+n(b); }, 0);
                $('#ssi_total_value').text(valTotal.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}));
            }
        }
    });
    $('#location_ids_modal').select2({ width:'100%', dropdownParent: $('#ssiDetailLocationFilterModal') });
    $('#ssi_apply_detail_location_modal').on('click', function(){
        $('#ssi_detail_selected_locations_count').text(($('#location_ids_modal').val() || []).length);
        $('#ssiDetailLocationFilterModal').modal('hide');
    });
    $('#ssi_apply_detail_filters').on('click', function(){
        var form = $('#qty_filter').closest('form');
        form.find('input[name="location_ids[]"]').remove();
        var vals = $('#location_ids_modal').val() || [];
        vals.forEach(function(v){
            form.append('<input type="hidden" name="location_ids[]" value="'+v+'">');
        });
        form.submit();
    });
});
</script>
@endsection
