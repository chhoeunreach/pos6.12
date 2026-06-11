@extends('layouts.app')
@section('title', 'Edit Telegram Group')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-3xl">
    <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">Edit Telegram Group</h1>
    <form action="{{ route('notificationcenter.groups.update', $group->id) }}" method="POST" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-p-6 tw-space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Group Name</label>
            <input type="text" name="name" value="{{ $group->name }}" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Chat ID</label>
            <input type="text" name="chat_id" value="{{ $group->chat_id }}" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Module Type</label>
            <select name="module_type" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="stock_transfer" @if($group->module_type === 'stock_transfer') selected @endif>Stock Transfer</option>
                <option value="loan_payment" @if($group->module_type === 'loan_payment') selected @endif>Loan Payment</option>
                <option value="loan_installment" @if($group->module_type === 'loan_installment') selected @endif>Loan Installment</option>
            </select>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Location ID (optional)</label>
            <input type="number" name="location_id" value="{{ $group->location_id }}" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
        </div>
        <div class="tw-flex tw-gap-6">
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="send_text" value="1" @if($group->send_text) checked @endif> Send Text
            </label>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="send_pdf" value="1" @if($group->send_pdf) checked @endif> Send PDF
            </label>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="active" value="1" @if($group->active) checked @endif> Active
            </label>
        </div>
        <div class="tw-flex tw-gap-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Update</button>
            <a href="{{ route('notificationcenter.groups.index') }}" class="tw-dw-btn tw-dw-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
