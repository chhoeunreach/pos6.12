@extends('loanmanagement::layouts.app')

@section('title', 'Create User')

@section('loan_css')
    @include('standalone.partials.admin_ui_css')
@endsection

@section('content_body')
<div class="pos-admin-page">
    <div class="pos-page-head">
        <div class="pos-page-title">
            <h1>Create User</h1>
            <p>Add a staff account and assign the correct access role.</p>
        </div>
        <div class="pos-action-row">
            <a href="{{ route('users.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="pos-panel">
        <div class="pos-panel-head">
            <h3>User Information</h3>
        </div>
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="pos-panel-body">
            @include('standalone.users.partials.form')
        </div>
        <div class="pos-page-foot">
            <a href="{{ route('users.index') }}" class="btn btn-default">Cancel</a>
            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save User</button>
        </div>
    </form>
    </div>
</div>
@endsection
