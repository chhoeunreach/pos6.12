@extends('loanmanagement::layouts.app')
@section('title', 'Edit Loan Customer')

@section('content_body')
<section class="content-header">
    <h1>Edit Loan Customer</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.customers.update', $customerRow->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="create_mode" id="create_mode" value="new">
                @include('loanmanagement::customers.partials.form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('loan-management.customers') }}" class="btn btn-default">Cancel</a>
            </form>
            <hr>
            <form method="POST" action="{{ route('loan-management.customers.reset-password', $customerRow->id) }}" class="form-inline">
                @csrf
                <div class="form-group">
                    <label style="margin-right:8px;">Reset App Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="New password (min 8)" required>
                </div>
                <button type="submit" class="btn btn-warning">Reset Password</button>
            </form>
            <hr>
            <form method="POST" action="{{ route('loan-management.customer-tracking.toggle', $customerRow->id) }}" class="form-inline">
                @csrf
                <div class="form-group">
                    <label style="margin-right:8px;">GPS Tracking</label>
                    <select class="form-control" name="allow_gps_tracking">
                        <option value="0" {{ !empty($customerRow->allow_gps_tracking) ? '' : 'selected' }}>Disable</option>
                        <option value="1" {{ !empty($customerRow->allow_gps_tracking) ? 'selected' : '' }}>Enable</option>
                    </select>
                </div>
                <div class="form-group" style="margin-left:8px;">
                    <input type="text" class="form-control" name="note" placeholder="Note">
                </div>
                <button type="submit" class="btn btn-info">Save GPS Setting</button>
            </form>
        </div>
    </div>
</section>
@endsection
