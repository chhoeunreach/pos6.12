<?php $__env->startSection('title', 'Edit Loan'); ?>

<?php $__env->startSection('loan_css'); ?>
<?php if(request()->boolean('_lm_modal')): ?>
<style>
    #loanManagementSidebar,
    #loanManagementHeader,
    .lm-breadcrumb-wrap,
    .lm-footer {
        display: none !important;
    }

    #loanManagementMain {
        margin-left: 0 !important;
        width: 100% !important;
    }

    #loanManagementMain .lm-content {
        padding-top: 0 !important;
    }

    #loanManagementMain .lm-workspace {
        padding: 12px 18px 24px !important;
    }

    .content-header {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .content {
        min-height: auto !important;
    }
</style>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content_body'); ?>
<?php
    $backCustomerId = request('customer_id') ?: ($loanRow->customer_id ?? null);
    $loanStatuses = ['draft', 'pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted', 'closed'];
    $paymentFrequencies = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
    $interestTypes = [
        'flat' => 'បង់ថេរ',
        'reducing_balance' => 'បង់ថយ',
    ];
    $collectionStatuses = ['new', 'active', 'follow_up', 'ptp', 'overdue', 'escalated', 'recovery', 'closed'];
    $riskLevels = ['low', 'medium', 'high', 'critical'];
    $ptpStatuses = ['open', 'kept', 'broken', 'cancelled'];
    $skipLevels = ['none', 'soft', 'medium', 'hard'];
?>

<section class="content-header">
    <h1><i class="fa fa-pencil-square-o"></i> Edit Loan</h1>
    <p class="text-muted" style="margin: 6px 0 0 30px;">
        Loan #<?php echo e($loanRow->loan_number ?? $loanRow->id, false); ?>

    </p>
</section>

<section class="content">
    <?php if($errors->any()): ?>
        <?php
            $fullSaveError = $errors->first('save_error');
        ?>
        <div class="alert alert-danger">
            <strong>Unable to save this loan.</strong>
            <?php if($fullSaveError): ?>
                <div style="margin-top: 8px;">
                    <a href="#" id="loanViewErrorLink">View error details</a>
                </div>
                <div id="loanErrorDetailsBox" style="display:none; margin-top:10px;">
                    <pre style="white-space:pre-wrap; word-break:break-word; margin:0; padding:10px; background:#fff; border:1px solid #f1b0b7; color:#a94442;"><?php echo e($fullSaveError, false); ?></pre>
                </div>
            <?php endif; ?>
            <ul style="margin:8px 0 0 18px; padding:0;">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($error === $fullSaveError): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <li><?php echo e($error, false); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if(session('status')): ?>
        <div class="alert alert-success">
            <?php echo e(is_array(session('status')) ? (session('status.msg') ?? 'Saved successfully.') : session('status'), false); ?>

        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?php echo e(number_format((float) ($loanRow->principal_amount ?? 0), 2), false); ?></h3>
                    <p>Principal</p>
                </div>
                <div class="icon"><i class="fa fa-money"></i></div>
                <span class="small-box-footer">Balance <?php echo e(number_format((float) ($loanRow->balance_amount ?? 0), 2), false); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?php echo e($paymentsCount ?? 0, false); ?></h3>
                    <p>Payments</p>
                </div>
                <div class="icon"><i class="fa fa-credit-card"></i></div>
                <span class="small-box-footer">Paid <?php echo e(number_format((float) ($loanRow->paid_amount ?? 0), 2), false); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3><?php echo e($schedulesCount ?? 0, false); ?></h3>
                    <p>Schedules</p>
                </div>
                <div class="icon"><i class="fa fa-calendar"></i></div>
                <span class="small-box-footer">Frequency <?php echo e(ucfirst($loanRow->payment_frequency ?? 'monthly'), false); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?php echo e($loanItemsCount ?? 0, false); ?></h3>
                    <p>Loan Items</p>
                </div>
                <div class="icon"><i class="fa fa-cubes"></i></div>
                <span class="small-box-footer">Status <?php echo e(ucfirst($loanRow->status ?? 'draft'), false); ?></span>
            </div>
        </div>
    </div>

    <form method="POST" action="<?php echo e(route('loan-management.loans.update', $loanRow->id), false); ?>">
        <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Loan Overview</h3>
                    <div class="box-tools pull-right">
                        <?php if(!empty($backCustomerId)): ?>
                            <a href="<?php echo e(route('loan-management.customers.edit', $backCustomerId), false); ?>" class="btn btn-default btn-sm">
                                <i class="fa fa-arrow-left"></i> Back to Customer
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo e(route('loan-management.loans.view', $loanRow->id), false); ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-eye"></i> View Loan
                        </a>
                    </div>
                </div>
                <div class="box-body row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan #</label>
                            <input type="text" class="form-control" value="<?php echo e($loanRow->loan_number ?? $loanRow->id, false); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer</label>
                            <input type="text" name="customer_name_snapshot" class="form-control" value="<?php echo e(old('customer_name_snapshot', $customerName), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="customer_phone_snapshot" class="form-control" value="<?php echo e(old('customer_phone_snapshot', $customerPhone), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer ID</label>
                            <input type="number" min="0" name="customer_id" class="form-control" value="<?php echo e(old('customer_id', $loanRow->customer_id ?? ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Contact ID</label>
                            <input type="number" min="0" name="main_contact_id" class="form-control" value="<?php echo e(old('main_contact_id', $mainContactId ?? ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Location</label>
                            <select name="business_location_id" id="loanBusinessLocationSelect" class="form-control">
                                <option value="">Select location</option>
                                <?php $__currentLoopData = $locationOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locationOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($locationOption->id, false); ?>"
                                        data-name="<?php echo e($locationOption->name, false); ?>"
                                        data-main-location-id="<?php echo e($locationOption->main_location_id ?? '', false); ?>"
                                        data-address="<?php echo e($locationOption->address ?? '', false); ?>"
                                        <?php echo e((string) old('business_location_id', $selectedBusinessLocationId ?? '') === (string) $locationOption->id ? 'selected' : '', false); ?>>
                                        <?php echo e($locationOption->name, false); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <input type="hidden" name="business_location_name_snapshot" id="loanBusinessLocationName" value="<?php echo e(old('business_location_name_snapshot', $locationName), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Main Location ID</label>
                            <input type="number" min="0" name="main_location_id" id="loanMainLocationIdInput" class="form-control" value="<?php echo e(old('main_location_id', $locationId ?? $loanRow->main_location_id ?? ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Business Location ID</label>
                            <input type="number" min="0" id="loanBusinessLocationIdDisplay" class="form-control" value="<?php echo e(old('business_location_id', $selectedBusinessLocationId ?? $loanRow->business_location_id ?? ''), false); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="customer_address_snapshot" class="form-control" rows="2"><?php echo e(old('customer_address_snapshot', $customerAddress), false); ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-12" style="margin-top: -8px;">
                        <p><strong>Location Address:</strong> <span id="loanLocationAddressText"><?php echo e($locationAddress, false); ?></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Source Sell Snapshot</h3></div>
                <div class="box-body">
                    <p><strong>Source Type:</strong> <?php echo e($sourceType ?? '-', false); ?></p>
                    <p><strong>Source Transaction ID:</strong> <?php echo e($sourceTransactionId ?? '-', false); ?></p>
                    <p><strong>Source Invoice:</strong> <?php echo e($sourceInvoice ?? '-', false); ?></p>
                    <p><strong>Sell Final Total:</strong> <?php echo e(number_format((float) ($sourceFinalTotal ?? 0), 2), false); ?></p>
                    <p><strong>Sell Paid:</strong> <?php echo e(number_format((float) ($sourcePaid ?? 0), 2), false); ?></p>
                    <p><strong>Sell Due:</strong> <?php echo e(number_format((float) ($sourceDue ?? 0), 2), false); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">Stored Snapshot</h3></div>
                <div class="box-body">
                    <p><strong>Customer Snapshot:</strong> <?php echo e($loanRow->customer_name_snapshot ?? '-', false); ?></p>
                    <p><strong>Phone Snapshot:</strong> <?php echo e($loanRow->customer_phone_snapshot ?? '-', false); ?></p>
                    <p><strong>Product Snapshot:</strong> <?php echo e($loanRow->product_name_snapshot ?? '-', false); ?></p>
                    <p><strong>IMEI Snapshot:</strong> <?php echo e($loanRow->imei_snapshot ?? '-', false); ?></p>
                    <p><strong>Invoice Snapshot:</strong> <?php echo e($loanRow->invoice_number_snapshot ?? '-', false); ?></p>
                </div>
            </div>
        </div>
    </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Core Loan Fields</h3></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <?php $__currentLoopData = $loanStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status, false); ?>" <?php echo e(old('status', $loanRow->status ?? 'draft') === $status ? 'selected' : '', false); ?>><?php echo e(ucfirst($status), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Payment Frequency</label>
                            <select name="payment_frequency" class="form-control">
                                <?php $__currentLoopData = $paymentFrequencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $frequency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($frequency, false); ?>" <?php echo e(old('payment_frequency', $loanRow->payment_frequency ?? 'monthly') === $frequency ? 'selected' : '', false); ?>><?php echo e(ucfirst($frequency), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" name="currency" class="form-control" value="<?php echo e(old('currency', $loanRow->currency ?? 'USD'), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Installment Count</label>
                            <input type="number" name="installment_count" class="form-control" min="0" value="<?php echo e(old('installment_count', $loanRow->installment_count ?? 0), false); ?>">
                            <input type="hidden" name="duration_months" id="loanDurationMonthsInput" value="<?php echo e(old('duration_months', $loanRow->duration_months ?? $loanRow->installment_count ?? 0), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Rate (%)</label>
                            <input type="number" step="0.01" min="0" name="interest_rate" class="form-control" value="<?php echo e(old('interest_rate', $displayInterestRate ?? 0), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Interest Type</label>
                            <select name="interest_type" class="form-control">
                                <?php $__currentLoopData = $interestTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $interestType => $interestTypeLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($interestType, false); ?>" <?php echo e(old('interest_type', $displayInterestType ?? 'flat') === $interestType ? 'selected' : '', false); ?>><?php echo e($interestTypeLabel, false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loan Date</label>
                            <input type="date" name="loan_date" class="form-control" value="<?php echo e(old('loan_date', !empty($loanRow->loan_date) ? \Carbon\Carbon::parse($loanRow->loan_date)->format('Y-m-d') : ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>First Due Date</label>
                            <input type="date" name="first_due_date" class="form-control" value="<?php echo e(old('first_due_date', !empty($loanRow->first_due_date) ? \Carbon\Carbon::parse($loanRow->first_due_date)->format('Y-m-d') : ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Maturity Date</label>
                            <input type="date" name="maturity_date" class="form-control" value="<?php echo e(old('maturity_date', !empty($loanRow->maturity_date) ? \Carbon\Carbon::parse($loanRow->maturity_date)->format('Y-m-d') : ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Approved At</label>
                            <input type="date" name="approved_at" class="form-control" value="<?php echo e(old('approved_at', !empty($loanRow->approved_at) ? \Carbon\Carbon::parse($loanRow->approved_at)->format('Y-m-d') : ''), false); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-info" id="btnGenerateLoanPreview">
                            <i class="fa fa-refresh"></i> Generate Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Schedule Preview</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="loanSchedulePreviewTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Due Date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Total</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Click Generate Preview to recalculate the schedule.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-right">Totals</th>
                            <th>0.00</th>
                            <th>0.00</th>
                            <th>0.00</th>
                            <th>0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Amounts</h3></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-2"><div class="form-group"><label>Principal</label><input type="number" step="0.01" min="0" name="principal_amount" class="form-control" value="<?php echo e(old('principal_amount', $loanRow->principal_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Interest</label><input type="number" step="0.01" min="0" name="interest_amount" class="form-control" value="<?php echo e(old('interest_amount', $loanRow->interest_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Total</label><input type="number" step="0.01" min="0" name="total_amount" class="form-control" value="<?php echo e(old('total_amount', $loanRow->total_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Paid</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="<?php echo e(old('paid_amount', $loanRow->paid_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Balance</label><input type="number" step="0.01" min="0" name="balance_amount" class="form-control" value="<?php echo e(old('balance_amount', $loanRow->balance_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Down Payment</label><input type="number" step="0.01" min="0" name="down_payment" class="form-control" value="<?php echo e(old('down_payment', $loanRow->down_payment ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Penalty</label><input type="number" step="0.01" min="0" name="penalty_amount" class="form-control" value="<?php echo e(old('penalty_amount', $loanRow->penalty_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-2"><div class="form-group"><label>Discount</label><input type="number" step="0.01" min="0" name="discount_amount" class="form-control" value="<?php echo e(old('discount_amount', $loanRow->discount_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-8"><div class="form-group"><label>Source Invoice No</label><input type="text" name="source_invoice_no" class="form-control" value="<?php echo e(old('source_invoice_no', $loanRow->source_invoice_no ?? ''), false); ?>"></div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Source & Collection Workflow</h3></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Type</label>
                            <input type="text" name="source_type" class="form-control" value="<?php echo e(old('source_type', $loanRow->source_type ?? ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Created At</label>
                            <input type="date" name="source_created_at" class="form-control" value="<?php echo e(old('source_created_at', !empty($loanRow->source_created_at) ? \Carbon\Carbon::parse($loanRow->source_created_at)->format('Y-m-d') : ''), false); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Collection Status</label>
                            <select name="collection_status" class="form-control">
                                <option value="">Select</option>
                                <?php $__currentLoopData = $collectionStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status, false); ?>" <?php echo e(old('collection_status', $loanRow->collection_status ?? '') === $status ? 'selected' : '', false); ?>><?php echo e(ucfirst(str_replace('_', ' ', $status)), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Risk Level</label>
                            <select name="risk_level" class="form-control">
                                <option value="">Select</option>
                                <?php $__currentLoopData = $riskLevels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $risk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($risk, false); ?>" <?php echo e(old('risk_level', $loanRow->risk_level ?? '') === $risk ? 'selected' : '', false); ?>><?php echo e(ucfirst($risk), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Collection Priority</label><input type="number" min="0" name="collection_priority" class="form-control" value="<?php echo e(old('collection_priority', $loanRow->collection_priority ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Date</label><input type="date" name="ptp_date" class="form-control" value="<?php echo e(old('ptp_date', !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Amount</label><input type="number" step="0.01" min="0" name="ptp_amount" class="form-control" value="<?php echo e(old('ptp_amount', $loanRow->ptp_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>PTP Status</label>
                            <select name="ptp_status" class="form-control">
                                <option value="">Select</option>
                                <?php $__currentLoopData = $ptpStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status, false); ?>" <?php echo e(old('ptp_status', $loanRow->ptp_status ?? '') === $status ? 'selected' : '', false); ?>><?php echo e(ucfirst($status), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Broken PTP Count</label><input type="number" min="0" name="broken_ptp_count" class="form-control" value="<?php echo e(old('broken_ptp_count', $loanRow->broken_ptp_count ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Contact At</label><input type="date" name="last_contact_at" class="form-control" value="<?php echo e(old('last_contact_at', !empty($loanRow->last_contact_at) ? \Carbon\Carbon::parse($loanRow->last_contact_at)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Next Followup At</label><input type="date" name="next_followup_at" class="form-control" value="<?php echo e(old('next_followup_at', !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-3">
                        <div class="checkbox" style="margin-top: 32px;">
                            <label><input type="checkbox" name="stock_already_deducted" value="1" <?php echo e(old('stock_already_deducted', $loanRow->stock_already_deducted ?? 0) ? 'checked' : '', false); ?>> Stock already deducted</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="field_visit_required" value="1" <?php echo e(old('field_visit_required', $loanRow->field_visit_required ?? 0) ? 'checked' : '', false); ?>> Field visit required</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Skip Level</label>
                            <select name="skip_level" class="form-control">
                                <option value="">Select</option>
                                <?php $__currentLoopData = $skipLevels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $skip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($skip, false); ?>" <?php echo e(old('skip_level', $loanRow->skip_level ?? '') === $skip ? 'selected' : '', false); ?>><?php echo e(ucfirst($skip), false); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Legal Stage</label><input type="text" name="legal_stage" class="form-control" value="<?php echo e(old('legal_stage', $loanRow->legal_stage ?? ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Stage</label><input type="text" name="recovery_stage" class="form-control" value="<?php echo e(old('recovery_stage', $loanRow->recovery_stage ?? ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Repossession Status</label><input type="text" name="repossession_status" class="form-control" value="<?php echo e(old('repossession_status', $loanRow->repossession_status ?? ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Assigned Collection Team</label><input type="text" name="assigned_collection_team" class="form-control" value="<?php echo e(old('assigned_collection_team', $loanRow->assigned_collection_team ?? ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Days Past Due</label><input type="number" min="0" name="days_past_due" class="form-control" value="<?php echo e(old('days_past_due', $loanRow->days_past_due ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Overdue Bucket</label><input type="text" name="overdue_bucket" class="form-control" value="<?php echo e(old('overdue_bucket', $loanRow->overdue_bucket ?? ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Contact Attempt Count</label><input type="number" min="0" name="contact_attempt_count" class="form-control" value="<?php echo e(old('contact_attempt_count', $loanRow->contact_attempt_count ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Score</label><input type="number" min="0" name="recovery_score" class="form-control" value="<?php echo e(old('recovery_score', $loanRow->recovery_score ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Date</label><input type="date" name="last_payment_date" class="form-control" value="<?php echo e(old('last_payment_date', !empty($loanRow->last_payment_date) ? \Carbon\Carbon::parse($loanRow->last_payment_date)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Amount</label><input type="number" step="0.01" min="0" name="last_payment_amount" class="form-control" value="<?php echo e(old('last_payment_amount', $loanRow->last_payment_amount ?? 0), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Blacklisted At</label><input type="date" name="blacklisted_at" class="form-control" value="<?php echo e(old('blacklisted_at', !empty($loanRow->blacklisted_at) ? \Carbon\Carbon::parse($loanRow->blacklisted_at)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Written Off At</label><input type="date" name="written_off_at" class="form-control" value="<?php echo e(old('written_off_at', !empty($loanRow->written_off_at) ? \Carbon\Carbon::parse($loanRow->written_off_at)->format('Y-m-d') : ''), false); ?>"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Last Contact Result</label><input type="text" name="last_contact_result" class="form-control" value="<?php echo e(old('last_contact_result', $loanRow->last_contact_result ?? ''), false); ?>"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>PTP Note</label><textarea name="ptp_note" class="form-control" rows="2"><?php echo e(old('ptp_note', $loanRow->ptp_note ?? ''), false); ?></textarea></div></div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Note</h3></div>
            <div class="box-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="note" class="form-control" rows="4"><?php echo e(old('note', $loanRow->note ?? ''), false); ?></textarea>
                </div>
            </div>
            <div class="box-footer text-right">
                <?php if(!empty($backCustomerId)): ?>
                    <a href="<?php echo e(route('loan-management.customers.edit', $backCustomerId), false); ?>" class="btn btn-default">Back to Customer</a>
                <?php else: ?>
                    <a href="<?php echo e(route('loan-management.loans.view', $loanRow->id), false); ?>" class="btn btn-default">Cancel</a>
                <?php endif; ?>
                <button class="btn btn-primary"><i class="fa fa-save"></i> Save Loan</button>
            </div>
        </div>
    </form>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Related Loan Data</h3></div>
        <div class="box-body">
            <p class="text-muted">Large loan tables are loaded after the page opens so the edit form can appear faster.</p>
            <div id="loanEditSections" data-url="<?php echo e(route('loan-management.loans.sections.edit', ['loan' => $loanRow->id] + (!empty($backCustomerId) ? ['customer_id' => $backCustomerId] : [])), false); ?>">
                <div class="text-center text-muted" style="padding: 24px 0;">
                    <i class="fa fa-spinner fa-spin"></i> Loading related sections...
                </div>
            </div>
        </div>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('loan_js'); ?>
<script>
    (function () {
        var select = document.getElementById('loanBusinessLocationSelect');
        var nameInput = document.getElementById('loanBusinessLocationName');
        var idDisplay = document.getElementById('loanBusinessLocationIdDisplay');
        var mainLocationInput = document.getElementById('loanMainLocationIdInput');
        var locationAddressText = document.getElementById('loanLocationAddressText');
        var sectionsContainer = document.getElementById('loanEditSections');
        var installmentCountInput = document.querySelector('input[name="installment_count"]');
        var durationMonthsInput = document.getElementById('loanDurationMonthsInput');
        var previewButton = document.getElementById('btnGenerateLoanPreview');
        var previewTable = document.getElementById('loanSchedulePreviewTable');
        var viewErrorLink = document.getElementById('loanViewErrorLink');
        var errorDetailsBox = document.getElementById('loanErrorDetailsBox');

        function formatMoney(value) {
            var amount = Number(value || 0);

            return amount.toFixed(2);
        }

        function syncDurationMonths() {
            if (durationMonthsInput && installmentCountInput) {
                durationMonthsInput.value = installmentCountInput.value || '';
            }
        }

        if (viewErrorLink && errorDetailsBox) {
            viewErrorLink.addEventListener('click', function (event) {
                event.preventDefault();

                var isHidden = errorDetailsBox.style.display === 'none';
                errorDetailsBox.style.display = isHidden ? 'block' : 'none';
                viewErrorLink.textContent = isHidden ? 'Hide error details' : 'View error details';
            });
        }

        function syncLocationFields() {
            if (!select) {
                return;
            }

            var option = select.options[select.selectedIndex];
            var hasValue = option && option.value !== '';

            if (nameInput) {
                nameInput.value = hasValue ? (option.getAttribute('data-name') || '') : '';
            }

            if (idDisplay) {
                idDisplay.value = hasValue ? option.value : '';
            }

            if (mainLocationInput) {
                mainLocationInput.value = hasValue
                    ? (option.getAttribute('data-main-location-id') || '')
                    : '';
            }

            if (locationAddressText) {
                locationAddressText.textContent = hasValue
                    ? (option.getAttribute('data-address') || '-')
                    : '-';
            }
        }

        if (select) {
            select.addEventListener('change', syncLocationFields);
            syncLocationFields();
        }

        if (installmentCountInput) {
            installmentCountInput.addEventListener('input', syncDurationMonths);
            installmentCountInput.addEventListener('change', syncDurationMonths);
            syncDurationMonths();
        }

        if (previewButton && previewTable && window.jQuery) {
            window.jQuery(previewButton).on('click', function () {
                syncDurationMonths();

                var form = window.jQuery(previewButton).closest('form');
                var tbody = window.jQuery(previewTable).find('tbody').first();
                var footerCells = window.jQuery(previewTable).find('tfoot tr th');

                window.jQuery(previewButton)
                    .prop('disabled', true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Generating...');

                window.jQuery.post("<?php echo e(route('loan-management.loans.preview-schedule'), false); ?>", form.serialize(), function (res) {
                    var rows = res.data || [];
                    var totalPrincipal = 0;
                    var totalInterest = 0;
                    var totalAmount = 0;
                    var totalBalance = 0;

                    tbody.empty();

                    if (!rows.length) {
                        tbody.append('<tr><td colspan="6" class="text-center text-muted">No preview rows generated.</td></tr>');
                    }

                    rows.forEach(function (row) {
                        totalPrincipal += Number(row.principal || 0);
                        totalInterest += Number(row.interest || 0);
                        totalAmount += Number(row.total || 0);
                        totalBalance += Number(row.balance || 0);

                        tbody.append(
                            '<tr>' +
                                '<td>' + (row.schedule_no || '') + '</td>' +
                                '<td>' + (row.due_date || '') + '</td>' +
                                '<td>' + formatMoney(row.principal) + '</td>' +
                                '<td>' + formatMoney(row.interest) + '</td>' +
                                '<td>' + formatMoney(row.total) + '</td>' +
                                '<td>' + formatMoney(row.balance) + '</td>' +
                            '</tr>'
                        );
                    });

                    footerCells.eq(1).text(formatMoney(totalPrincipal));
                    footerCells.eq(2).text(formatMoney(totalInterest));
                    footerCells.eq(3).text(formatMoney(totalAmount));
                    footerCells.eq(4).text(formatMoney(totalBalance));
                }).fail(function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Failed to preview schedule';

                    window.alert(message);
                }).always(function () {
                    window.jQuery(previewButton)
                        .prop('disabled', false)
                        .html('<i class="fa fa-refresh"></i> Generate Preview');
                });
            });
        }

        if (sectionsContainer && sectionsContainer.getAttribute('data-url') && window.jQuery) {
            window.jQuery.ajax({
                url: sectionsContainer.getAttribute('data-url'),
                dataType: 'html',
                success: function (result) {
                    sectionsContainer.innerHTML = result;
                },
                error: function () {
                    sectionsContainer.innerHTML = '<div class="alert alert-warning" style="margin-bottom:0;">Unable to load related sections right now.</div>';
                }
            });
        }
    })();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('loanmanagement::layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/loans/edit.blade.php ENDPATH**/ ?>