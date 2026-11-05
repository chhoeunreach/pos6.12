@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.sync_pos_data'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'POS & Installment Financial Data Synchronizer'])

    <!-- Sync Trigger Box -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-mb-6">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">
                <i class="fa fa-refresh tw-mr-1"></i> Run Data Synchronization
            </h3>
        </div>
        <div class="box-body tw-p-6">
            <p class="tw-text-sm tw-text-gray-600 tw-mb-4">
                This process performs a <strong>purely read-only extraction</strong> from UltimatePOS (sales, returns, payments, expenses) and LoanManagement (installment principal and interest collections). It is <strong>100% safe</strong>, non-destructive, and enforces duplicate prevention via composite database unique constraints.
            </p>

            <form action="{{ route('daily-financial.sync.trigger') }}" method="POST" class="tw-flex tw-flex-wrap tw-items-end tw-gap-4">
                @csrf
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">Start Date (Optional)</label>
                    <input type="date" name="start_date" class="form-control" />
                    <small class="text-muted">Leave blank for incremental</small>
                </div>
                <div class="tw-w-[160px]">
                    <label class="tw-text-xs tw-font-semibold tw-text-gray-600">End Date (Optional)</label>
                    <input type="date" name="end_date" value="{{ date('Y-m-d') }}" class="form-control" />
                </div>
                <div>
                    <label class="tw-cursor-pointer tw-text-xs tw-font-semibold tw-text-gray-600 tw-block">
                        <input type="checkbox" name="force" value="1" /> Force re-check
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-cloud-download"></i> Start Financial Synchronization
                </button>
            </form>
        </div>
    </div>

    <!-- Sync Batch History -->
    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Synchronization Batch Logs</h3>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Batch ID</th>
                            <th>Date / Time</th>
                            <th>Module</th>
                            <th>Sync Type</th>
                            <th class="tw-text-right">Processed</th>
                            <th class="tw-text-right">Imported</th>
                            <th class="tw-text-right">Skipped (Existing)</th>
                            <th class="tw-text-center">Status</th>
                            <th>Executed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($imports as $imp)
                            <tr>
                                <td class="tw-font-mono tw-font-bold">#{{ $imp->id }}</td>
                                <td>{{ $imp->created_at->format('Y-m-d H:i:s') }}</td>
                                <td><span class="label label-info">{{ strtoupper($imp->source_module) }}</span></td>
                                <td>{{ ucfirst($imp->sync_type) }}</td>
                                <td class="tw-text-right">{{ number_format($imp->records_processed) }}</td>
                                <td class="tw-text-right tw-font-bold tw-text-green-700">{{ number_format($imp->records_imported) }}</td>
                                <td class="tw-text-right tw-text-gray-500">{{ number_format($imp->records_skipped) }}</td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $imp->status === 'completed' ? 'bg-green' : ($imp->status === 'running' ? 'bg-orange' : 'bg-red') }}">
                                        {{ strtoupper($imp->status) }}
                                    </span>
                                </td>
                                <td>{{ $imp->executor ? $imp->executor->user_full_name : 'System Cron' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tw-text-center tw-text-gray-400 tw-py-6">No synchronization batches run yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $imports->links() }}
        </div>
    </div>
</div>
@endsection

