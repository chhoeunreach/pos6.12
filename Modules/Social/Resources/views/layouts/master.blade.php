@extends('layouts.app')
@section('title', trim($__env->yieldContent('page_title')) ?: 'Social Platform Admin')
@section('content')
@include('layouts.partials.error')
@if(session('status'))
    <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
@endif
<section class="content-header">
    <h1>
        <i class="fa fa-globe text-primary"></i>
        @yield('page_title', 'Social Platform Admin')
    </h1>
</section>
<section class="content">
    <div style="margin-bottom:15px;">
        <a href="{{ route('social.admin.posts.index') }}" class="btn btn-{{ request()->is('social/admin') || request()->is('social/admin/posts*') ? 'primary' : 'default' }}">
            <i class="fa fa-newspaper-o"></i> Feed & Sample Posts
        </a>
        <a href="{{ route('social.admin.reports.index') }}" class="btn btn-{{ request()->is('social/admin/reports*') ? 'primary' : 'default' }}">
            <i class="fa fa-flag"></i> Moderation Reports
        </a>
        <a href="{{ route('social.admin.settings.index') }}" class="btn btn-{{ request()->is('social/admin/settings*') ? 'primary' : 'default' }}">
            <i class="fa fa-cogs"></i> API Control & Settings
        </a>
        <a href="{{ url('/api/social/health') }}" target="_blank" class="btn btn-info pull-right">
            <i class="fa fa-heartbeat"></i> Check API Health
        </a>
    </div>
    @yield('module_content')
</section>
@endsection
@section('javascript')
@yield('module_js')
@endsection
