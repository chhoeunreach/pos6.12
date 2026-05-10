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
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="location_ids">Business Location:</label>
                        <select class="form-control select2" id="location_ids" name="location_ids[]" multiple style="width:100%;">
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ in_array((int)$loc->id, (array)$locationIds, true) ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
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
                        <button class="btn btn-primary">Apply</button>
                        <a class="btn btn-default" href="{{ route('ssi.dashboard.detail', ['metric' => $metric]) }}">Clear</a>
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
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach((array)$row as $v)<td>{{ $v }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="text-center">No data found</td></tr>
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
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
@section('module_js')
<script>
$(function(){
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
});
</script>
@endsection
