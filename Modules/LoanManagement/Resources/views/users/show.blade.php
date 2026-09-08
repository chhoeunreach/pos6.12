@extends('loanmanagement::layouts.app')

@section('title', 'User Detail')

@section('loan_css')
    @include('loanmanagement::standalone.partials.admin_ui_css')
    <style>
        .lm-profile-head {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .lm-profile-avatar {
            width: 54px;
            height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            color: #2563eb;
            font-size: 22px;
            font-weight: 800;
        }
        .lm-profile-head h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }
        .lm-profile-head p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 12px;
        }
        .lm-detail-table th {
            width: 165px;
            color: #475569;
            background: #f8fafc;
        }
    </style>
@endsection

@section('content_body')
@php
    $displayName = $userRow->name ?: trim(($userRow->first_name ?? '').' '.($userRow->last_name ?? '')) ?: $userRow->username ?: 'User';
    $initial = strtoupper(mb_substr($displayName, 0, 1));
    $status = $userRow->status ?? 'active';
@endphp

<div class="pos-admin-page">
    <div class="pos-page-head">
        <div class="pos-page-title">
            <h1>User Detail</h1>
            <p>Review account access, role assignment, and login status.</p>
        </div>
        <div class="pos-action-row">
            @can('user.update')
                <a href="{{ route('loan-management.users.edit', $userRow->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-edit"></i> Edit
                </a>
            @endcan
            <a href="{{ route('loan-management.users.index') }}" class="btn btn-default btn-sm">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="pos-panel">
                <div class="pos-panel-head">
                    <div class="lm-profile-head">
                        <span class="lm-profile-avatar">{{ $initial }}</span>
                        <div>
                            <h3>{{ $displayName }}</h3>
                            <p>{{ $userRow->username ?? '-' }}</p>
                        </div>
                    </div>
                    <span class="pos-badge {{ $status === 'active' ? 'pos-badge-success' : 'pos-badge-muted' }}">{{ ucfirst($status) }}</span>
                </div>
                <div class="pos-panel-body table-responsive">
                    <table class="table table-bordered lm-detail-table">
                        <tr><th>ID</th><td>{{ $userRow->id }}</td></tr>
                        <tr><th>First Name</th><td>{{ $userRow->first_name ?? '-' }}</td></tr>
                        <tr><th>Last Name</th><td>{{ $userRow->last_name ?? '-' }}</td></tr>
                        <tr><th>Username</th><td>{{ $userRow->username ?? '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $userRow->email ?? '-' }}</td></tr>
                        <tr><th>Business ID</th><td>{{ $userRow->business_id ?? '-' }}</td></tr>
                        <tr>
                            <th>Login Access</th>
                            <td><span class="pos-badge {{ !empty($userRow->allow_login) ? 'pos-badge-success' : 'pos-badge-muted' }}">{{ !empty($userRow->allow_login) ? 'Allowed' : 'Blocked' }}</span></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td>{{ $userRow->relationLoaded('roles') ? ($userRow->roles->pluck('name')->implode(', ') ?: '-') : '-' }}</td>
                        </tr>
                        <tr><th>Created</th><td>{{ $userRow->created_at ?? '-' }}</td></tr>
                        <tr><th>Updated</th><td>{{ $userRow->updated_at ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            @can('user.update')
                <div class="pos-panel">
                    <div class="pos-panel-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="pos-panel-body">
                        @if(auth()->id() !== $userRow->id)
                            <form method="POST" action="{{ route('loan-management.users.toggle-status', $userRow->id) }}" style="margin-bottom: 12px;">
                                @csrf
                                <button type="submit" class="btn {{ $status === 'active' ? 'btn-warning' : 'btn-success' }} btn-block">
                                    <i class="fa fa-power-off"></i> {{ $status === 'active' ? 'Disable User' : 'Enable User' }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('loan-management.users.reset-password', $userRow->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" class="form-control" name="new_password" placeholder="New password" minlength="6" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" class="form-control" name="new_password_confirmation" placeholder="Confirm password" minlength="6" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-key"></i> Reset Password</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
@endsection
