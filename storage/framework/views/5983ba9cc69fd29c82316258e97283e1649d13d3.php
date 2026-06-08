<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Loan Items</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>IMEI</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $loanItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($item->id, false); ?></td>
                        <td><?php echo e($item->product_name_snapshot ?? '-', false); ?></td>
                        <td><?php echo e($item->sku_snapshot ?? '-', false); ?></td>
                        <td><?php echo e($item->imei_snapshot ?? '-', false); ?></td>
                        <td><?php echo e($item->qty ?? 0, false); ?></td>
                        <td><?php echo e(number_format((float) ($item->unit_price ?? 0), 2), false); ?></td>
                        <td><?php echo e(number_format((float) ($item->line_total ?? 0), 2), false); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="7" class="text-center">No loan items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Payment Schedules</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $schedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $schedule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0);
                        $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
                        $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? 0);
                    ?>
                    <tr>
                        <td><?php echo e($schedule->installment_no ?? $schedule->id, false); ?></td>
                        <td><?php echo e(!empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-', false); ?></td>
                        <td><?php echo e(number_format((float) ($schedule->principal_due ?? $schedule->principal_amount ?? 0), 2), false); ?></td>
                        <td><?php echo e(number_format((float) ($schedule->interest_due ?? $schedule->interest_amount ?? 0), 2), false); ?></td>
                        <td><?php echo e(number_format($due, 2), false); ?></td>
                        <td><?php echo e(number_format($paid, 2), false); ?></td>
                        <td><?php echo e(number_format($balance, 2), false); ?></td>
                        <td><?php echo e(ucfirst($schedule->status ?? 'pending'), false); ?></td>
                        <td>
                            <button type="button"
                                    class="btn btn-xs btn-primary btn-modal"
                                    data-href="<?php echo e(route('loan-management.loans.schedules.edit', ['loan' => $loanRow->id, 'schedule' => $schedule->id]), false); ?>"
                                    data-container=".view_modal">
                                <i class="fa fa-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="9" class="text-center">No schedules found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Recent Payments</h3></div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Receipt</th>
                    <th>Paid Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $receipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #' . $payment->id);
                        $amount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                        $method = $payment->payment_method_snapshot ?? $payment->channel ?? '-';
                        $paidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                    ?>
                    <tr>
                        <td><?php echo e($payment->id, false); ?></td>
                        <td><?php echo e($receipt, false); ?></td>
                        <td><?php echo e(!empty($paidDate) ? \Carbon\Carbon::parse($paidDate)->format('d-m-Y') : '-', false); ?></td>
                        <td><?php echo e($method, false); ?></td>
                        <td><?php echo e(number_format($amount, 2), false); ?></td>
                        <td><?php echo e(ucfirst($payment->status ?? 'confirmed'), false); ?></td>
                        <td>
                            <a href="<?php echo e(route('loan-management.payments.edit', ['payment' => $payment->id, 'customer_id' => $backCustomerId]), false); ?>" class="btn btn-xs btn-primary">
                                <i class="fa fa-pencil"></i> Edit Payment
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="7" class="text-center">No payments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/loans/partials/edit_sections.blade.php ENDPATH**/ ?>