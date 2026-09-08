@php
    $principal = (float) ($scheduleRow->principal_amount ?? $scheduleRow->principal_due ?? $scheduleRow->principal ?? 0);
    $interest = (float) ($scheduleRow->interest_amount ?? $scheduleRow->interest_due ?? $scheduleRow->interest ?? $scheduleRow->benefit_value ?? 0);
    $amountDue = (float) ($scheduleRow->schedule_amount ?? $scheduleRow->amount_due ?? $scheduleRow->total ?? ($principal + $interest));
    $paid = (float) ($scheduleRow->paid_amount ?? $scheduleRow->amount_paid ?? $scheduleRow->paid_value ?? 0);
    $discount = (float) ($scheduleRow->discount_amount ?? 0);
    $balance = (float) ($scheduleRow->balance_amount ?? $scheduleRow->amount_balance ?? max(0, $amountDue - $paid - $discount));
    $dueDate = ! empty($scheduleRow->due_date) ? \Carbon\Carbon::parse($scheduleRow->due_date)->format('Y-m-d') : '';
    $status = strtolower((string) ($scheduleRow->status ?? 'unpaid'));
    $isEmbeddedModal = request()->boolean('_lm_modal');
    $sectionsContext = request('sections_context', 'edit');
    $editRouteParams = ['loan' => $loanRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : []);
    $schedulePayments = $schedulePayments ?? collect();
    $paymentTypes = $paymentTypes ?? ['cash' => 'Cash'];
    $defaultPaymentMethod = $defaultPaymentMethod ?? (array_key_exists('cash', $paymentTypes) ? 'cash' : array_key_first($paymentTypes));
    $paymentAmount = $paid > 0 ? $paid : max(0, $amountDue - $balance);
    $paymentDate = ! empty($scheduleRow->paid_date)
        ? \Carbon\Carbon::parse($scheduleRow->paid_date)->format('Y-m-d')
        : (! empty($scheduleRow->paid_at) ? \Carbon\Carbon::parse($scheduleRow->paid_at)->format('Y-m-d') : date('Y-m-d'));
    $firstPayment = $schedulePayments->first();
    $firstPaymentMethod = $firstPayment->method ?? $firstPayment->channel ?? $firstPayment->payment_method_snapshot ?? $defaultPaymentMethod;
    if (! array_key_exists($firstPaymentMethod, $paymentTypes)) {
        $matchedMethod = array_search($firstPaymentMethod, $paymentTypes, true);
        $firstPaymentMethod = $matchedMethod !== false ? $matchedMethod : $defaultPaymentMethod;
    }
    $statuses = [
        'auto' => 'Auto',
        'pending' => 'Pending',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
        'paid' => 'Paid',
        'late' => 'Late',
        'completed' => 'Completed',
        'pay off' => 'Pay Off',
    ];
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => route('loan-management.loans.schedules.update', ['loan' => $loanRow->id, 'schedule' => $scheduleRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])), 'method' => 'post', 'id' => 'loan_schedule_update_form']) !!}
        <input type="hidden" name="return_to" value="{{ route('loan-management.loans.edit', $editRouteParams) }}">
        <input type="hidden" name="sections_context" value="{{ $sectionsContext }}">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ lm_label('messages.close', 'Close', 'បិទ') }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-pencil"></i> Edit Payment Schedule
            </h4>
        </div>

        <div class="modal-body">
            <div id="loan_schedule_update_error" class="alert alert-danger" style="display:none;"></div>
            <div class="row">
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Installment #:</strong> {{ $loanRow->loan_number ?? $loanRow->id }}<br>
                        <strong>Customer:</strong> {{ $loanRow->customer_name_snapshot ?? '-' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Schedule #:</strong> {{ $scheduleRow->installment_no ?? $scheduleRow->id }}<br>
                        <strong>Currency:</strong> {{ $loanRow->currency ?? 'USD' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Current status:</strong> {{ ucfirst($status ?: 'unpaid') }}<br>
                        <strong>Current balance:</strong> {{ number_format($balance, 2) }}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('installment_no', '#') !!}
                        <input type="number" min="1" name="installment_no" id="installment_no" class="form-control" value="{{ $scheduleRow->installment_no ?? '' }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('due_date', 'Due Date') !!}
                        <input type="date" name="due_date" id="due_date" class="form-control" value="{{ $dueDate }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('status', 'Status') !!}
                        <select name="status" id="status" class="form-control">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ $value === $status ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('schedule_amount', 'Schedule Amount') !!}
                        <input type="number" step="0.01" min="0" name="schedule_amount" id="schedule_amount" class="form-control schedule-number" value="{{ number_format($amountDue, 2, '.', '') }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('principal_amount', 'Principal') !!}
                        <input type="number" step="0.01" min="0" name="principal_amount" id="principal_amount" class="form-control schedule-number" value="{{ number_format($principal, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('interest_amount', 'Interest') !!}
                        <input type="number" step="0.01" min="0" name="interest_amount" id="interest_amount" class="form-control schedule-number" value="{{ number_format($interest, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('paid_amount', 'Paid') !!}
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" class="form-control schedule-number" value="{{ number_format($paid, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('discount_amount', 'Discount') !!}
                        <input type="text" inputmode="decimal" name="discount_amount" id="discount_amount" class="form-control schedule-number schedule-discount-field" value="{{ number_format($discount, 2, '.', '') }}" autocomplete="off">
                        <small class="text-muted">Use with Pay Off when customer pays less by discount.</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('balance_amount', 'Balance') !!}
                        <input type="number" step="0.01" min="0" name="balance_amount" id="balance_amount" class="form-control schedule-number" value="{{ number_format($balance, 2, '.', '') }}">
                    </div>
                </div>
            </div>

            <hr style="margin:10px 0 14px;">

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_action', 'Payment Action') !!}
                        <select name="payment_action" id="payment_action" class="form-control">
                            <option value="keep">Keep payment records</option>
                            <option value="sync_status">Sync by status</option>
                            <option value="add_update">Add / Update payment</option>
                            <option value="remove">Remove payment</option>
                        </select>
                        <small class="text-muted">Use this when status must change payment records too.</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_amount', 'Payment Amount') !!}
                        <input type="number" step="0.01" min="0" name="payment_amount" id="payment_amount" class="form-control schedule-payment-field" value="{{ number_format($paymentAmount, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_paid_date', 'Payment Date') !!}
                        <input type="date" name="payment_paid_date" id="payment_paid_date" class="form-control schedule-payment-field" value="{{ $paymentDate }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_method', 'Payment Method') !!}
                        {!! Form::select('payment_method', $paymentTypes, $firstPaymentMethod, ['class' => 'form-control schedule-payment-field', 'id' => 'payment_method']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('payment_reference_number', 'Payment Reference') !!}
                        <input type="text" name="payment_reference_number" id="payment_reference_number" class="form-control schedule-payment-field" value="{{ $firstPayment->reference_number ?? '' }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('payment_note', 'Payment Note') !!}
                        <input type="text" name="payment_note" id="payment_note" class="form-control schedule-payment-field" value="{{ $firstPayment->note ?? '' }}">
                    </div>
                </div>
            </div>

            <div class="table-responsive" style="margin-top:4px;">
                <table class="table table-condensed table-bordered" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>Linked Payment</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedulePayments as $payment)
                            @php
                                $linkedPaidDate = $payment->paid_date ?? $payment->paid_at ?? null;
                                $linkedAmount = (float) ($payment->total_paid_base ?? $payment->total_paid ?? $payment->amount ?? 0);
                                $linkedMethod = $payment->payment_method_snapshot ?? $payment->channel ?? $payment->method ?? '-';
                                $linkedReceipt = $payment->receipt_number ?? $payment->payment_ref_no ?? $payment->reference_number ?? ('Payment #'.$payment->id);
                            @endphp
                            <tr>
                                <td>{{ $linkedReceipt }}</td>
                                <td>{{ ! empty($linkedPaidDate) ? \Carbon\Carbon::parse($linkedPaidDate)->format('d-m-Y') : '-' }}</td>
                                <td>{{ $linkedMethod }}</td>
                                <td class="text-right">{{ number_format($linkedAmount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No payment linked to this schedule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left loan-schedule-recalculate">
                <i class="fa fa-calculator"></i> Auto Balance
            </button>
            <button type="button"
                    class="btn btn-danger pull-left loan-schedule-delete"
                    data-url="{{ route('loan-management.loans.schedules.destroy', ['loan' => $loanRow->id, 'schedule' => $scheduleRow->id] + ($isEmbeddedModal ? ['_lm_modal' => 1] : [])) }}">
                <i class="fa fa-trash"></i> Delete
            </button>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                {{ lm_label('messages.update', 'Update', 'ធ្វើបច្ចុប្បន្នភាព') }}
            </button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
                {{ lm_label('messages.close', 'Close', 'បិទ') }}
            </button>
        </div>
        {!! Form::close() !!}
    </div>
</div>

<script>
$(function () {
    var $form = $('#loan_schedule_update_form');

    function numberValue(selector) {
        return parseDecimal($form.find(selector).val());
    }

    function parseDecimal(value) {
        value = String(value || '').replace(/,/g, '.').replace(/[^0-9.]/g, '');
        var firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }

        return parseFloat(value) || 0;
    }

    function sanitizeDecimalField($input, format) {
        var value = String($input.val() || '').replace(/,/g, '.').replace(/[^0-9.]/g, '');
        var firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }
        var number = parseFloat(value) || 0;
        $input.val(format ? number.toFixed(2) : value);

        return number;
    }

    function recalculateBalance() {
        var amountDue = numberValue('[name="schedule_amount"]');
        var paid = numberValue('[name="paid_amount"]');
        var discount = numberValue('[name="discount_amount"]');
        var status = String($form.find('[name="status"]').val() || '').toLowerCase();
        if (status === 'pay_off' || status === 'payoff') {
            status = 'pay off';
        }
        if (status === 'pay off' && !$form.find('[name="paid_amount"]').data('touched')) {
            paid = Math.max(amountDue - discount, 0);
            $form.find('[name="paid_amount"]').val(paid.toFixed(2));
        }
        $form.find('[name="balance_amount"]').val(Math.max(amountDue - paid - discount, 0).toFixed(2));
        if (!$form.find('[name="payment_amount"]').data('touched')) {
            $form.find('[name="payment_amount"]').val(Math.max(paid, 0).toFixed(2));
        }
    }

    $form.on('click', '.loan-schedule-recalculate', recalculateBalance);
    $form.on('input change', '[name="paid_amount"]', function () {
        $(this).data('touched', true);
    });
    $form.on('input', '.schedule-discount-field', function () {
        sanitizeDecimalField($(this), false);
        recalculateBalance();
    });
    $form.on('change blur', '.schedule-discount-field', function () {
        sanitizeDecimalField($(this), true);
        recalculateBalance();
    });
    $form.on('input change', '[name="payment_amount"]', function () {
        $(this).data('touched', true);
    });
    $form.on('change', '[name="status"]', function () {
        var status = String($(this).val() || '').toLowerCase();
        if (['paid', 'completed', 'pay off'].indexOf(status) !== -1) {
            $form.find('[name="payment_action"]').val('sync_status');
            var amountDue = numberValue('[name="schedule_amount"]');
            var discount = status === 'pay off' ? numberValue('[name="discount_amount"]') : 0;
            if (status === 'pay off' && !$form.find('[name="paid_amount"]').data('touched')) {
                $form.find('[name="paid_amount"]').val(Math.max(amountDue - discount, 0).toFixed(2));
            }
            if (!$form.find('[name="payment_amount"]').data('touched')) {
                $form.find('[name="payment_amount"]').val(Math.max(amountDue - discount, 0).toFixed(2));
            }
        } else if (['pending', 'unpaid'].indexOf(status) !== -1 && numberValue('[name="paid_amount"]') <= 0) {
            $form.find('[name="payment_action"]').val('sync_status');
        }
    });

    function replaceSectionsFromResponse(res) {
        var data = (res && res.data) ? res.data : {};
        var targetId = data.sections_target || 'loanEditSections';
        var targetIds = [targetId, 'loanShowSections', 'loanEditSections'];
        var html = data.sections_html || '';

        $('.view_modal').modal('hide');

        if (html) {
            for (var i = 0; i < targetIds.length; i++) {
                var id = targetIds[i];
                var parentTarget = window.parent && window.parent.document ? window.parent.document.getElementById(id) : null;
                if (parentTarget) {
                    parentTarget.innerHTML = html;
                    return true;
                }

                var localTarget = document.getElementById(id);
                if (localTarget) {
                    localTarget.innerHTML = html;
                    return true;
                }
            }
        }

        if (data.redirect_url && window.jQuery && $('.view_modal').length) {
            $.ajax({
                url: data.redirect_url,
                dataType: 'html',
                success: function (html) {
                    $('.view_modal').html(html).modal('show');
                },
                error: function () {
                    window.location.href = data.redirect_url;
                }
            });
            return true;
        }

        window.location.href = data.redirect_url || window.location.href;
        return false;
    }

    $form.off('submit.loanScheduleModal').on('submit.loanScheduleModal', function (e) {
        e.preventDefault();
        var $buttons = $form.find('button[type="submit"], .loan-schedule-recalculate');
        var $errorBox = $('#loan_schedule_update_error');

        $errorBox.hide().empty();
        $buttons.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Payment schedule updated successfully');
                }

                replaceSectionsFromResponse(res);
            },
            error: function (xhr) {
                var message = 'Failed to update payment schedule';
                var detail = '';

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    message = xhr.responseJSON.errors[firstKey][0] || 'Validation failed';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                    if (xhr.responseJSON.data && xhr.responseJSON.data.detail) {
                        detail = xhr.responseJSON.data.detail;
                    }
                } else if (xhr.responseText) {
                    message = $('<div>').html(xhr.responseText).text().trim() || message;
                    message = message.substring(0, 600);
                }

                $errorBox
                    .html(
                        $('<div>').text(message).html() +
                        (detail ? '<br><small>' + $('<div>').text(detail).html() + '</small>' : '')
                    )
                    .show();

                if (window.toastr) {
                    toastr.error(message);
                }

                if (!$errorBox.length) {
                    alert(message);
                    return;
                }
            },
            complete: function () {
                $buttons.prop('disabled', false);
            }
        });
    });

    $form.on('click', '.loan-schedule-delete', function () {
        if (!confirm('Delete this payment schedule? Related payment records linked to this schedule will also be removed.')) {
            return;
        }

        var $button = $(this);
        var $buttons = $form.find('button');
        var $errorBox = $('#loan_schedule_update_error');

        $errorBox.hide().empty();
        $buttons.prop('disabled', true);

        $.ajax({
            url: $button.data('url'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Payment schedule deleted successfully');
                }
                replaceSectionsFromResponse(res);
            },
            error: function (xhr) {
                var message = 'Failed to delete payment schedule';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                $errorBox.text(message).show();
                if (window.toastr) {
                    toastr.error(message);
                }
            },
            complete: function () {
                $buttons.prop('disabled', false);
            }
        });
    });
});
</script>
