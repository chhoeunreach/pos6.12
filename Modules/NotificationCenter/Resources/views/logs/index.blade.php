@extends('layouts.app')
@section('title', 'Notification Logs')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-7xl">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
        <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800">Notification Logs</h1>
    </div>
    <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-overflow-x-auto">
        <table class="tw-w-full tw-text-sm">
            <thead class="tw-bg-gray-50 tw-border-b">
                <tr>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">#</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Module</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Reference</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Group</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Message</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Status</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Sent At</th>
                    <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="tw-divide-y">
                @forelse($logs as $log)
                <tr class="hover:tw-bg-gray-50 @if($log->status === 'failed') tw-bg-red-50 @endif">
                    <td class="tw-px-4 tw-py-3 tw-text-gray-400">{{ $log->id }}</td>
                    <td class="tw-px-4 tw-py-3">{{ $log->module_type }}</td>
                    <td class="tw-px-4 tw-py-3">
                        <span class="tw-text-xs tw-bg-gray-100 tw-rounded tw-px-1 tw-py-0.5">{{ $log->reference_type }}#{{ $log->reference_id }}</span>
                        @if($log->reference_no)<br><span class="tw-text-xs tw-text-gray-400">{{ $log->reference_no }}</span>@endif
                    </td>
                    <td class="tw-px-4 tw-py-3">{{ $log->group->name ?? 'N/A' }}</td>
                    <td class="tw-px-4 tw-py-3 tw-max-w-xs tw-truncate" title="{{ $log->message }}">{{ \Illuminate\Support\Str::limit($log->message, 80) }}</td>
                    <td class="tw-px-4 tw-py-3">
                        @if($log->status === 'sent')<span class="tw-text-green-600">Sent</span>
                        @elseif($log->status === 'failed')<span class="tw-text-red-600">Failed</span>
                        @elseif($log->status === 'pending')<span class="tw-text-yellow-600">Pending</span>
                        @else<span class="tw-text-gray-400">{{ $log->status }}</span>
                        @endif
                        @if($log->error_message)<br><span class="tw-text-xs tw-text-red-400">{{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</span>@endif
                    </td>
                    <td class="tw-px-4 tw-py-3 tw-text-gray-500 tw-text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    <td class="tw-px-4 tw-py-3 tw-text-right">
                        @if($log->status === 'failed')
                        <form action="{{ route('notificationcenter.logs.retry', $log->id) }}" method="POST" class="tw-inline">
                            @csrf
                            <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-warning">Retry</button>
                        </form>
                        @else
                        <span class="tw-text-gray-300 tw-text-xs">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="tw-mt-3">{{ $logs->links() }}</div>
</div>
@endsection
