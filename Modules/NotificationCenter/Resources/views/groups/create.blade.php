@extends('layouts.app')
@section('title', 'Add Telegram Group')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-3xl">
    <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">Add Telegram Group</h1>
    <form action="{{ route('notificationcenter.groups.store') }}" method="POST" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-p-6 tw-space-y-4">
        @csrf
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Group Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="e.g. Main Stock Channel">
            @error('name')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Chat ID</label>
            <input type="text" name="chat_id" value="{{ old('chat_id') }}" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="e.g. -1001234567890">
            <p class="tw-text-xs tw-text-gray-400 tw-mt-1">Negative for groups/channels, positive for users.</p>
            @error('chat_id')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Module Type</label>
            <select name="module_type" required class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="stock_transfer" @if(old('module_type') === 'stock_transfer') selected @endif>Stock Transfer</option>
                <option value="loan_payment" @if(old('module_type') === 'loan_payment') selected @endif>Loan Payment</option>
                <option value="loan_installment" @if(old('module_type') === 'loan_installment') selected @endif>Loan Installment</option>
            </select>
            @error('module_type')<p class="tw-text-xs tw-text-red-500 tw-mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Direction</label>
            <select name="direction" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="">Both / All</option>
                <option value="from" @if(old('direction') === 'from') selected @endif>From Channel</option>
                <option value="to" @if(old('direction') === 'to') selected @endif>To Channel</option>
            </select>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Location ID (optional)</label>
            <input type="number" name="location_id" value="{{ old('location_id') }}" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm" placeholder="Leave empty for all locations">
        </div>
        <div class="tw-flex tw-gap-6">
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="send_text" value="1" checked> Send Text
            </label>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="send_pdf" value="1" checked> Send PDF
            </label>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="checkbox" name="active" value="1" checked> Active
            </label>
        </div>
        <div class="tw-flex tw-gap-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
            <a href="{{ route('notificationcenter.groups.index') }}" class="tw-dw-btn tw-dw-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
