@extends('loanmanagement::layouts.app')
@section('title', 'Loan Customers')

@section('content')
<section class="content-header">
    <h1>Loan Customers</h1>
</section>

<section class="content">
    @if(!$tableExists)
        <div class="alert alert-danger">`loan_customers` table not found in `mysql_loan` database.</div>
    @else
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">All Loan Customers</h3>
                <div class="box-tools">
                    @can('loan_management.create')
                        <a href="{{ route('loan-management.customers.create') }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus"></i> Add
                        </a>
                    @endcan
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('loan-management.customers') }}" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <input type="text" name="name" class="form-control" value="{{ request('name') }}" placeholder="Name">
                    </div>
                    <div class="form-group">
                        <input type="text" name="phone" class="form-control" value="{{ request('phone') }}" placeholder="Phone">
                    </div>
                    <div class="form-group">
                        <input type="text" name="customer_code" class="form-control" value="{{ request('customer_code') }}" placeholder="Customer Code">
                    </div>
                    <button type="submit" class="btn btn-default">Filter</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Can Login</th>
                                <th>GPS</th>
                                <th>Status</th>
                                <th>Blacklist</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $c)
                                <tr>
                                    <td>
                                        <a href="{{ route('loan-management.customers.show', $c->id) }}" class="btn btn-xs btn-info">View</a>
                                        @can('loan_management.edit')
                                            <a href="{{ route('loan-management.customers.edit', $c->id) }}" class="btn btn-xs btn-primary">Edit</a>
                                        @endcan
                                        @can('loan_management.delete')
                                            <form method="POST" action="{{ route('loan-management.customers.destroy', $c->id) }}" style="display:inline;" onsubmit="return confirm('Delete this customer?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                            </form>
                                        @endcan
                                    </td>
                                    <td>{{ $c->id }}</td>
                                    <td>{{ $c->customer_code ?? '-' }}</td>
                                    <td>{{ $c->name ?? $c->customer_name ?? '-' }}</td>
                                    <td>{{ $c->phone ?? '-' }}</td>
                                    <td>{{ $c->business_location_name_snapshot ?? '-' }}</td>
                                    <td>{!! !empty($c->can_login) ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                                    <td>{!! !empty($c->allow_gps_tracking) ? '<span class="label label-success">On</span>' : '<span class="label label-default">Off</span>' !!}</td>
                                    <td>{{ $c->status ?? '-' }}</td>
                                    <td>{{ !empty($c->blacklist_status) ? 'Yes' : 'No' }}</td>
                                    <td>{{ $c->created_at ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center">No loan customers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($customers instanceof \Illuminate\Contracts\Pagination\Paginator || $customers instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    {{ $customers->links() }}
                @endif
            </div>
        </div>
    @endif
</section>
@endsection
