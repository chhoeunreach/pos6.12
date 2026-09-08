@extends('loanmanagement::layouts.app')

@section('title', 'Edit User')

@section('loan_css')
    @include('standalone.partials.admin_ui_css')
@endsection

@section('content_body')
<div class="pos-admin-page">
    <div class="pos-page-head">
        <div class="pos-page-title">
            <h1>Edit User</h1>
            <p>Update account details, role assignment, and login status.</p>
        </div>
        <div class="pos-action-row">
            <a href="{{ route('users.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
    </div>

<div class="row">
    <div class="col-md-8">
        <div class="pos-panel">
            <div class="pos-panel-head">
                <h3 class="box-title">Edit User</h3>
            </div>
            <form method="POST" action="{{ route('users.update', $userRow->id) }}">
                @csrf
                @method('PUT')
                <div class="pos-panel-body">
                    @include('standalone.users.partials.form', ['userRow' => $userRow])
                </div>
                <div class="pos-page-foot">
                    <a href="{{ route('users.index') }}" class="btn btn-default">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="pos-panel">
            <div class="pos-panel-head">
                <h3 class="box-title">Reset Password</h3>
            </div>
            <form method="POST" action="{{ route('users.reset-password', $userRow->id) }}">
                @csrf
                <div class="pos-panel-body">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="new_password_confirmation" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="pos-page-foot">
                    <button class="btn btn-warning" type="submit"><i class="fa fa-key"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection
