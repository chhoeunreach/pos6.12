@extends('social::layouts.master')
@section('page_title', 'Social Platform — Moderation Reports')
@section('module_content')
<div class="box box-warning">
    <div class="box-header with-border">
        <h3 class="box-title">User Reports Queue (<code>social.social_reports</code>)</h3>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Target</th>
                    <th>Reason</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>#{{ $report->id }}</td>
                        <td>{{ strtoupper($report->target_type) }} #{{ $report->target_id }}</td>
                        <td><span class="label label-warning">{{ $report->reason }}</span></td>
                        <td>{{ $report->details ?: '-' }}</td>
                        <td>
                            <span class="label label-{{ $report->status === 'open' ? 'danger' : ($report->status === 'reviewed' ? 'success' : 'default') }}">
                                {{ strtoupper($report->status) }}
                            </span>
                        </td>
                        <td>
                            @if($report->status === 'open')
                                <form method="post" action="{{ route('social.admin.reports.resolve', $report->id) }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="action" value="hide_target">
                                    <button class="btn btn-xs btn-danger">Hide Target</button>
                                </form>
                                <form method="post" action="{{ route('social.admin.reports.resolve', $report->id) }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="action" value="dismiss">
                                    <button class="btn btn-xs btn-default">Dismiss</button>
                                </form>
                            @else
                                <small class="text-muted">Resolved</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:24px;">No moderation reports yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
