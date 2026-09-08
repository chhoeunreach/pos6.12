@extends('loanmanagement::layouts.app')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@section('title', $lmText('Manage Users', 'គ្រប់គ្រងអ្នកប្រើប្រាស់'))

@section('loan_css')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css">
<style>
    /* =========================================================
       ULTIMATE POS STANDARD STYLE FOR USERS VIEW
       ========================================================= */
    .lm-users-content {
        font-family: 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* KPI Summary Cards */
    .lm-users-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    .lm-user-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .lm-user-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    }
    .lm-user-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .lm-user-stat-blue .lm-user-stat-icon { background: #eff6ff; color: #2563eb; }
    .lm-user-stat-green .lm-user-stat-icon { background: #ecfdf5; color: #16a34a; }
    .lm-user-stat-amber .lm-user-stat-icon { background: #fffbeb; color: #d97706; }
    .lm-user-stat-purple .lm-user-stat-icon { background: #faf5ff; color: #9333ea; }
    .lm-user-stat-copy small {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 2px;
    }
    .lm-user-stat-copy strong {
        display: block;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

    /* Filters Component Styling */
    .lm-pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr) auto;
        gap: 12px 14px;
        align-items: end;
        padding: 6px 0;
    }
    @media (max-width: 1200px) {
        .lm-pos-filter-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .lm-pos-filter-grid { grid-template-columns: 1fr; }
        .lm-users-stats-grid { grid-template-columns: 1fr; }
    }
    .lm-pos-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .lm-pos-filter-field label {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }
    .lm-pos-filter-field .form-control {
        height: 38px;
        padding: 6px 12px;
        font-size: 13px;
        color: #1e293b;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        outline: none;
        width: 100%;
        box-shadow: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .lm-pos-filter-field .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }
    .lm-pos-filter-field select.form-control {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 28px;
        -webkit-appearance: none;
        appearance: none;
    }

    .lm-pos-filter-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }
    .lm-btn-pos-filter {
        height: 38px;
        padding: 0 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #0284c7;
        color: #fff;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .lm-btn-pos-filter:hover { background: #0369a1; }
    .lm-btn-pos-reset {
        height: 38px;
        padding: 0 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .lm-btn-pos-reset:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }

    /* Ultimate POS DataTables Toolbar Layout */
    .lm-dt-top {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .lm-dt-length label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
        font-weight: 500 !important;
        font-size: 13px !important;
        color: #475569 !important;
    }
    .lm-dt-length select {
        height: 34px !important;
        padding: 2px 28px 2px 10px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        color: #1e293b !important;
        background-color: #fff !important;
        outline: none !important;
    }
    .lm-dt-buttons {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        flex-wrap: wrap !important;
    }
    .lm-dt-buttons .btn {
        border-radius: 6px !important;
        padding: 6px 12px !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        color: #334155 !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        transition: all 0.15s ease !important;
    }
    .lm-dt-buttons .btn:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .lm-dt-search {
        margin: 0 !important;
    }
    .lm-dt-search label {
        margin: 0 !important;
        display: block !important;
    }
    .lm-dt-search input {
        height: 34px !important;
        min-width: 220px !important;
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        outline: none !important;
        background: #ffffff !important;
        box-shadow: none !important;
        transition: border-color 0.15s ease !important;
    }
    .lm-dt-search input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
    }

    /* Table Styling */
    .lm-table-dense {
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
    }
    .lm-table-dense th {
        font-size: 12.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        color: #475569 !important;
        background: #f8fafc !important;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #cbd5e1 !important;
        padding: 10px 12px !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
    }
    .lm-table-dense td {
        font-size: 13px !important;
        color: #1e293b !important;
        padding: 10px 12px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-table-dense tbody tr:hover {
        background-color: #f8fafc !important;
    }

    /* User Cell Avatar */
    .pos-user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pos-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #eff6ff;
        color: #2563eb;
        font-weight: 800;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dbeafe;
    }

    /* Bottom Info & Pagination */
    .lm-dt-bottom {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        background: #ffffff !important;
        border-top: 1px solid #f1f5f9 !important;
    }
    .lm-dt-info {
        font-size: 13px !important;
        color: #64748b !important;
        padding: 0 !important;
    }
    .lm-dt-pagination .pagination {
        margin: 0 !important;
    }
    .lm-dt-pagination .pagination > li > a {
        border-radius: 4px !important;
        margin: 0 2px !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
    }
    .lm-dt-pagination .pagination > .active > a {
        background-color: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
    }
</style>
@endsection

@section('content_body')
<div class="lm-users-content">

    {{-- Content Header (Page header) --}}
    <section class="content-header" style="padding: 0 0 16px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <h1 style="font-size: 22px; font-weight: 700; color: #1e293b; margin: 0;">
            {{ $lmText('Users Management', 'គ្រប់គ្រងអ្នកប្រើប្រាស់') }}
            <small style="font-size: 13px; color: #64748b; font-weight: 400; margin-left: 8px;">
                {{ $lmText('Manage staff access, login credentials, and role permissions', 'គ្រប់គ្រងគណនីបុគ្គលិក សិទ្ធិប្រើប្រាស់ និងតួនាទី') }}
            </small>
        </h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            @can('roles.view')
                <a href="{{ route('loan-management.roles.index') }}" class="btn btn-default btn-sm" style="font-weight: 600; border-radius: 6px;">
                    <i class="fa fa-shield"></i> {{ $lmText('Roles', 'តួនាទី') }}
                </a>
            @endcan
            @can('user.create')
                <a href="{{ route('loan-management.users.import-template') }}" class="btn btn-default btn-sm" style="font-weight: 600; border-radius: 6px;">
                    <i class="fa fa-download"></i> {{ $lmText('Template', 'គំរូទិន្នន័យ') }}
                </a>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#usersImportModal" style="font-weight: 600; border-radius: 6px;">
                    <i class="fa fa-upload"></i> {{ $lmText('Import', 'នាំចូល') }}
                </button>
            @endcan
            @can('user.view')
                <a href="{{ route('loan-management.users.export') }}" class="btn btn-default btn-sm" style="font-weight: 600; border-radius: 6px;">
                    <i class="fa fa-file-excel-o"></i> {{ $lmText('Export', 'នាំចេញ') }}
                </a>
            @endcan
            @can('user.create')
                <a href="{{ route('loan-management.users.create') }}" class="btn btn-primary btn-sm" style="font-weight: 700; border-radius: 6px;">
                    <i class="fa fa-plus-circle"></i> {{ $lmText('Add User', 'បន្ថែមអ្នកប្រើ') }}
                </a>
            @endcan
        </div>
    </section>

    {{-- Status Alerts --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible" style="border-radius: 8px; margin-bottom: 16px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-exclamation-triangle"></i> {{ $errors->first() }}
        </div>
    @endif
    @php
        $status = session('status');
        $statusMessage = is_array($status) ? data_get($status, 'msg') : $status;
        $statusSuccess = is_array($status) ? data_get($status, 'success', 1) : 1;
    @endphp
    @if($statusMessage)
        <div class="alert alert-{{ $statusSuccess ? 'success' : 'warning' }} alert-dismissible" style="border-radius: 8px; margin-bottom: 16px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="fa fa-check-circle"></i> {{ $statusMessage }}
        </div>
    @endif

    {{-- 4 KPI Metric Cards --}}
    <div class="lm-users-stats-grid">
        <div class="lm-user-stat-card lm-user-stat-blue">
            <div class="lm-user-stat-icon"><i class="fa fa-users"></i></div>
            <div class="lm-user-stat-copy">
                <small>{{ $lmText('Total Users', 'អ្នកប្រើប្រាស់សរុប') }}</small>
                <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-user-stat-card lm-user-stat-green">
            <div class="lm-user-stat-icon"><i class="fa fa-user-plus"></i></div>
            <div class="lm-user-stat-copy">
                <small>{{ $lmText('Active', 'សកម្ម') }}</small>
                <strong>{{ number_format($stats['active'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-user-stat-card lm-user-stat-amber">
            <div class="lm-user-stat-icon"><i class="fa fa-user-times"></i></div>
            <div class="lm-user-stat-copy">
                <small>{{ $lmText('Inactive', 'អសកម្ម') }}</small>
                <strong>{{ number_format($stats['inactive'] ?? 0) }}</strong>
            </div>
        </div>
        <div class="lm-user-stat-card lm-user-stat-purple">
            <div class="lm-user-stat-icon"><i class="fa fa-sign-in"></i></div>
            <div class="lm-user-stat-copy">
                <small>{{ $lmText('Login Enabled', 'អនុញ្ញាតចូលប្រើ') }}</small>
                <strong>{{ number_format($stats['login_enabled'] ?? 0) }}</strong>
            </div>
        </div>
    </div>

    {{-- Ultimate POS Standard Collapsible Filters Component --}}
    @component('components.filters', ['title' => __('report.filters'), 'closed' => true])
        <form method="GET" action="{{ route('loan-management.users.index') }}" id="usersFilterForm">
            <div class="lm-pos-filter-grid">
                {{-- Search Name --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Full Name', 'ឈ្មោះ') }}</label>
                    <input class="form-control" name="name" placeholder="{{ $lmText('Search name...', 'ស្វែងរកឈ្មោះ...') }}" value="{{ request('name') }}">
                </div>

                {{-- Username --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Username', 'ឈ្មោះគណនី') }}</label>
                    <input class="form-control" name="username" placeholder="{{ $lmText('Username...', 'ឈ្មោះគណនី...') }}" value="{{ request('username') }}">
                </div>

                {{-- Email --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Email Address', 'អ៊ីមែល') }}</label>
                    <input class="form-control" name="email" placeholder="{{ $lmText('Email address...', 'អ៊ីមែល...') }}" value="{{ request('email') }}">
                </div>

                {{-- Role --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Role', 'តួនាទី') }}</label>
                    <select class="form-control" name="role">
                        <option value="">{{ $lmText('All Roles', 'គ្រប់តួនាទី') }}</option>
                        @foreach($roles as $id => $name)
                            <option value="{{ $id }}" {{ (string) request('role') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div class="lm-pos-filter-field">
                    <label>{{ $lmText('Status', 'ស្ថានភាព') }}</label>
                    <select class="form-control" name="status">
                        <option value="">{{ $lmText('All Statuses', 'គ្រប់ស្ថានភាព') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                {{-- Action Buttons --}}
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $lmText('Apply', 'អនុវត្ត') }}
                    </button>
                    <a href="{{ route('loan-management.users.index') }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'សម្អាត') }}
                    </a>
                </div>
            </div>
        </form>
    @endcomponent

    {{-- Ultimate POS Standard Widget Component --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => $lmText('All System Users', 'បញ្ជីអ្នកប្រើប្រាស់ប្រព័ន្ធទាំងអស់')])
        <div class="table-responsive">
            <table class="lm-table-dense table table-bordered table-striped table-hover" id="usersTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th>{{ $lmText('User Details', 'ព័ត៌មានអ្នកប្រើ') }}</th>
                        <th>{{ $lmText('Email', 'អ៊ីមែល') }}</th>
                        <th>{{ $lmText('Role', 'តួនាទី') }}</th>
                        <th style="text-align: center;">{{ $lmText('Login Access', 'ការចូលប្រើ') }}</th>
                        <th style="text-align: center;">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                        <th style="width: 160px; text-align: center;" class="no-export">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $displayName = $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                            $initial = strtoupper(mb_substr($displayName ?: $user->username, 0, 1));
                        @endphp
                        <tr>
                            <td>
                                <div class="pos-user-cell">
                                    <span class="pos-avatar">{{ $initial }}</span>
                                    <div>
                                        <strong style="color: #0f172a;">{{ $displayName ?: '-' }}</strong>
                                        <div style="font-size: 11px; color: #64748b;">{{ $user->username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->email ?: '-' }}</td>
                            <td>
                                <span class="label label-info" style="font-size: 11px;">
                                    {{ $user->relationLoaded('roles') ? ($user->roles->pluck('name')->implode(', ') ?: '-') : '-' }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="label label-{{ !empty($user->allow_login) ? 'success' : 'default' }}" style="font-size: 10.5px;">
                                    {{ !empty($user->allow_login) ? 'Allowed' : 'Blocked' }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="label label-{{ ($user->status ?? 'active') === 'active' ? 'success' : 'danger' }}" style="font-size: 10.5px; text-transform: uppercase;">
                                    {{ ucfirst($user->status ?? 'active') }}
                                </span>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div class="btn-group" style="display: inline-flex; gap: 3px;">
                                    @can('user.update')
                                        <a href="{{ route('loan-management.users.edit', $user->id) }}" class="btn btn-xs btn-primary" title="{{ $lmText('Edit User', 'កែប្រែ') }}">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        @if(auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('loan-management.users.toggle-status', $user->id) }}" style="display: inline;">
                                                @csrf
                                                <button class="btn btn-xs btn-warning" type="submit" title="{{ ($user->status ?? 'active') === 'active' ? 'Disable' : 'Enable' }}">
                                                    <i class="fa fa-power-off"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('user.delete')
                                        @if(auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('loan-management.users.destroy', $user->id) }}" style="display: inline;" onsubmit="return confirm('{{ $lmText('Are you sure you want to delete this user?', 'តើអ្នកប្រាកដជាចង់លុបអ្នកប្រើនេះ?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-xs btn-danger" type="submit" title="{{ $lmText('Delete User', 'លុប') }}">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div style="margin-top: 10px;">
                {{ $users->links() }}
            </div>
        @endif
    @endcomponent

</div>

{{-- MODAL: IMPORT USERS --}}
@can('user.create')
    <div class="modal fade" id="usersImportModal" tabindex="-1" role="dialog" aria-labelledby="usersImportModalLabel">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('loan-management.users.import') }}" enctype="multipart/form-data" class="modal-content" style="border-radius: 10px; overflow: hidden;">
                @csrf
                <div class="modal-header" style="background: #1e293b; color: #fff;">
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: .8;">&times;</button>
                    <h4 class="modal-title" id="usersImportModalLabel" style="font-weight: 800; font-size: 16px;">
                        <i class="fa fa-upload text-primary"></i> {{ $lmText('Import Users via Spreadsheet', 'នាំចូលអ្នកប្រើតាម Excel') }}
                    </h4>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group">
                        <label style="font-weight: 700;">{{ $lmText('Select File (.xlsx, .csv)', 'ជ្រើសរើសឯកសារ (.xlsx, .csv)') }} <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" required accept=".csv,.xlsx,.xls">
                    </div>
                    <div class="alert alert-info" style="margin-bottom: 0; font-size: 12px; border-radius: 6px;">
                        <i class="fa fa-info-circle"></i>
                        {{ $lmText('Make sure your file matches the template columns before uploading.', 'សូមប្រាកដថាទិន្នន័យត្រូវគ្នានឹងទម្រង់គំរូមុនពេលផ្ទុកឡើង។') }}
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $lmText('Cancel', 'បោះបង់') }}</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                        <i class="fa fa-upload"></i> {{ $lmText('Upload & Import', 'ផ្ទុកឡើង និងនាំចូល') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endcan
@endsection

@section('loan_js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function(){
    // Initialize DataTables with Ultimate POS standard toolbar
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#usersTable')) {
        var tableButtons = [];
        if ($.fn.dataTable.Buttons) {
            tableButtons = [
                {
                    extend: 'copy',
                    text: 'Copy',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible:not(.no-export)' }
                },
                {
                    extend: 'csv',
                    text: '<i class="fa fa-file-text-o"></i> Export CSV',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible:not(.no-export)' }
                },
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Export Excel',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible:not(.no-export)' }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    className: 'btn btn-default btn-sm',
                    exportOptions: { columns: ':visible:not(.no-export)', stripHtml: true }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fa fa-columns"></i> Column visibility',
                    className: 'btn btn-default btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> Export PDF <i class="fa fa-caret-down" style="margin-left:2px;"></i>',
                    className: 'btn btn-default btn-sm',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: { columns: ':visible:not(.no-export)' }
                }
            ];
        }

        $('#usersTable').DataTable({
            dom: '<"lm-dt-top"<"lm-dt-length"l><"lm-dt-buttons"B><"lm-dt-search"f>>rt<"lm-dt-bottom"<"lm-dt-info"i><"lm-dt-pagination"p>>',
            buttons: tableButtons,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, 250, -1], [10, 25, 50, 100, 250, "{{ $lmText('All', 'ទាំងអស់') }}"]],
            order: [[0, 'asc']],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: 'Search ...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: '{{ $lmText("No users found.", "មិនមានទិន្នន័យអ្នកប្រើប្រាស់ទេ។") }}',
                info: '{{ $lmText("Showing _START_ to _END_ of _TOTAL_ entries", "បង្ហាញពី _START_ ដល់ _END_ នៃ _TOTAL_ ធាតុ") }}',
                infoEmpty: '{{ $lmText("Showing 0 to 0 of 0 entries", "បង្ហាញ 0 នៃ 0 ធាតុ") }}',
                infoFiltered: '({{ $lmText("filtered from _MAX_ total entries", "ចម្រាញ់ចេញពី _MAX_ ធាតុសរុប") }})',
                paginate: {
                    first: '{{ $lmText("First", "ដំបូង") }}',
                    last: '{{ $lmText("Last", "ចុងក្រោយ") }}',
                    next: '{{ $lmText("Next", "បន្ទាប់") }}',
                    previous: '{{ $lmText("Previous", "មុន") }}'
                }
            },
            columnDefs: [
                { targets: [5], orderable: false, className: 'no-export' },
                { targets: [3, 4, 5], className: 'text-center' }
            ]
        });
    }
});
</script>
@endsection
