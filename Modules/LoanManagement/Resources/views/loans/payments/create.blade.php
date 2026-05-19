@php
    $loanNumber = $loanRow->loan_number ?? $loanRow->id;
    $loanBalance = (float) ($loanRow->balance_amount ?? 0);
    $loanCurrency = $loanRow->currency ?? 'USD';
    $scheduleLabel = null;

    if (! empty($selectedSchedule)) {
        $scheduleDueDate = ! empty($selectedSchedule->due_date) ? \Carbon\Carbon::parse($selectedSchedule->due_date)->format('d-m-Y') : '-';
        $scheduleBalance = (float) ($selectedSchedule->balance_amount ?? $selectedSchedule->amount_balance ?? $selectedSchedule->schedule_amount ?? $selectedSchedule->amount_due ?? 0);
        $scheduleLabel = '#'.($selectedSchedule->installment_no ?? $selectedSchedule->id).' - '.$scheduleDueDate.' - '.number_format($scheduleBalance, 2);
    }

    $paymentTypes = $paymentTypes ?? ['cash' => 'Cash'];
    $defaultPaymentMethod = $defaultPaymentMethod ?? (array_key_exists('cash', $paymentTypes) ? 'cash' : array_key_first($paymentTypes));
    $suggestedPaymentTotal = number_format(max(0.01, (float) $defaultAmount), 2, '.', '');
    $loanBalanceAmount = number_format(max(0.01, $loanBalance), 2, '.', '');
    $payOffAmount = number_format(max(0.01, (float) ($payOffAmount ?? $loanBalance)), 2, '.', '');
    $paymentLineLabels = [
        'payment_method' => __('lang_v1.payment_method'),
        'amount' => __('sale.amount'),
        'payment_ref_no' => __('account.payment_ref_no'),
        'payment_note' => __('lang_v1.payment_note'),
    ];
