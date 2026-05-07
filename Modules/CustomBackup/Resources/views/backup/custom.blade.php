@extends('layouts.app')
@section('title', 'Custom Backup')

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Custom Backup
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Export selected data by date range</small>
    </h1>
</section>

<section class="content">
    @if (session('status'))
        @php $status = session('status'); @endphp
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-{{ !empty($status['success']) && $status['success'] ? 'success' : 'danger' }} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ $status['msg'] ?? '' }}
                </div>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'export']), 'method' => 'post']) !!}
                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group">
                            {!! Form::label('from_date', 'From Date:') !!}
                            {!! Form::text('from_date', old('from_date'), ['class' => 'form-control', 'readonly', 'id' => 'from_date', 'required']) !!}
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            {!! Form::label('to_date', 'To Date:') !!}
                            {!! Form::text('to_date', old('to_date'), ['class' => 'form-control', 'readonly', 'id' => 'to_date', 'required']) !!}
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <label>Export Modules:</label>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[products]', 1, old('modules.products')) !!} Products
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[repair]', 1, old('modules.repair')) !!} Repair
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[purchases]', 1, old('modules.purchases')) !!} Purchases
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[sell]', 1, old('modules.sell')) !!} Sell
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[stock_transfers]', 1, old('modules.stock_transfers')) !!} Stock Transfers
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[stock_adjustment]', 1, old('modules.stock_adjustment')) !!} Stock Adjustment
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[expenses]', 1, old('modules.expenses')) !!} Expenses
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        {!! Form::checkbox('modules[users_permissions]', 1, old('modules.users_permissions')) !!} Users &amp; Permissions
                                    </label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">At least one checkbox is required.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                            <i class="fa fa-download"></i> Export SQL
                        </button>
                    </div>
                </div>

                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
</section>

@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        $('#from_date').datepicker({
            autoclose: true,
            format: datepicker_date_format
        });
        $('#to_date').datepicker({
            autoclose: true,
            format: datepicker_date_format
        });
    });
</script>
@endsection

