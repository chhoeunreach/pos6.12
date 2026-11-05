@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.add_expense'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.add_expense')])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm tw-max-w-4xl tw-mx-auto">
        <div class="box-header with-border">
            <h3 class="box-title tw-text-base tw-font-bold">Record Expense Entry</h3>
        </div>
        <form action="{{ route('daily-financial.expense.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="box-body tw-p-6">
                <div class="row">
                    <!-- Branch -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Branch Location <span class="text-danger">*</span></label>
                        <select name="business_location_id" class="form-control select2" required>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Department -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Department</label>
                        <select name="department_id" class="form-control select2">
                            <option value="">-- General Operations --</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Category -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Expense Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-control select2" required>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->display_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Payment Account -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Pay From Account <span class="text-danger">*</span></label>
                        <select name="financial_account_id" class="form-control select2" required>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ strtoupper($acc->account_type) }}) - ${{ number_format($acc->current_balance, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Date -->
                    <div class="col-md-4 form-group">
                        <label class="tw-font-semibold">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" class="form-control" required />
                    </div>

                    <!-- Amount -->
                    <div class="col-md-4 form-group">
                        <label class="tw-font-semibold">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required />
                    </div>

                    <!-- Currency -->
                    <div class="col-md-4 form-group">
                        <label class="tw-font-semibold">Currency <span class="text-danger">*</span></label>
                        <select name="currency" class="form-control" required>
                            <option value="USD">USD ($)</option>
                            <option value="KHR">KHR (៛ - Riels)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Payment Method -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash (សាច់ប្រាក់)</option>
                            <option value="aba">ABA Bank (ធនាគារអេប៊ីអេ)</option>
                            <option value="acleda">ACLEDA (ធនាគារអេស៊ីលីដា)</option>
                            <option value="wing">Wing (វីង)</option>
                            <option value="other">Other Transfer</option>
                        </select>
                    </div>

                    <!-- Reference Number -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Reference / Receipt Number</label>
                        <input type="text" name="ref_no" class="form-control" placeholder="e.g. EXP-00452" />
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="tw-font-semibold">Expense Purpose / Reason <span class="text-danger">*</span></label>
                    <textarea name="description" rows="3" class="form-control" placeholder="Itemized justification for this expense..." required></textarea>
                </div>

                <div class="row">
                    <!-- Receipt Attachment -->
                    <div class="col-md-6 form-group">
                        <label class="tw-font-semibold">Upload Invoice / Receipt Document</label>
                        <input type="file" name="receipt" class="form-control" accept="image/*,application/pdf" />
                        <small class="text-muted">Supports JPG, PNG, PDF up to 5MB</small>
                    </div>

                    <!-- Operating Expense Checkbox -->
                    <div class="col-md-6 form-group tw-pt-6">
                        <label class="tw-cursor-pointer">
                            <input type="checkbox" name="is_operating" value="1" checked />
                            <span class="tw-font-semibold tw-ml-1">Classify as Operating Expense (OPEX)</span>
                        </label>
                        <small class="text-muted tw-block">Uncheck if this is inventory purchase, asset purchase or loan advance.</small>
                    </div>
                </div>
            </div>

            <div class="box-footer tw-bg-gray-50 tw-p-4 tw-flex tw-justify-between">
                <a href="{{ route('daily-financial.expense.index') }}" class="btn btn-default">Cancel</a>
                <div class="tw-flex tw-gap-2">
                    <button type="submit" name="save_draft" value="1" class="btn btn-default">
                        <i class="fa fa-save"></i> Save as Draft
                    </button>
                    <button type="submit" name="submit_approval" value="1" class="btn btn-danger">
                        <i class="fa fa-paper-plane"></i> Save & Submit for Approval
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

