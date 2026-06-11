@extends('layouts.app')
@section('title', 'Notification Templates')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-7xl">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
        <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800">Notification Templates</h1>
        <a href="{{ route('notificationcenter.templates.create') }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary">+ New Template</a>
    </div>
    <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border">
        <table class="tw-w-full tw-text-sm">
            <thead class="tw-bg-gray-50 tw-border-b">
                <tr>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Title</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Module</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">PDF View</th>
                    <th class="tw-px-4 tw-py-3 tw-text-left tw-font-medium tw-text-gray-600">Active</th>
                    <th class="tw-px-4 tw-py-3 tw-text-right tw-font-medium tw-text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="tw-divide-y">
                @forelse($templates as $template)
                <tr class="hover:tw-bg-gray-50">
                    <td class="tw-px-4 tw-py-3">{{ $template->title }}</td>
                    <td class="tw-px-4 tw-py-3">{{ $template->module_type }}</td>
                    <td class="tw-px-4 tw-py-3">{{ $template->pdf_template_view ?? '-' }}</td>
                    <td class="tw-px-4 tw-py-3">@if($template->active)<span class="tw-text-green-600">Active</span>@else<span class="tw-text-red-500">Inactive</span>@endif</td>
                    <td class="tw-px-4 tw-py-3 tw-text-right tw-space-x-1">
                        <a href="{{ route('notificationcenter.templates.edit', $template->id) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info">Edit</a>
                        <form action="{{ route('notificationcenter.templates.destroy', $template->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this template?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="tw-px-4 tw-py-8 tw-text-center tw-text-gray-400">No templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="tw-mt-3">{{ $templates->links() }}</div>
</div>
@endsection
