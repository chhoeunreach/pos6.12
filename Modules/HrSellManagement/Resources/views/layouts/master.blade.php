@extends('layouts.app')
@section('title', trim($__env->yieldContent('page_title')) ?: 'HR Sell Management')
@section('content')
@include('layouts.partials.error')
@if(session('status'))
    <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
@endif
<section class="content-header">
    <h1>@yield('page_title', 'HR Sell Management')</h1>
</section>
<section class="content">
    @yield('module_content')
</section>
@endsection
@section('javascript')
@yield('module_js')
@endsection
