@php
    $principal = (float) ($scheduleRow->principal_amount ?? $scheduleRow->principal_due ?? 0);
    $interest = (float) ($scheduleRow->interest_amount ?? $scheduleRow->interest_due ?? $scheduleRow->benefit_value ?? 0);
    $amountDue = (float) ($scheduleRow->schedule_amount ?? $scheduleRow->amount_due ?? ($principal + $interest));
    $paid = (float) ($scheduleRow->paid_amount ?? $scheduleRow->amount_paid ?? $scheduleRow->paid_value ?? 0);
    $balance = (float) ($scheduleRow->balance_amount ?? $scheduleRow->amount_balance ?? max(0, $amountDue - $paid));
    $dueDate = ! empty($scheduleRow->due_date) ? \Carbon\Carbon::parse($scheduleRow->due_date)->format('Y-m-d') : '';
    $status = strtolower((string) ($scheduleRow->status ?? 'unpaid'));
    $statuses = [
        'auto' => 'Auto',
        'pending' => 'Pending',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
        'paid' => 'Paid',
        'late' => 'Late',
        'completed' => 'Completed',
    ];
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => route('loan-management.loans.schedules.update', ['loan' => $loanRow->id, 'schedule' => $scheduleRow->id]), 'method' => 'post', 'id' => 'loan_schedule_update_form']) !!}
        <input type="hidden" name="return_to" value="{{ url()->previous() }}">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-pencil"></i> Edit Payment Schedule
            </h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="well well-sm">
                        <strong>Loan #:</strong> {{ $loanRow->loan_number ?? $loanRow->id }}<br>
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
                        {!! Form::label('balance_amount', 'Balance') !!}
                        <input type="number" step="0.01" min="0" name="balance_amount" id="balance_amount" class="form-control schedule-number" value="{{ number_format($balance, 2, '.', '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left loan-schedule-recalculate">
                <i class="fa fa-calculator"></i> Auto Balance
            </button>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                @lang('messages.update')
            </button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
                @lang('messages.close')
            </button>
        </div>
        {!! Form::close() !!}
    </div>
</div>

<script>
$(function () {
    var $form = $('#loan_schedule_update_form');

    function numberValue(selector) {
        return parseFloat($form.find(selector).val()) || 0;
    }

    function recalculateBalance() {
        var amountDue = numberValue('[name="schedule_amount"]');
        var paid = numberValue('[name="paid_amount"]');
        $form.find('[name="balance_amount"]').val(Math.max(amountDue - paid, 0).toFixed(2));
    }

    $form.on('click', '.loan-schedule-recalculate', recalculateBalance);

    $form.off('submit.loanScheduleModal').on('submit.loanScheduleModal', function (e) {
        e.preventDefault();
        var $buttons = $form.find('button[type="submit"], .loan-schedule-recalculate');
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

                $('.view_modal').modal('hide');
                window.location.href = (res.data && res.data.redirect_url) ? res.data.redirect_url : window.location.href;
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    alert(xhr.responseJSON.errors[firstKey][0] || 'Validation failed');
                    return;
                }

                alert(xhr.responseJSON?.message || 'Failed to update payment schedule');
            },
            complete: function () {
                $buttons.prop('disabled', false);
            }
        });
    });
});
</script>
