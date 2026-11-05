@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.audit_logs'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Forensic Financial Audit Trail'])

    <!-- Filters -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6 no-print">
        <div class="box-body tw-p-4">
            <form action="{{ route('daily-financial.audit.index') }}" method="GET" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                <div class="tw-w-[150px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" />
                </div>
                <div class="tw-w-[150px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control" />
                </div>
                <div class="tw-min-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Action</label>
                    <input type="text" name="action" value="{{ request('action') }}" class="form-control" placeholder="e.g. pos_sync, approve" />
                </div>
                <div class="tw-min-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">User</label>
                    <select name="user_id" class="form-control select2">
                        <option value="">-- All Users --</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->user_full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('daily-financial.audit.index') }}" class="btn btn-default">Reset</a>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Immutable Financial Activity Audit Records</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Target Entity</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Remarks / Change Summary</th>
                            <th class="tw-text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="tw-font-mono tw-text-xs">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td><span class="label label-primary">{{ strtoupper($log->action) }}</span></td>
                                <td>{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td>
                                <td>{{ $log->user ? $log->user->user_full_name : 'System' }}</td>
                                <td class="tw-font-mono tw-text-xs">{{ $log->ip_address ?: '-' }}</td>
                                <td>{{ $log->remarks ?: '-' }}</td>
                                <td class="tw-text-center">
                                    @if ($log->old_values || $log->new_values)
                                        <button type="button" class="btn btn-xs btn-default" data-toggle="collapse" data-target="#log-diff-{{ $log->id }}">
                                            <i class="fa fa-code"></i> View Diff
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @if ($log->old_values || $log->new_values)
                                <tr id="log-diff-{{ $log->id }}" class="collapse tw-bg-gray-50">
                                    <td colspan="7" class="tw-p-4">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="tw-font-bold tw-text-xs tw-text-red-700">Old State:</h5>
                                                <pre class="tw-text-xs tw-bg-white tw-p-2 tw-rounded tw-border">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h5 class="tw-font-bold tw-text-xs tw-text-green-700">New State:</h5>
                                                <pre class="tw-text-xs tw-bg-white tw-p-2 tw-rounded tw-border">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="tw-text-center tw-text-gray-400 tw-py-6">No audit records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $logs->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

