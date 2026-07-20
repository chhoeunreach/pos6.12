@extends('loanmanagement::layouts.app')
@section('title', 'Loan Users')

@section('content_body')
<section class="content-header">
    <h1>Loan Users</h1>
</section>

<section class="content">
    @if(!$tableExists)
        <div class="alert alert-danger">`loan_users` table not found in `mysql_loan` database.</div>
    @else
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">All Loan Users</h3>
                <div class="box-tools">
                    @can('loan_management.create')
                        <a href="{{ route('loan-management.users.create') }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add User
                        </a>
                    @endcan
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('loan-management.users.index') }}" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <input type="text" name="name" class="form-control" value="{{ request('name') }}" placeholder="Name">
                    </div>
                    <div class="form-group">
                        <input type="text" name="username" class="form-control" value="{{ request('username') }}" placeholder="Username">
                    </div>
                    <div class="form-group">
                        <input type="text" name="email" class="form-control" value="{{ request('email') }}" placeholder="Email">
                    </div>
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-default">Filter</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $u)
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="{{ route('loan-management.users.show', $u->id) }}" class="btn btn-xs btn-info">View</a>
                                        @can('loan_management.edit')
                                            <a href="{{ route('loan-management.users.edit', $u->id) }}" class="btn btn-xs btn-primary">Edit</a>
                                        @endcan
                                        @can('loan_management.edit')
                                            <form method="POST" action="{{ route('loan-management.users.toggle-status', $u->id) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs {{ ($u->status ?? 'active') === 'active' ? 'btn-warning' : 'btn-success' }}">
                                                    {{ ($u->status ?? 'active') === 'active' ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('loan_management.delete')
                                            <form method="POST" action="{{ route('loan-management.users.destroy', $u->id) }}" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                            </form>
                                        @endcan
                                    </td>
                                    <td>{{ $u->id }}</td>
                                    <td><strong>{{ $u->name ?? '-' }}</strong></td>
                                    <td>{{ $u->username ?? '-' }}</td>
                                    <td>{{ $u->email ?? '-' }}</td>
                                    <td>{{ $u->phone ?? '-' }}</td>
                                    <td>
                                        @if(($u->status ?? 'active') === 'active')
                                            <span class="label label-success">Active</span>
                                        @else
                                            <span class="label label-default">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $u->last_login_at ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">No loan users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($users instanceof \Illuminate\Contracts\Pagination\Paginator || $users instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    {{ $users->links() }}
                @endif
            </div>
        </div>
    @endif
</section>
@endsection
