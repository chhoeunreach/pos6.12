@extends('smartstockinventory::layouts.master')
@section('page_title', 'Inventory Count')

@section('module_content')
<div class="box box-primary">
    <div class="box-header"><h4>Create Count Session</h4></div>
    <div class="box-body">
        <form method="post" action="{{ route('ssi.count.store') }}">@csrf
            <div class="row">
                <div class="col-md-4"><input class="form-control" name="session_name" placeholder="Session name" required></div>
                <div class="col-md-4">
                    <select class="form-control select2" name="location_id" required>
                        <option value="">Select location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><button class="btn btn-primary">Save Draft</button></div>
            </div>
        </form>
    </div>
</div>

<div class="box box-default">
    <div class="box-header">
        <h4>Count Lines</h4>
        <div class="pull-right">
            <a class="btn btn-success btn-sm" href="{{ route('ssi.count.export', ['session_id' => optional($sessions->first())->id]) }}">Export Excel</a>
        </div>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped datatable" id="ssi_count_table">
            <thead><tr>
                <th>SKU</th><th>Product</th><th>Variation</th><th>IMEI</th><th>Lot Number</th>
                <th>System Qty</th><th>Actual Qty</th><th>Difference</th><th>Status</th><th>Remark</th><th>Action</th>
            </tr></thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line->sku }}</td><td>{{ $line->product_name }}</td><td>{{ $line->variation_name }}</td>
                        <td>{{ $line->imei }}</td><td>{{ $line->lot_number }}</td><td>{{ $line->system_qty }}</td>
                        <td>{{ $line->actual_qty }}</td><td>{{ $line->difference_qty }}</td>
                        <td><span class="badge bg-info">{{ ucfirst(str_replace('_',' ', $line->status)) }}</span></td>
                        <td>{{ $line->remark }}</td>
                        <td>
                            <form method="post" action="{{ route('ssi.count.line.update', $line->id) }}" style="display:inline-block;">@csrf
                                <input type="hidden" name="actual_qty" value="{{ $line->actual_qty }}">
                                <input type="hidden" name="status" value="{{ $line->status }}">
                                <input type="hidden" name="remark" value="{{ $line->remark }}">
                                <input type="hidden" name="reason" value="quick_update">
                                <button class="btn btn-xs btn-info">Update</button>
                            </form>
                            <form method="post" action="{{ route('ssi.count.line.delete', $line->id) }}" style="display:inline-block;">@csrf @method('DELETE')
                                <input type="hidden" name="reason" value="quick_delete">
                                <button class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('module_js')
<script>$(function(){ $('#ssi_count_table').DataTable({pageLength:25}); $('.select2').select2(); });</script>
@endsection
