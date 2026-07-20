@extends('loanmanagement::layouts.app')
@section('title', 'Loan User Detail')

@section('content_body')
<section class="content-header">
    <h1>Loan User Detail</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $userRow->name ?? 'User' }}</h3>
            <div class="box-tools pull-right">
                @can('loan_management.edit')
                    <a href="{{ route('loan-management.users.edit', $userRow->id) }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-edit"></i> Edit
                    </a>
                @endcan
                <a href="{{ route('loan-management.users.index') }}" class="btn btn-default btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr><th style="width:160px;">ID</th><td>{{ $userRow->id }}</td></tr>
                        <tr><th>Name</th><td>{{ $userRow->name ?? '-' }}</td></tr>
                        <tr><th>Username</th><td>{{ $userRow->username ?? '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $userRow->email ?? '-' }}</td></tr>
                        <tr><th>Phone</th><td>{{ $userRow->phone ?? '-' }}</td></tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if(($userRow->status ?? 'active') === 'active')
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-default">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Last Login</th><td>{{ $userRow->last_login_at ?? 'Never' }}</td></tr>
                        <tr><th>Created</th><td>{{ $userRow->created_at ?? '-' }}</td></tr>
                        <tr><th>Updated</th><td>{{ $userRow->updated_at ?? '-' }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    @can('loan_management.edit')
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title">Quick Actions</h3>
                            </div>
                            <div class="box-body">
                                <form method="POST" action="{{ route('loan-management.users.toggle-status', $userRow->id) }}" style="margin-bottom:10px;">
                                    @csrf
                                    <button type="submit" class="btn {{ ($userRow->status ?? 'active') === 'active' ? 'btn-warning' : 'btn-success' }}">
                                        {{ ($userRow->status ?? 'active') === 'active' ? 'Deactivate User' : 'Activate User' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('loan-management.users.reset-password', $userRow->id) }}" class="form-inline">
                                    @csrf
                                    <div class="form-group">
                                        <input type="password" class="form-control" name="new_password" placeholder="New password" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" class="form-control" name="new_password_confirmation" placeholder="Confirm" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning">Reset Password</button>
                                </form>
                            </div>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @if($recentLoans->isNotEmpty())
    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Recent Loans Created</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loan Number</th>
                        <th>Status</th>
                        <th>Principal</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentLoans as $loan)
                        <tr>
                            <td>{{ $loan->id }}</td>
                            <td>{{ $loan->loan_number ?? '-' }}</td>
                            <td><span class="label label-info">{{ ucfirst($loan->status ?? 'pending') }}</span></td>
                            <td>{{ number_format((float) ($loan->principal_amount ?? 0), 2) }}</td>
                            <td>{{ $loan->created_at ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</section>
@endsection
