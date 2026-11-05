@extends('layouts.app')

@section('title', 'Financial Approval Limits & Settings')

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Approval Workflow Configuration'])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-max-w-3xl tw-mx-auto">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Role-Based Financial Approval Limits ($ USD)</h3>
        </div>
        <form action="{{ route('daily-financial.approvals.settings.update') }}" method="POST">
            @csrf
            <div class="box-body tw-p-6">
                <p class="tw-text-sm tw-text-gray-600 tw-mb-6">
                    Configure the maximum transaction amounts that each role is authorized to approve without requiring escalation to higher management tiers.
                </p>

                <div class="form-group">
                    <label class="tw-font-semibold">Cashier Approval Limit ($)</label>
                    <input type="number" step="0.01" name="cashier_limit" value="{{ $settings->cashier_limit }}" class="form-control" required />
                    <small class="text-muted">Maximum expense amount a cashier can submit without branch manager pre-approval.</small>
                </div>

                <div class="form-group">
                    <label class="tw-font-semibold">Branch Manager Approval Limit ($)</label>
                    <input type="number" step="0.01" name="branch_manager_limit" value="{{ $settings->branch_manager_limit }}" class="form-control" required />
                    <small class="text-muted">Expenses up to this limit can be approved by Branch Managers.</small>
                </div>

                <div class="form-group">
                    <label class="tw-font-semibold">Accountant Review Threshold ($)</label>
                    <input type="number" step="0.01" name="accountant_limit" value="{{ $settings->accountant_limit }}" class="form-control" required />
                    <small class="text-muted">Expenses exceeding this amount strictly require General Manager / Director sign-off.</small>
                </div>

                <div class="form-group">
                    <label class="tw-font-semibold">General Manager Limit ($)</label>
                    <input type="number" step="0.01" name="general_manager_limit" value="{{ $settings->general_manager_limit }}" class="form-control" required />
                    <small class="text-muted">Amounts above this limit require Owner / Director direct approval.</small>
                </div>

                <hr />

                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="require_accountant_review" value="1" {{ $settings->require_accountant_review ? 'checked' : '' }} />
                        <strong>Enforce Accountant Review Step</strong> before Branch Manager can approve.
                    </label>
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="auto_approve_pos_sync" value="1" {{ $settings->auto_approve_pos_sync ? 'checked' : '' }} />
                        <strong>Auto-approve POS Synchronized Records</strong> (Final sales and returns from POS).
                    </label>
                </div>
            </div>

            <div class="box-footer tw-bg-gray-50 tw-p-4 tw-flex tw-justify-between">
                <a href="{{ route('daily-financial.approvals.index') }}" class="btn btn-default">Back to Queue</a>
                <button type="submit" class="btn btn-primary">Save Approval Configuration</button>
            </div>
        </form>
    </div>
</div>
@endsection

