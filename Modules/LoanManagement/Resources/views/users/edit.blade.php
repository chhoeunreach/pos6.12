@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan User')

@section('content_body')
<section class="content-header">
    <h1>Edit Loan User</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.users.update', $userRow->id) }}">
                @csrf
                @method('PUT')
                @include('loanmanagement::users.partials.form', ['userRow' => $userRow])
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="{{ route('loan-management.users.show', $userRow->id) }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>

    <div class="box box-warning">
        <div class="box-header with-border">
            <h3 class="box-title">Reset Password</h3>
        </div>
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.users.reset-password', $userRow->id) }}" class="form-inline">
                @csrf
                <div class="form-group">
                    <label style="margin-right:8px;">New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="New password (min 6)" required>
                </div>
                <div class="form-group" style="margin-left:8px;">
                    <label style="margin-right:8px;">Confirm</label>
                    <input type="password" class="form-control" name="new_password_confirmation" placeholder="Confirm password" required>
                </div>
                <button type="submit" class="btn btn-warning">Reset Password</button>
            </form>
        </div>
    </div>
</section>
@endsection
