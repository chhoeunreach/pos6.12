@extends('layouts.app')
@section('title', 'Edit Template')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-3xl">
    <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">Edit Notification Template</h1>
    <form action="{{ route('notificationcenter.templates.update', $template->id) }}" method="POST" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-p-6 tw-space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Title</label>
            <input type="text" name="title" value="{{ old('title', $template->title) }}" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
            @error('title')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Module Type</label>
            <select name="module_type" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="stock_transfer" @if(old('module_type', $template->module_type) === 'stock_transfer') selected @endif>Stock Transfer</option>
                <option value="loan_payment" @if(old('module_type', $template->module_type) === 'loan_payment') selected @endif>Loan Payment</option>
                <option value="loan_installment" @if(old('module_type', $template->module_type) === 'loan_installment') selected @endif>Loan Installment</option>
            </select>
            @error('module_type')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Message Template</label>
            <textarea name="message_template" rows="6" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">{{ old('message_template', $template->message_template) }}</textarea>
            @error('message_template')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">PDF Template View</label>
            <input type="text" name="pdf_template_view" value="{{ old('pdf_template_view', $template->pdf_template_view) }}" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
            @error('pdf_template_view')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="active" value="1" @if($template->active) checked @endif> Active
            </label>
        </div>
        <div class="tw-flex tw-gap-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Update</button>
            <a href="{{ route('notificationcenter.templates.index') }}" class="tw-dw-btn tw-dw-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
