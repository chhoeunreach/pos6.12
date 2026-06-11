@extends('layouts.app')
@section('title', 'Notification Groups')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-7xl">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
        <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800">Telegram Groups</h1>
        <a href="{{ route('notificationcenter.groups.create') }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary">+ New Group</a>
    </div>

    {{-- From Channels --}}
    <div class="tw-mb-6">
        <h2 class="tw-text-base tw-font-semibold tw-text-gray-700 tw-mb-2">From Channels</h2>
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border">
            <table class="tw-w-full tw-text-sm">
                <thead class="tw-bg-gray-50 tw-border-b">
                    <tr>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Location</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Chat ID</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Text</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">PDF</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Active</th>
                        <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="tw-divide-y">
                    @forelse($fromGroups as $group)
                    <tr class="hover:tw-bg-gray-50">
                        <td class="tw-px-4 tw-py-3">{{ $group->location_name ?: $group->name }}</td>
                        <td class="tw-px-4 tw-py-3"><code class="tw-text-xs tw-bg-gray-100 tw-px-1 tw-py-0.5 tw-rounded">{{ $group->chat_id }}</code></td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_text)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_pdf)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->active)<span class="tw-text-green-600">Active</span>@else<span class="tw-text-red-500">Inactive</span>@endif</td>
                        <td class="tw-px-4 tw-py-3 tw-text-right tw-space-x-1">
                            <a href="{{ route('notificationcenter.groups.edit', $group->id) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">Edit</a>
                            <form action="{{ route('notificationcenter.groups.test', $group->id) }}" method="POST" class="tw-inline">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success">Test</button>
                            </form>
                            <form action="{{ route('notificationcenter.groups.destroy', $group->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No from-channel groups configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- To Channels --}}
    <div class="tw-mb-6">
        <h2 class="tw-text-base tw-font-semibold tw-text-gray-700 tw-mb-2">To Channels</h2>
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border">
            <table class="tw-w-full tw-text-sm">
                <thead class="tw-bg-gray-50 tw-border-b">
                    <tr>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Location</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Chat ID</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Text</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">PDF</th>
                        <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Active</th>
                        <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="tw-divide-y">
                    @forelse($toGroups as $group)
                    <tr class="hover:tw-bg-gray-50">
                        <td class="tw-px-4 tw-py-3">{{ $group->location_name ?: $group->name }}</td>
                        <td class="tw-px-4 tw-py-3"><code class="tw-text-xs tw-bg-gray-100 tw-px-1 tw-py-0.5 tw-rounded">{{ $group->chat_id }}</code></td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_text)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->send_pdf)<span class="tw-text-green-600">Yes</span>@else<span class="tw-text-red-500">No</span>@endif</td>
                        <td class="tw-px-4 tw-py-3">@if($group->active)<span class="tw-text-green-600">Active</span>@else<span class="tw-text-red-500">Inactive</span>@endif</td>
                        <td class="tw-px-4 tw-py-3 tw-text-right tw-space-x-1">
                            <a href="{{ route('notificationcenter.groups.edit', $group->id) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">Edit</a>
                            <form action="{{ route('notificationcenter.groups.test', $group->id) }}" method="POST" class="tw-inline">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success">Test</button>
                            </form>
                            <form action="{{ route('notificationcenter.groups.destroy', $group->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this group?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No to-channel groups configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