@endphp

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => route('loan-management.loans.payment.store', $loanRow->id), 'method' => 'post', 'id' => 'loan_payment_add_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-money"></i> Add Loan Payment
            </h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="well">
                        <strong>Loan #:</strong> {{ $loanNumber }}<br>
                        <strong>Customer:</strong> {{ $loanRow->customer_name_snapshot ?? '-' }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well">
                        <strong>Balance:</strong> {{ number_format($loanBalance, 2) }} {{ $loanCurrency }}<br>
                        <strong>Pay off:</strong> {{ $payOffAmount }} {{ $loanCurrency }}<br>
                        <strong>Currency:</strong> {{ $loanCurrency }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="well">
                        <strong>Schedule:</strong>
                        @if(! empty($scheduleLabel))
                            {{ $scheduleLabel }}
                        @else
                            Auto apply to oldest unpaid
                        @endif
                    </div>
                </div>
            </div>

            <div class="row payment_row">
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('schedule_id', 'Monthly Schedule:') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-calendar-check-o"></i>
                            </span>
                        <select name="schedule_id" class="form-control select2" style="width:100%;">
                            <option value="" data-balance="{{ $loanBalanceAmount }}">Auto apply to oldest unpaid</option>
                            @foreach($schedules as $schedule)
                                @php
                                    $dueDate = ! empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-';
                                    $balance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? $schedule->schedule_amount ?? $schedule->amount_due ?? 0);
                                @endphp
                                <option value="{{ $schedule->id }}" data-balance="{{ number_format(max(0.01, $balance), 2, '.', '') }}" {{ (int) ($selectedScheduleId ?? 0) === (int) $schedule->id ? 'selected' : '' }}>
                                    #{{ $schedule->installment_no ?? $loop->iteration }} - {{ $dueDate }} - Balance {{ number_format($balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('paid_date', __('lang_v1.paid_on') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-calendar"></i>
                            </span>
                            <input type="date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="checkbox" style="margin-top:30px;">
                        <label>
                            <input type="checkbox" name="pay_off" value="1" class="loan-pay-off-option">
                            Pay off loan
                        </label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="well well-sm" style="margin-top:25px;margin-bottom:10px;">
                        <strong>@lang('sale.total'):</strong>
                        <span class="display_currency loan-payment-total" data-currency_symbol="true">{{ number_format(max(0.01, (float) $defaultAmount), 2, '.', '') }}</span>
                        <span class="pull-right">
                            <strong>Remaining:</strong>
                            <span class="display_currency loan-payment-remaining" data-currency_symbol="true">0.00</span>
                        </span>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="box box-solid bg-lightgray" style="margin-bottom:10px;">
                        <div class="box-header">
                            <h3 class="box-title">@lang('lang_v1.payment_method')</h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-box-tool add-loan-payment-line">
                                    <i class="fa fa-plus"></i> @lang('messages.add')
                                </button>
                            </div>
                        </div>
                        <div class="box-body loan-payment-lines">
                            <div class="row loan-payment-line" data-index="0">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('payment_lines_0_method', __('lang_v1.payment_method') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fas fa-money-bill-alt"></i>
                                            </span>
                                            {!! Form::select('payment_lines[0][method]', $paymentTypes, $defaultPaymentMethod, ['class' => 'form-control payment-line-method', 'id' => 'payment_lines_0_method', 'style' => 'width:100%;', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('payment_lines_0_amount', __('sale.amount') . ':*') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fas fa-money-bill-alt"></i>
                                            </span>
                                            <input type="number" step="0.01" min="0.01" name="payment_lines[0][amount]" id="payment_lines_0_amount" class="form-control input_number payment-line-amount" value="{{ number_format(max(0.01, (float) $defaultAmount), 2, '.', '') }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('payment_lines_0_reference_number', __('account.payment_ref_no') . ':') !!}
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="fa fa-hashtag"></i>
                                            </span>
                                            <input type="text" name="payment_lines[0][reference_number]" id="payment_lines_0_reference_number" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        {!! Form::label('payment_lines_0_note', __('lang_v1.payment_note') . ':') !!}
                                        <input type="text" name="payment_lines[0][note]" id="payment_lines_0_note" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm remove-loan-payment-line" style="margin-top:25px;" disabled>
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                @lang('messages.save')
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
    var $form = $('#loan_payment_add_form');
    var paymentTypes = @json($paymentTypes);
    var defaultPaymentMethod = @json($defaultPaymentMethod);
    var suggestedTotal = parseFloat(@json($suggestedPaymentTotal)) || 0;
    var normalLoanBalance = parseFloat(@json($loanBalanceAmount)) || suggestedTotal;
    var payOffBalance = parseFloat(@json($payOffAmount)) || normalLoanBalance;
    var previousScheduleId = $form.find('[name="schedule_id"]').val();
    var labels = @json($paymentLineLabels);

    function optionsHtml(selected) {
        return Object.keys(paymentTypes).map(function (key) {
            var isSelected = key === selected ? ' selected' : '';
            return '<option value="' + key + '"' + isSelected + '>' + paymentTypes[key] + '</option>';
        }).join('');
    }

    function updateLoanPaymentTotal() {
        var total = 0;
        $form.find('.payment-line-amount').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        $form.find('.loan-payment-total').text(total.toFixed(2));
        $form.find('.loan-payment-remaining').text(Math.max(suggestedTotal - total, 0).toFixed(2));
    }

    function remainingBeforeNewRow() {
        var total = 0;
        $form.find('.payment-line-amount').each(function () {
            total += parseFloat($(this).val()) || 0;
        });

        return Math.max(suggestedTotal - total, 0);
    }

    function setSinglePaymentAmount(amount) {
        $form.find('.loan-payment-line').slice(1).remove();
        $form.find('.payment-line-amount').first().val(Math.max(amount, 0.01).toFixed(2));
        refreshRemoveButtons();
        updateLoanPaymentTotal();
    }

    function selectedScheduleBalance() {
        var balance = parseFloat($form.find('[name="schedule_id"] option:selected').data('balance'));

        return balance > 0 ? balance : normalLoanBalance;
    }

    function applyPayTarget() {
        if ($form.find('.loan-pay-off-option').is(':checked')) {
            previousScheduleId = $form.find('[name="schedule_id"]').val() || previousScheduleId;
            suggestedTotal = payOffBalance;
            $form.find('[name="schedule_id"]').val('').trigger('change');
            setSinglePaymentAmount(payOffBalance);
            return;
        }

        if (!$form.find('[name="schedule_id"]').val() && previousScheduleId) {
            $form.find('[name="schedule_id"]').val(previousScheduleId).trigger('change.select2');
        }

        suggestedTotal = selectedScheduleBalance();
        setSinglePaymentAmount(suggestedTotal);
    }

    function refreshRemoveButtons() {
        var count = $form.find('.loan-payment-line').length;
        $form.find('.remove-loan-payment-line').prop('disabled', count <= 1);
    }

    $form.on('click', '.add-loan-payment-line', function () {
        var index = $form.find('.loan-payment-line').length;
        var suggestedAmount = remainingBeforeNewRow().toFixed(2);
        var row = [
            '<div class="row loan-payment-line" data-index="' + index + '">',
                '<div class="col-md-3"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_method">' + labels.payment_method + ':*</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>',
                    '<select name="payment_lines[' + index + '][method]" id="payment_lines_' + index + '_method" class="form-control payment-line-method" style="width:100%;" required>' + optionsHtml(defaultPaymentMethod) + '</select>',
                    '</div></div></div>',
                '<div class="col-md-3"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_amount">' + labels.amount + ':*</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>',
                    '<input type="number" step="0.01" min="0.01" name="payment_lines[' + index + '][amount]" id="payment_lines_' + index + '_amount" class="form-control input_number payment-line-amount" value="' + suggestedAmount + '" required>',
                    '</div></div></div>',
                '<div class="col-md-3"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_reference_number">' + labels.payment_ref_no + ':</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fa fa-hashtag"></i></span>',
                    '<input type="text" name="payment_lines[' + index + '][reference_number]" id="payment_lines_' + index + '_reference_number" class="form-control">',
                    '</div></div></div>',
                '<div class="col-md-2"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_note">' + labels.payment_note + ':</label>',
                    '<input type="text" name="payment_lines[' + index + '][note]" id="payment_lines_' + index + '_note" class="form-control">',
                    '</div></div>',
                '<div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-loan-payment-line" style="margin-top:25px;"><i class="fa fa-times"></i></button></div>',
            '</div>'
        ].join('');

        $form.find('.loan-payment-lines').append(row);
        refreshRemoveButtons();
        updateLoanPaymentTotal();
    });

    $form.on('click', '.remove-loan-payment-line', function () {
        $(this).closest('.loan-payment-line').remove();
        refreshRemoveButtons();
        updateLoanPaymentTotal();
    });

    $form.on('input change', '.payment-line-amount', updateLoanPaymentTotal);
    $form.on('change', '.loan-pay-off-option', applyPayTarget);
    $form.on('change', '[name="schedule_id"]', function () {
        if ($form.find('.loan-pay-off-option').is(':checked')) {
            return;
        }

        previousScheduleId = $(this).val();
        applyPayTarget();
    });
    refreshRemoveButtons();
    updateLoanPaymentTotal();
});
</script>
