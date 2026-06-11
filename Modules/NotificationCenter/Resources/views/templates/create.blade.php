@extends('layouts.app')
@section('title', 'Add Template')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-3xl">
    <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">Add Notification Template</h1>
    <form action="{{ route('notificationcenter.templates.store') }}" method="POST" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-p-6 tw-space-y-4">
        @csrf
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Title</label>
            <input type="text" name="title" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="e.g. Stock Transfer Notification">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Module Type</label>
            <select name="module_type" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="stock_transfer">Stock Transfer</option>
                <option value="loan_payment">Loan Payment</option>
                <option value="loan_installment">Loan Installment</option>
            </select>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Message Template</label>
            <textarea name="message_template" rows="6" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="Use {{variable}} placeholders.&#10;e.g. Stock Transfer {{ref_no}} from {{from_location}} to {{to_location}}"></textarea>
            <p class="tw-text-xs tw-text-gray-400 tw-mt-1">Available variables: {{ref_no}}, {{from_location}}, {{to_location}}, {{date}}, {{status}}, {{total_qty}}, {{user}}</p>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">PDF Template View (optional)</label>
            <input type="text" name="pdf_template_view" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="e.g. pdf.stock_transfer">
        </div>
        <div>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="active" value="1" checked> Active
            </label>
        </div>
        <div class="tw-flex tw-gap-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
            <a href="{{ route('notificationcenter.templates.index') }}" class="tw-dw-btn tw-dw-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
