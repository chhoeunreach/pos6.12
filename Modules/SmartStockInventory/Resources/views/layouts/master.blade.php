@extends('layouts.app')
@section('title', 'Smart Stock Inventory')

@section('content')
<section class="content-header"><h1>@yield('page_title')</h1></section>
<section class="content" id="ssi_app" style="font-size:14px;">
    <div class="btn-group" style="margin-bottom:10px;">
        <a class="btn btn-xs btn-primary" href="{{ route('ssi.count.index') }}">Add</a>
        <a class="btn btn-xs btn-info" href="{{ route('ssi.count.index') }}">Edit</a>
        <a class="btn btn-xs btn-success" href="{{ route('ssi.settings.index') }}">Update</a>
        <a class="btn btn-xs btn-danger" href="{{ route('ssi.count.index') }}">Delete</a>
        <a class="btn btn-xs btn-warning" href="{{ route('ssi.mismatch.index') }}">Fix</a>
        <a class="btn btn-xs btn-default" href="{{ route('ssi.fix_logs') }}">Rollback</a>
        <a class="btn btn-xs btn-default" href="{{ route('ssi.count.export', ['session_id' => request('session_id')]) }}">Export</a>
        <a class="btn btn-xs btn-default" href="#" onclick="window.print();return false;">Print</a>
    </div>
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
    @endif
    @yield('module_content')
</section>
@endsection

@section('javascript')
<script src="{{ asset('Modules/SmartStockInventory/Resources/assets/js/smart_stock_inventory.js') }}"></script>
<link rel="stylesheet" href="{{ asset('Modules/SmartStockInventory/Resources/assets/css/smart_stock_inventory.css') }}">
@yield('module_js')
@endsection
