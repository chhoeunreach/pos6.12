@extends('layouts.app')
@section('title', 'Notification Settings')
@section('content')
<div class="tw-px-3 lg:tw-px-5 tw-mx-auto tw-max-w-3xl">
    <h1 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">Notification Settings</h1>
    <form action="{{ route('notificationcenter.settings.update') }}" method="POST" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-p-6 tw-space-y-4">
        @csrf
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Telegram Bot Token</label>
            <input type="password" name="telegram_bot_token" value="{{ $settings['telegram_bot_token'] }}" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
            <p class="tw-text-xs tw-text-gray-400 tw-mt-1">Leave empty to use config('notificationcenter.telegram_bot_token')</p>
        </div>
        <div>
            <label class="tw-flex tw-items-center tw-gap-2 tw-text-sm">
                <input type="hidden" name="queue_enabled" value="0">
                <input type="checkbox" name="queue_enabled" value="1" @if($settings['queue_enabled']) checked @endif> Queue Notifications
            </label>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">PDF Engine</label>
            <select name="pdf_engine" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
                <option value="wkhtmltopdf" @if($settings['pdf_engine'] === 'wkhtmltopdf') selected @endif>wkhtmltopdf</option>
                <option value="mpdf" @if($settings['pdf_engine'] === 'mpdf') selected @endif>mPDF</option>
            </select>
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Log Cleanup (days)</label>
            <input type="number" name="cleanup_days" value="{{ $settings['cleanup_days'] }}" min="1" max="90" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700">Retry Attempts</label>
            <input type="number" name="retry_attempts" value="{{ $settings['retry_attempts'] }}" min="0" max="10" class="tw-w-full tw-border tw-border-gray-300 tw-rounded-lg tw-px-3 tw-py-2 tw-text-sm">
        </div>
        <div class="tw-flex tw-gap-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">Save</button>
            <a href="{{ route('notificationcenter.groups.index') }}" class="tw-dw-btn tw-dw-btn-ghost">Back to Groups</a>
        </div>
    </form>
</div>
@endsection
