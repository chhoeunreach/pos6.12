@extends('loanmanagement::layouts.app')

@section('title', 'Activity Logs')

@section('content_body')
    <div class="lm-page-header" style="margin-bottom: 18px;">
        <div>
            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #0f172a;">Activity Logs</h1>
            <p style="margin: 6px 0 0; color: #64748b;">Loan Management activity history</p>
        </div>
    </div>

    <div class="row" style="margin-bottom: 16px;">
        <div class="col-md-3 col-sm-6">
            <div class="lm-card" style="padding: 16px;">
                <div style="color:#64748b;text-transform:uppercase;font-size:12px;font-weight:700;">Total Activity</div>
                <div style="font-size:28px;font-weight:800;color:#0f172a;">{{ number_format($summary['total']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="lm-card" style="padding: 16px;">
                <div style="color:#64748b;text-transform:uppercase;font-size:12px;font-weight:700;">Recorded Activity</div>
                <div style="font-size:28px;font-weight:800;color:#0f172a;">{{ number_format($summary['recorded']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="lm-card" style="padding: 16px;">
                <div style="color:#64748b;text-transform:uppercase;font-size:12px;font-weight:700;">Loan Records</div>
                <div style="font-size:28px;font-weight:800;color:#0f172a;">{{ number_format($summary['loans']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="lm-card" style="padding: 16px;">
                <div style="color:#64748b;text-transform:uppercase;font-size:12px;font-weight:700;">Payments</div>
                <div style="font-size:28px;font-weight:800;color:#0f172a;">{{ number_format($summary['payments']) }}</div>
            </div>
        </div>
    </div>

    <div class="lm-card" style="padding: 16px; margin-bottom: 16px;">
        <form method="GET" action="{{ route('loan-management.activity-logs.index') }}">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="activity_search">Search</label>
                        <input type="text" class="form-control" id="activity_search" name="search"
                               value="{{ $filters['search'] }}" placeholder="Loan, customer, user, status...">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="activity_event">Activity Type</label>
                        <select class="form-control" id="activity_event" name="event">
                            <option value="">All activity</option>
                            @foreach($eventOptions as $event)
                                <option value="{{ $event }}" {{ $filters['event'] === $event ? 'selected' : '' }}>{{ $event }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="activity_date_from">From</label>
                        <input type="date" class="form-control" id="activity_date_from" name="date_from"
                               value="{{ $filters['date_from'] }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="activity_date_to">To</label>
                        <input type="date" class="form-control" id="activity_date_to" name="date_to"
                               value="{{ $filters['date_to'] }}">
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            @if($filters['search'] || $filters['event'] || $filters['date_from'] || $filters['date_to'])
                <a href="{{ route('loan-management.activity-logs.index') }}" class="btn btn-default btn-sm">
                    <i class="fa fa-times"></i> Clear filters
                </a>
            @endif
        </form>
    </div>

    <div class="lm-card" style="overflow: hidden;">
        <div style="padding: 16px 18px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div>
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#0f172a;">Loan Management Timeline</h3>
                <p style="margin:4px 0 0;color:#64748b;">Latest recorded activity from this module</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 160px;">Date</th>
                        <th style="width: 180px;">Activity</th>
                        <th style="width: 170px;">Reference</th>
                        <th>Details</th>
                        <th style="width: 150px;">User</th>
                        <th style="width: 110px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <span style="font-weight:600;color:#0f172a;">{{ $log['occurred_at'] }}</span>
                                <div style="font-size:12px;color:#94a3b8;">{{ $log['source'] }}</div>
                            </td>
                            <td>
                                <span class="label label-info" style="display:inline-block;padding:6px 8px;">{{ $log['event'] }}</span>
                            </td>
                            <td style="font-weight:600;color:#334155;">{{ $log['reference'] }}</td>
                            <td style="color:#334155;">{{ $log['details'] }}</td>
                            <td>{{ $log['actor'] }}</td>
                            <td>
                                <span class="label label-default" style="display:inline-block;padding:6px 8px;">{{ $log['status'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 40px; color:#64748b;">
                                <i class="fa fa-history fa-2x" style="display:block;margin-bottom:10px;color:#cbd5e1;"></i>
                                No activity found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div style="padding: 14px 18px; border-top: 1px solid #e5e7eb;">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
