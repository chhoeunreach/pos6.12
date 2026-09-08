@extends('loanmanagement::layouts.app')

@section('title', 'Edit Role')

@section('loan_css')
    @include('standalone.partials.admin_ui_css')
@endsection

@section('content_body')
<div class="pos-admin-page">
    <div class="pos-page-head">
        <div class="pos-page-title">
            <h1>Edit Role</h1>
            <p>Adjust permission access for {{ $role->name }}.</p>
        </div>
        <div class="pos-action-row">
            <a href="{{ route('roles.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="pos-panel">
        <div class="pos-panel-head">
            <h3>Role Information</h3>
        </div>
    <form method="POST" action="{{ route('roles.update', $role->id) }}">
        @csrf
        @method('PUT')
        <div class="pos-panel-body">
            @include('standalone.roles.partials.form')
        </div>
        <div class="pos-page-foot">
            <a href="{{ route('roles.index') }}" class="btn btn-default">Cancel</a>
            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Update Role</button>
        </div>
    </form>
    </div>
</div>
@endsection
