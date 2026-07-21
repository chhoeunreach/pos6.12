@extends('layouts.app')
@section('title', 'Edit Telegram Group')
@section('content')
<style>
    .nc-page {
        padding: 18px;
    }
    .nc-shell {
        max-width: 980px;
        margin: 0 auto;
    }
    .nc-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .nc-title {
        margin: 0;
        color: #1f2937;
        font-size: 22px;
        font-weight: 700;
    }
    .nc-subtitle {
        margin: 4px 0 0;
        color: #6b7280;
        font-size: 13px;
    }
    .nc-form {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .nc-form-body {
        padding: 22px;
    }
    .nc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .nc-field-full {
        grid-column: 1 / -1;
    }
    .nc-label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 7px;
        color: #374151;
        font-size: 13px;
        font-weight: 700;
    }
    .nc-label i {
        color: #64748b;
        width: 14px;
        text-align: center;
    }
    .nc-control,
    .nc-form select.nc-control {
        width: 100%;
        height: 42px;
        background: #ffffff !important;
        color: #111827 !important;
        border: 1px solid #d1d5db !important;
        border-radius: 7px !important;
        padding: 8px 12px !important;
        font-size: 14px;
        box-shadow: none !important;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .nc-control:focus,
    .nc-form select.nc-control:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16) !important;
        outline: none;
    }
    .nc-help {
        margin: 6px 0 0;
        color: #6b7280;
        font-size: 12px;
    }
    .nc-error {
        margin: 6px 0 0;
        color: #dc2626;
        font-size: 12px;
    }
    .nc-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .nc-option {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 48px;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
        color: #1f2937;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .nc-option input {
        margin: 0;
        accent-color: #4f46e5;
    }
    .nc-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
    }
    .nc-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 9px 16px;
        border-radius: 7px;
        border: 1px solid transparent;
        font-weight: 700;
        font-size: 14px;
        line-height: 1;
    }
    .nc-btn-primary {
        background: #4f46e5;
        color: #ffffff;
    }
    .nc-btn-secondary {
        background: #ffffff;
        color: #374151;
        border-color: #d1d5db;
    }
    .nc-form .select2-container {
        width: 100% !important;
    }
    .nc-form .select2-container .select2-selection--single {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 7px;
        background: #ffffff;
    }
    .nc-form .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #111827;
        line-height: 40px;
        padding-left: 12px;
    }
    .nc-form .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    @media (max-width: 768px) {
        .nc-page {
            padding: 12px;
        }
        .nc-heading,
        .nc-actions {
            align-items: stretch;
            flex-direction: column;
        }
        .nc-grid,
        .nc-options {
            grid-template-columns: 1fr;
        }
        .nc-btn {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<div class="nc-page">
    <div class="nc-shell">
        <div class="nc-heading">
            <div>
                <h1 class="nc-title">Edit Telegram Group</h1>
                <p class="nc-subtitle">Update channel routing, location, and send options.</p>
            </div>
            <a href="{{ route('notificationcenter.groups.index') }}" class="nc-btn nc-btn-secondary">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>

        <form action="{{ route('notificationcenter.groups.update', $group->id) }}" method="POST" class="nc-form">
            @csrf @method('PUT')
            <div class="nc-form-body">
                <div class="nc-grid">
                    <div class="nc-field-full">
                        <label class="nc-label"><i class="fa fa-users"></i> Group Name</label>
                        <input type="text" name="name" value="{{ old('name', $group->name) }}" required class="nc-control">
                        @error('name')<p class="nc-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="nc-label"><i class="fa fa-paper-plane"></i> Chat ID</label>
                        <input type="text" name="chat_id" value="{{ old('chat_id', $group->chat_id) }}" required class="nc-control">
                        @error('chat_id')<p class="nc-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="nc-label"><i class="fa fa-cubes"></i> Module Type</label>
                        <select name="module_type" required class="nc-control">
                            <option value="stock_transfer" @if(old('module_type', $group->module_type) === 'stock_transfer') selected @endif>Stock Transfer</option>
                            <option value="loan_payment" @if(old('module_type', $group->module_type) === 'loan_payment') selected @endif>Loan Payment</option>
                            <option value="loan_installment" @if(old('module_type', $group->module_type) === 'loan_installment') selected @endif>Loan Installment</option>
                        </select>
                        @error('module_type')<p class="nc-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="nc-label"><i class="fa fa-exchange"></i> Direction</label>
                        <select name="direction" class="nc-control">
                            <option value="">Both / All</option>
                            <option value="from" @if(old('direction', $group->direction) === 'from') selected @endif>From Channel</option>
                            <option value="to" @if(old('direction', $group->direction) === 'to') selected @endif>To Channel</option>
                        </select>
                    </div>

                    <div>
                        <label class="nc-label"><i class="fa fa-map-marker"></i> Location</label>
                        <select name="location_id" class="nc-control select2">
                            @foreach($business_locations as $id => $name)
                                <option value="{{ $id }}" @if((string) old('location_id', $group->location_id) === (string) $id) selected @endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="nc-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="nc-options">
                    <label class="nc-option">
                        <input type="checkbox" name="send_text" value="1" @if($group->send_text) checked @endif>
                        <span><i class="fa fa-commenting-o"></i> Send Text</span>
                    </label>
                    <label class="nc-option">
                        <input type="checkbox" name="send_pdf" value="1" @if($group->send_pdf) checked @endif>
                        <span><i class="fa fa-file-pdf-o"></i> Send PDF</span>
                    </label>
                    <label class="nc-option">
                        <input type="checkbox" name="active" value="1" @if($group->active) checked @endif>
                        <span><i class="fa fa-check-circle-o"></i> Active</span>
                    </label>
                </div>
            </div>

            <div class="nc-actions">
                <a href="{{ route('notificationcenter.groups.index') }}" class="nc-btn nc-btn-secondary">Cancel</a>
                <button type="submit" class="nc-btn nc-btn-primary">
                    <i class="fa fa-save"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
