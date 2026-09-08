@php
    $loanNumber = $loanRow->loan_number ?? $loanRow->id;
    $customerName = trim((string) ($loanRow->customer_khmer_name ?? '')) ?: ($loanRow->customer_name_snapshot ?? '-');
    $loanBalance = (float) ($loanRow->balance_amount ?? 0);
    $loanCurrency = $loanRow->currency ?? 'USD';
    $telegramCustomerId = (int) ($loanRow->customer_id ?? 0);
    $telegramLinkUrl = $telegramCustomerId > 0 ? route('loan-management.customers.telegram.link', $telegramCustomerId) : null;
    $telegramLinked = (bool) ($telegramLinked ?? false);
    $scheduleLabel = null;

    if (! empty($selectedSchedule)) {
        $scheduleDueDate = ! empty($selectedSchedule->due_date) ? \Carbon\Carbon::parse($selectedSchedule->due_date)->format('d-m-Y') : '-';
        $scheduleBalance = (float) ($selectedSchedule->balance_amount ?? $selectedSchedule->amount_balance ?? $selectedSchedule->schedule_amount ?? $selectedSchedule->amount_due ?? 0);
        $scheduleLabel = '#'.($selectedSchedule->installment_no ?? $selectedSchedule->id).' - '.$scheduleDueDate.' - '.number_format($scheduleBalance, 2);
    }

    $paymentTypes = $paymentTypes ?? ['cash' => 'Cash'];
    $isDepositPayment = $isDepositPayment ?? request()->boolean('deposit_payment');
    $defaultPaymentMethod = $defaultPaymentMethod ?? (array_key_exists('cash', $paymentTypes) ? 'cash' : array_key_first($paymentTypes));
    $suggestedPaymentTotal = number_format(max(0.01, (float) $defaultAmount), 2, '.', '');
    $loanBalanceAmount = number_format(max(0.01, $loanBalance), 2, '.', '');
    $payOffAmount = number_format(max(0.01, (float) ($payOffAmount ?? $loanBalance)), 2, '.', '');
    $paymentLineLabels = [
        'payment_method' => lm_label('lang_v1.payment_method', 'Payment Method', 'វិធីបង់ប្រាក់'),
        'amount' => lm_label('sale.amount', 'Amount', 'ចំនួនប្រាក់'),
        'payment_ref_no' => lm_label('account.payment_ref_no', 'Payment Reference', 'លេខយោងការបង់ប្រាក់'),
        'payment_doc' => 'Payment Doc',
        'payment_note' => lm_label('lang_v1.payment_note', 'Payment Note', 'កំណត់ចំណាំការបង់ប្រាក់'),
        'note_date_time' => lm_label('loanmanagement::ui.date_time', 'Date time', 'កាលបរិច្ឆេទ និងម៉ោង'),
        'paid_on' => lm_label('lang_v1.paid_on', 'Paid On', 'បានបង់នៅថ្ងៃ'),
        'save' => lm_label('messages.save', 'Save', 'រក្សាទុក'),
        'close' => lm_label('messages.close', 'Close', 'បិទ'),
        'total' => lm_label('sale.total', 'Total', 'សរុប'),
        'add' => lm_label('messages.add', 'Add', 'បន្ថែម'),
    ];
@endphp

<style>
    #loan_payment_add_form .lm-payment-modal__summary {
        display: grid;
        grid-template-columns: 1.2fr .9fr .9fr;
        gap: 12px;
        margin-bottom: 14px;
    }
    #loan_payment_add_form .lm-payment-summary-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        padding: 14px 16px;
        min-height: 88px;
    }
    #loan_payment_add_form .lm-payment-summary-card__label {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    #loan_payment_add_form .lm-payment-summary-card__value {
        display: block;
        color: #0f172a;
        font-size: 18px;
        font-weight: 700;
        line-height: 1.25;
    }
    #loan_payment_add_form .lm-payment-summary-card__sub {
        display: block;
        color: #64748b;
        font-size: 12px;
        margin-top: 4px;
    }
    #loan_payment_add_form .lm-payment-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .05);
        margin-bottom: 14px;
        overflow: hidden;
    }
    #loan_payment_add_form .lm-payment-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 12px 16px;
    }
    #loan_payment_add_form .lm-payment-panel__title {
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
        margin: 0;
    }
    #loan_payment_add_form .lm-payment-panel__body {
        padding: 16px;
    }
    #loan_payment_add_form .lm-payment-total-strip {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 24px;
    }
    #loan_payment_add_form .lm-payment-total-box {
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        background: #f0fdf4;
        padding: 10px 12px;
    }
    #loan_payment_add_form .lm-payment-total-box--remaining {
        border-color: #fde68a;
        background: #fffbeb;
    }
    #loan_payment_add_form .lm-payment-total-box span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    #loan_payment_add_form .lm-payment-total-box strong {
        display: block;
        color: #166534;
        font-size: 17px;
        margin-top: 2px;
    }
    #loan_payment_add_form .lm-payment-total-box--remaining strong {
        color: #92400e;
    }
    #loan_payment_add_form .loan-payment-line {
        display: grid;
        grid-template-columns: minmax(140px, 1fr) minmax(120px, .8fr) minmax(140px, .9fr) minmax(210px, 1.25fr) minmax(180px, 1fr) 42px;
        gap: 12px;
        align-items: start;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 12px;
    }
    #loan_payment_add_form .loan-payment-note-group {
        display: flex;
        align-items: stretch;
        width: 100%;
    }
    #loan_payment_add_form .loan-payment-note-group .payment-line-note {
        flex: 1 1 auto;
        min-height: 38px;
        max-height: 180px;
        resize: none;
        overflow-y: hidden;
        line-height: 1.45;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    #loan_payment_add_form .loan-payment-note-group .add-payment-note-datetime {
        width: 38px;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        color: #475569;
    }
    #loan_payment_add_form .payment-note-datetime {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }
    #loan_payment_add_form .loan-payment-line-action {
        display: flex;
        justify-content: center;
        padding-top: 22px;
    }
    #loan_payment_add_form .remove-loan-payment-line {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 4px;
    }
    #loan_payment_add_form .loan-payment-line > [class*="col-"] {
        float: none;
        width: auto;
        padding-left: 0;
        padding-right: 0;
    }
    #loan_payment_add_form .loan-payment-line::before,
    #loan_payment_add_form .loan-payment-line::after {
        display: none;
    }
    @media (max-width: 991px) {
        #loan_payment_add_form .lm-payment-modal__summary,
        #loan_payment_add_form .lm-payment-total-strip {
            grid-template-columns: 1fr;
        }
        #loan_payment_add_form .loan-payment-line {
            grid-template-columns: 1fr 1fr;
        }
        #loan_payment_add_form .loan-payment-line-action {
            justify-content: flex-start;
            padding-top: 0;
        }
    }
    @media (max-width: 640px) {
        #loan_payment_add_form .loan-payment-line {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => route('loan-management.loans.payment.store', $loanRow->id), 'method' => 'post', 'id' => 'loan_payment_add_form', 'files' => true]) !!}
        <input type="hidden" name="return_to" value="{{ request('return_to', route('loan-management.dashboard')) }}">
        @if($isDepositPayment)
            <input type="hidden" name="deposit_payment" value="1">
        @endif
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ $paymentLineLabels['close'] }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                <i class="fa fa-money"></i> {{ $isDepositPayment ? 'Add Customer Deposit Payment' : 'Add Installment Payment' }}
            </h4>
        </div>

        <div class="modal-body">
            <div class="lm-payment-modal__summary">
                <div class="lm-payment-summary-card">
                    <span class="lm-payment-summary-card__label">Customer</span>
                    <span class="lm-payment-summary-card__value">{{ $customerName }}</span>
                    <span class="lm-payment-summary-card__sub">Installment # {{ $loanNumber }}</span>
                        @if($telegramLinkUrl && auth()->user() && auth()->user()->can('loan_management.edit'))
                            <div style="margin-top:10px;">
                                @if($telegramLinked)
                                    <button type="button" class="btn btn-default btn-xs" disabled>
                                        <i class="fa fa-check-circle"></i> Telegram Connected
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-info btn-xs js-payment-telegram-link"
                                            data-url="{{ $telegramLinkUrl }}"
                                            data-customer="{{ $customerName }}">
                                        <i class="fa fa-paper-plane"></i> Connect Telegram
                                    </button>
                                    <small class="help-block" style="margin-bottom:0;">Creates a limited-time, one-use link and QR code.</small>
                                @endif
                            </div>
                        @endif
                </div>
                <div class="lm-payment-summary-card">
                    <span class="lm-payment-summary-card__label">Current Balance</span>
                    <span class="lm-payment-summary-card__value">{{ number_format($loanBalance, 2) }}</span>
                    <span class="lm-payment-summary-card__sub">{{ $loanCurrency }}</span>
                </div>
                <div class="lm-payment-summary-card">
                    <span class="lm-payment-summary-card__label">Pay Off Amount</span>
                    <span class="lm-payment-summary-card__value">{{ $payOffAmount }}</span>
                    <span class="lm-payment-summary-card__sub">{{ $loanCurrency }}</span>
                </div>
            </div>

            <div class="row" id="paymentTelegramLinkPanel" style="display:none;">
                <div class="col-sm-12">
                    <div class="alert alert-info" style="margin-bottom:12px;">
                        <div class="row">
                            <div class="col-sm-4 text-center">
                                <img id="paymentTelegramQr" src="" alt="Telegram QR code" style="width:180px;height:180px;max-width:100%;border:1px solid #dbe4ef;border-radius:8px;padding:8px;background:#fff;">
                            </div>
                            <div class="col-sm-8">
                                <strong><i class="fa fa-paper-plane"></i> Telegram customer link</strong>
                                <p class="help-block" style="margin:6px 0 10px;">Share this link with the customer. Valid for a limited time and can only be used once.</p>
                                <input type="text" class="form-control" id="paymentTelegramLinkInput" readonly>
                                <div class="help-block" id="paymentTelegramExpires" style="margin-bottom:10px;"></div>
                                <a href="#" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="paymentTelegramOpenLink">
                                    <i class="fa fa-external-link"></i> Open Link
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="payment_row">
                <div class="lm-payment-panel">
                    <div class="lm-payment-panel__header">
                        <h3 class="lm-payment-panel__title"><i class="fa fa-check-circle"></i> Payment Details</h3>
                    </div>
                    <div class="lm-payment-panel__body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! Form::label('paid_date', $paymentLineLabels['paid_on'] . ':*') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fa fa-calendar"></i>
                                        </span>
                                        <input type="date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                            </div>

                            @if(!$isDepositPayment)
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! Form::label('schedule_id', 'Payment target:') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fa fa-calendar-check-o"></i>
                                        </span>
                                        <select name="schedule_id" id="loan_payment_schedule_id" class="form-control">
                                            <option value="">Auto apply to oldest unpaid</option>
                                            @foreach($schedules as $schedule)
                                                @php
                                                    $rowBalance = (float) ($schedule->balance_amount ?? $schedule->amount_balance ?? $schedule->schedule_amount ?? $schedule->amount_due ?? 0);
                                                    $rowDueDate = ! empty($schedule->due_date) ? \Carbon\Carbon::parse($schedule->due_date)->format('d-m-Y') : '-';
                                                    $rowLabel = '#'.($schedule->installment_no ?? $schedule->id).' - '.$rowDueDate.' - '.number_format($rowBalance, 2).' '.$loanCurrency;
                                                @endphp
                                                <option value="{{ $schedule->id }}" data-balance="{{ number_format($rowBalance, 2, '.', '') }}" {{ (int)($selectedScheduleId ?? optional($selectedSchedule)->id) === (int) $schedule->id ? 'selected' : '' }}>
                                                    {{ $rowLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <span class="loan-schedule-display">{{ $scheduleLabel ?: 'Auto apply to oldest unpaid' }}</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="checkbox" style="margin-top:30px;">
                                    <label>
                                        <input type="checkbox" name="pay_off" value="1" class="loan-pay-off-option">
                                        Pay off loan
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-2 loan-pay-off-discount-wrap" style="display:none;">
                                <div class="form-group">
                                    {!! Form::label('pay_off_discount_amount', 'Discount:') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fa fa-tag"></i>
                                        </span>
                                        <input type="text" inputmode="decimal" name="pay_off_discount_amount" id="pay_off_discount_amount" class="form-control loan-pay-off-discount" value="0.00" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="col-md-12">
                                <div class="lm-payment-total-strip">
                                    <div class="lm-payment-total-box">
                                        <span>{{ $paymentLineLabels['total'] }}</span>
                                        <strong class="display_currency loan-payment-total" data-currency_symbol="true">{{ number_format(max(0.01, (float) $defaultAmount), 2, '.', '') }}</strong>
                                    </div>
                                    <div class="lm-payment-total-box lm-payment-total-box--remaining">
                                        <span>Remaining</span>
                                        <strong class="display_currency loan-payment-remaining" data-currency_symbol="true">0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lm-payment-panel">
                    <div class="lm-payment-panel__header">
                        <h3 class="lm-payment-panel__title"><i class="fa fa-credit-card"></i> Payment Methods</h3>
                        <button type="button" class="btn btn-primary btn-sm add-loan-payment-line">
                            <i class="fa fa-plus"></i> {{ $paymentLineLabels['add'] }}
                        </button>
                    </div>
                    <div class="lm-payment-panel__body loan-payment-lines">
                        <div class="row loan-payment-line" data-index="0">
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('payment_lines_0_method', $paymentLineLabels['payment_method'] . ':*') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fas fa-money-bill-alt"></i>
                                        </span>
                                        {!! Form::select('payment_lines[0][method]', $paymentTypes, $defaultPaymentMethod, ['class' => 'form-control payment-line-method', 'id' => 'payment_lines_0_method', 'style' => 'width:100%;', 'required']) !!}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('payment_lines_0_amount', $paymentLineLabels['amount'] . ':*') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fas fa-money-bill-alt"></i>
                                        </span>
                                        <input type="number" step="0.01" min="0.01" name="payment_lines[0][amount]" id="payment_lines_0_amount" class="form-control input_number payment-line-amount" value="{{ number_format(max(0.01, (float) $defaultAmount), 2, '.', '') }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('payment_lines_0_reference_number', $paymentLineLabels['payment_ref_no'] . ':') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fa fa-hashtag"></i>
                                        </span>
                                        <input type="text" name="payment_lines[0][reference_number]" id="payment_lines_0_reference_number" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('payment_lines_0_payment_docs', 'Payment Doc:') !!}
                                    <textarea name="payment_lines[0][payment_doc_text]" id="payment_lines_0_payment_doc_text" class="form-control payment-line-doc-text" rows="2" placeholder="Write or paste payment document text"></textarea>
                                    <input type="file" name="payment_lines[0][payment_docs][]" id="payment_lines_0_payment_docs" class="form-control payment-line-doc" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" multiple>
                                    <small class="help-block payment-doc-help">Write text, paste a screenshot/file, or upload multiple files.</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('payment_lines_0_note', $paymentLineLabels['payment_note'] . ':') !!}
                                    <div class="loan-payment-note-group">
                                        <textarea name="payment_lines[0][note]" id="payment_lines_0_note" class="form-control payment-line-note" rows="1" placeholder="Add payment note"></textarea>
                                        <button type="button" class="btn btn-default add-payment-note-datetime" title="Add date time">
                                            <i class="fa fa-calendar"></i>
                                        </button>
                                    </div>
                                    <input type="datetime-local" class="payment-note-datetime" tabindex="-1">
                                </div>
                            </div>
                            <div class="col-md-1 loan-payment-line-action">
                                <button type="button" class="btn btn-danger btn-sm remove-loan-payment-line">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                <i class="fa fa-save"></i> {{ $paymentLineLabels['save'] }}
            </button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
                <i class="fa fa-times"></i> {{ $paymentLineLabels['close'] }}
            </button>
        </div>
        {!! Form::close() !!}
    </div>
</div>

<script>
$(function () {
    var $form = $('#loan_payment_add_form');
    var defaultRedirectUrl = "{{ route('loan-management.dashboard') }}";

    $(document).off('submit.loanPaymentModal.delegated').on('submit.loanPaymentModal.delegated', '#loan_payment_add_form', function (e) {
        e.preventDefault();
        var $submitButtons = $form.find('button[type="submit"], .remove-loan-payment-line, .add-loan-payment-line');
        $submitButtons.prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: new FormData($form[0]),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Payment added successfully');
                }

                var $modal = $('.view_modal');
                var redirectUrl = res.data && res.data.redirect_url ? res.data.redirect_url : defaultRedirectUrl;
                var invoiceUrl = (res.data && res.data.invoice_url) ? res.data.invoice_url : (res.data && res.data.print_url ? res.data.print_url : '');
                var invoiceCustomer = {
                    id: res.data && res.data.customer_id ? res.data.customer_id : 0,
                    name: (res.data && res.data.customer_name) ? res.data.customer_name : '',
                    telegramLinked: !!(res.data && res.data.telegram_linked),
                    loanId: res.data && res.data.loan_id ? res.data.loan_id : {{ (int) ($loanRow->id ?? 0) }},
                    loanNumber: (res.data && res.data.loan_number) ? res.data.loan_number : @json((string) ($loanNumber ?? '')),
                    balanceAmount: (res.data && res.data.balance_amount) ? res.data.balance_amount : @json((string) ($loanRow->balance_amount ?? ''))
                };
                var $loanSections = $modal.find('#loanShowSections');
                var sectionReloadUrl = $loanSections.length ? ($loanSections.data('url') || '') : '';

                $modal.modal('hide');

                showPaymentInvoicePreview(invoiceUrl, invoiceCustomer, function () {
                    if (sectionReloadUrl) {
                        window.jQuery.ajax({
                            url: sectionReloadUrl,
                            dataType: 'html',
                            success: function (html) { $loanSections.html(html); }
                        });
                    }
                    window.location.href = redirectUrl;
                });
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    var message = xhr.responseJSON.errors[firstKey][0] || 'Validation failed';
                    alert(message);
                    return;
                }

                alert(xhr.responseJSON?.message || 'Failed to save payment');
            },
            complete: function () {
                $submitButtons.prop('disabled', false);
            }
        });
    });

    function showPaymentInvoicePreview(invoiceUrl, customer, onDone) {
        customer = customer || {};
        var $previewModal = $('<div class="modal fade lm-invoice-preview-modal">' +
            '<div class="modal-dialog modal-xl" role="document" style="width:96%;max-width:1180px;">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h4 class="modal-title"><i class="fa fa-file-text-o"></i> Payment Saved - Invoice Preview</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:0;height:82vh;">' +
                        (invoiceUrl
                            ? '<iframe id="lmInvoicePreviewFrame" src="' + $('<div>').text(invoiceUrl).html() + '" style="width:100%;height:100%;border:0;"></iframe>'
                            : '<div class="text-center text-muted" style="padding:60px;">No invoice preview available.</div>') +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<span class="lm-invoice-send-status text-muted pull-left" style="display:none;margin-top:8px;"></span>' +
                        '<button type="button" class="btn btn-success lm-invoice-send-btn" data-cid="' + (customer.id || 0) + '" data-loan-id="' + (customer.loanId || 0) + '" data-loan-number="' + $('<div>').text(customer.loanNumber || '').html() + '" data-balance="' + $('<div>').text(customer.balanceAmount || '').html() + '" data-name="' + $('<div>').text(customer.name || 'Customer').html() + '" data-linked="' + (customer.telegramLinked ? '1' : '0') + '">' +
                            '<i class="fa fa-paper-plane"></i> Send invoice to customer</button>' +
                        '<button type="button" class="btn btn-primary lm-invoice-done-btn"><i class="fa fa-check"></i> Done</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>').appendTo('body');

        function invoicePreviewStatus(message, type) {
            var $status = $previewModal.find('.lm-invoice-send-status');
            $status
                .removeClass('text-muted text-success text-danger')
                .addClass(type === 'success' ? 'text-success' : (type === 'error' ? 'text-danger' : 'text-muted'))
                .text(message || '')
                .toggle(!!message);
        }

        function notifyInvoicePreview(message, type) {
            invoicePreviewStatus(message, type);
            if (window.toastr) {
                if (type === 'error') {
                    toastr.error(message);
                } else if (type === 'success') {
                    toastr.success(message);
                } else {
                    toastr.info(message);
                }
            } else if (type === 'error') {
                alert(message);
            }
        }

        $previewModal.on('click', '.lm-invoice-send-btn', function () {
            var $button = $(this);
            var cid = parseInt($button.data('cid'), 10) || 0;
            var loanId = parseInt($button.data('loan-id'), 10) || 0;
            var loanNumber = $button.data('loan-number') || '';
            var balanceAmount = $button.data('balance') || '';
            var name = $button.data('name') || 'Customer';
            var linked = String($button.data('linked')) === '1';

            if (!cid) {
                notifyInvoicePreview('This loan has no linked customer.', 'error');
                return;
            }
            if (!linked) {
                notifyInvoicePreview('This customer is not connected to Telegram yet. Please link them in the loan details.', 'error');
                return;
            }
            if (typeof window.loanManagementSendInvoiceToTelegramCustomer !== 'function' && typeof window.loanManagementOpenTelegramCustomer !== 'function') {
                notifyInvoicePreview('Telegram chat is not available on this page.', 'error');
                return;
            }

            var context = {
                auto_action: 'invoice',
                loan_id: loanId,
                loan_number: loanNumber,
                balance_amount: balanceAmount,
                preview_frame_id: 'lmInvoicePreviewFrame'
            };

            if (typeof window.loanManagementSendInvoiceToTelegramCustomer === 'function') {
                var originalHtml = $button.html();
                $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Previewing invoice...');
                invoicePreviewStatus('Opening invoice send preview...', 'info');

                function restoreSendButton() {
                    $button.prop('disabled', false).html(originalHtml);
                }

                window.loanManagementSendInvoiceToTelegramCustomer(cid, name, linked, context)
                    .then(function () {
                        notifyInvoicePreview('Invoice sent to customer chat.', 'success');
                        restoreSendButton();
                        $previewModal.modal('hide');
                    })
                    .catch(function (error) {
                        if (error && error.cancelled) {
                            invoicePreviewStatus('Invoice sending cancelled.', 'info');
                            restoreSendButton();
                            return;
                        }

                        var message = error && error.message ? error.message : 'Unable to send invoice to customer chat.';
                        notifyInvoicePreview(message, 'error');
                        restoreSendButton();
                    });

                return;
            }

            $previewModal.modal('hide');
            window.loanManagementOpenTelegramCustomer(cid, name, linked, context);
            notifyInvoicePreview('Opening Telegram chat to send invoice...', 'success');
        });

        $previewModal.on('hidden.bs.modal', function () {
            $previewModal.remove();
        });

        $previewModal.on('click', '.lm-invoice-done-btn', function () {
            $previewModal.modal('hide');
            if (typeof onDone === 'function') {
                onDone();
            }
        });

        $previewModal.modal({ backdrop: 'static', keyboard: false });
    }

    try {
    var paymentTypes = @json($paymentTypes);
    var defaultPaymentMethod = @json($defaultPaymentMethod);
    var suggestedTotal = parseFloat(@json($suggestedPaymentTotal)) || 0;
    var normalLoanBalance = parseFloat(@json($loanBalanceAmount)) || suggestedTotal;
    var payOffBalance = parseFloat(@json($payOffAmount)) || normalLoanBalance;
    var previousScheduleId = $form.find('[name="schedule_id"]').val();
    var labels = @json($paymentLineLabels);
    var copyInfo = @json($copyInfo ?? []);
    var copyInfoUrl = '{{ route('loan-management.loans.payment.copy-info', $loanRow->id) }}';
    var isDepositPayment = @json((bool) $isDepositPayment);
    var telegramLinkUrl = @json($telegramLinkUrl);

    if (!copyInfo || Object.keys(copyInfo).length === 0) {
        $.ajax({ url: copyInfoUrl, dataType: 'json' })
            .done(function (res) { if (res.success && res.data && res.data.info) { copyInfo = res.data.info; } })
            .fail(function () {});
    }

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
        var discount = $form.find('.loan-pay-off-option').is(':checked')
            ? parseDecimalInput($form.find('.loan-pay-off-discount').val())
            : 0;
        $form.find('.loan-payment-total').text(total.toFixed(2));
        $form.find('.loan-payment-remaining').text(Math.max(suggestedTotal - total - discount, 0).toFixed(2));
    }

    function parseDecimalInput(value) {
        value = String(value || '').replace(/,/g, '.').replace(/[^0-9.]/g, '');
        var firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }

        return parseFloat(value) || 0;
    }

    function sanitizeDecimalInput($input) {
        var value = String($input.val() || '').replace(/,/g, '.').replace(/[^0-9.]/g, '');
        var firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }
        $input.val(value);

        return parseFloat(value) || 0;
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
        if (isDepositPayment || !$form.find('[name="schedule_id"]').length) {
            return suggestedTotal;
        }

        var balance = parseFloat($form.find('[name="schedule_id"] option:selected').data('balance'));

        return balance > 0 ? balance : normalLoanBalance;
    }

    function updateScheduleDisplay() {
        if (isDepositPayment) {
            $form.find('.loan-schedule-display').text('Customer deposit payment');
            return;
        }

        var $selected = $form.find('[name="schedule_id"] option:selected');
        var text = $.trim($selected.text());
        if (!text) {
            text = 'Auto apply to oldest unpaid';
        }
        $form.find('.loan-schedule-display').text(text);
    }

    function moneyText(value) {
        return (parseFloat(value) || 0).toFixed(2);
    }

    function localDateTimeValue(date) {
        var pad = function (value) {
            return String(value).padStart(2, '0');
        };

        return [
            date.getFullYear(),
            pad(date.getMonth() + 1),
            pad(date.getDate())
        ].join('-') + 'T' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
    }

    function noteDateTimeText(value) {
        if (!value) {
            return '';
        }

        var parts = value.split('T');
        var dateParts = (parts[0] || '').split('-');
        var time = (parts[1] || '').slice(0, 5);
        if (dateParts.length !== 3 || !time) {
            return value;
        }

        return dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0] + ' ' + time;
    }

    function resizePaymentNoteField(field) {
        if (!field) {
            return;
        }

        field.style.height = 'auto';
        var height = Math.max(field.scrollHeight, 38);
        field.style.overflowY = height > 180 ? 'auto' : 'hidden';
        field.style.height = Math.min(height, 180) + 'px';
    }

    function resizePaymentNotes($scope) {
        $scope.find('.payment-line-note').each(function () {
            resizePaymentNoteField(this);
        });
    }

    function appendDateTimeToNote($line) {
        var $picker = $line.find('.payment-note-datetime');
        var $note = $line.find('.payment-line-note');
        var value = $picker.val();

        if (!value) {
            value = localDateTimeValue(new Date());
            $picker.val(value);
        }

        var text = noteDateTimeText(value);
        var current = $.trim($note.val() || '');
        if (text && current.indexOf(text) === -1) {
            $note.val($.trim((current ? current + ' ' : '') + text)).trigger('input');
        }
        $note.focus();
    }

    function paymentCopyAmounts() {
        var cash = 0;
        var bank = 0;

        $form.find('.loan-payment-line').each(function () {
            var amount = parseFloat($(this).find('.payment-line-amount').val()) || 0;
            var method = String($(this).find('.payment-line-method').val() || '').toLowerCase();
            var methodText = String($(this).find('.payment-line-method option:selected').text() || '').toLowerCase();
            var isCash = method.indexOf('cash') !== -1 || methodText.indexOf('cash') !== -1;

            if (isCash) {
                cash += amount;
            } else {
                bank += amount;
            }
        });

        return {
            cash: cash,
            bank: bank
        };
    }

    function loanPaymentCopyText() {
        return [
            copyInfo.invoice || '',
            copyInfo.name_khmer || '',
            copyInfo.phone || '',
            copyInfo.id_card || '',
            copyInfo.village || '',
            copyInfo.commune || '',
            copyInfo.district || '',
            copyInfo.province || '',
            copyInfo.product || '',
            copyInfo.qty || '',
            copyInfo.unit_price || '',
            copyInfo.amount_cash || '0.00',
            copyInfo.amount_bank || '0.00',
            copyInfo.duration_m || '',
            copyInfo.interest_percent || '0.00',
            copyInfo.first_due || ''
        ].map(function (value) {
            return String(value == null ? '' : value).trim();
        }).join(',');
    }

    function copyTextToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        var deferred = $.Deferred();
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            deferred.resolve();
        } catch (e) {
            deferred.reject(e);
        }

        document.body.removeChild(textarea);
        return deferred.promise();
    }

    function formatLmExpiry(value) {
        if (!value) {
            return '';
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return String(value);
        }
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    $form.on('click', '.js-payment-telegram-link', function () {
        var $button = $(this);
        var url = $button.data('url') || telegramLinkUrl;
        if (!url) {
            return;
        }

        $button.prop('disabled', true);
        $.post(url, {_token: $('meta[name="csrf-token"]').attr('content')})
            .done(function (res) {
                var link = res && res.link ? res.link : '';
                var expires = res && res.expires_at ? formatLmExpiry(res.expires_at) : '';
                var qrUrl = link ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(link) : '';

                $('#paymentTelegramQr').attr('src', qrUrl);
                $('#paymentTelegramLinkInput').val(link);
                $('#paymentTelegramOpenLink').attr('href', link || '#');
                $('#paymentTelegramExpires').text(expires ? 'Expires: ' + expires : '');
                $('#paymentTelegramLinkPanel').slideDown(150);

                if (window.toastr) {
                    toastr.success('Telegram link created');
                }
            })
            .fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || 'Unable to create Telegram link.');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    function applyPayTarget(formatDiscount) {
        formatDiscount = formatDiscount !== false;

        if (isDepositPayment) {
            updateLoanPaymentTotal();
            updateScheduleDisplay();
            return;
        }

        if ($form.find('.loan-pay-off-option').is(':checked')) {
            previousScheduleId = $form.find('[name="schedule_id"]').val() || previousScheduleId;
            suggestedTotal = payOffBalance;
            $form.find('[name="schedule_id"]').val('').trigger('change');
            $form.find('.loan-pay-off-discount-wrap').show();
            var $discountInput = $form.find('.loan-pay-off-discount');
            var discount = Math.min(sanitizeDecimalInput($discountInput), payOffBalance);
            if (formatDiscount) {
                $discountInput.val(discount.toFixed(2));
            } else if (discount >= payOffBalance) {
                $discountInput.val(payOffBalance.toFixed(2));
            }
            setSinglePaymentAmount(Math.max(payOffBalance - discount, 0.01));
            return;
        }

        $form.find('.loan-pay-off-discount-wrap').hide();
        $form.find('.loan-pay-off-discount').val('0.00');
        if (!$form.find('[name="schedule_id"]').val() && previousScheduleId) {
            $form.find('[name="schedule_id"]').val(previousScheduleId).trigger('change');
        } else if (!$form.find('[name="schedule_id"]').val()) {
            $form.find('[name="schedule_id"]').val('').trigger('change');
        }

        suggestedTotal = selectedScheduleBalance();
        setSinglePaymentAmount(suggestedTotal);
    }

    function refreshRemoveButtons() {
        $form.find('.remove-loan-payment-line').prop('disabled', false);
    }

    function updatePaymentDocHelp($input) {
        var files = $input[0].files || [];
        var text = files.length ? files.length + ' file(s) selected' : 'Write text, paste a screenshot/file, or upload multiple files.';
        $input.closest('.form-group').find('.payment-doc-help').text(text);
    }

    function appendFilesToInput(input, files) {
        if (!input || !files || !files.length || typeof DataTransfer === 'undefined') {
            return false;
        }

        var transfer = new DataTransfer();
        Array.prototype.forEach.call(input.files || [], function (file) {
            transfer.items.add(file);
        });
        Array.prototype.forEach.call(files, function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
        updatePaymentDocHelp($(input));

        return true;
    }

    function clipboardFiles(event) {
        var clipboard = event.originalEvent && event.originalEvent.clipboardData;
        if (!clipboard) {
            return [];
        }

        var files = [];
        Array.prototype.forEach.call(clipboard.items || [], function (item) {
            if (item.kind === 'file') {
                var file = item.getAsFile();
                if (file) {
                    files.push(file);
                }
            }
        });

        return files;
    }

    function clipboardText(event) {
        var clipboard = event.originalEvent && event.originalEvent.clipboardData;
        return clipboard ? $.trim(clipboard.getData('text') || '') : '';
    }

    $form.on('click', '.add-loan-payment-line', function () {
        var index = $form.find('.loan-payment-line').length;
        var suggestedAmount = remainingBeforeNewRow().toFixed(2);
        var row = [
            '<div class="row loan-payment-line" data-index="' + index + '">',
                '<div class="col-md-2"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_method">' + labels.payment_method + ':*</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>',
                    '<select name="payment_lines[' + index + '][method]" id="payment_lines_' + index + '_method" class="form-control payment-line-method" style="width:100%;" required>' + optionsHtml(defaultPaymentMethod) + '</select>',
                    '</div></div></div>',
                '<div class="col-md-2"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_amount">' + labels.amount + ':*</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>',
                    '<input type="number" step="0.01" min="0.01" name="payment_lines[' + index + '][amount]" id="payment_lines_' + index + '_amount" class="form-control input_number payment-line-amount" value="' + suggestedAmount + '" required>',
                    '</div></div></div>',
                '<div class="col-md-2"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_reference_number">' + labels.payment_ref_no + ':</label>',
                    '<div class="input-group"><span class="input-group-addon"><i class="fa fa-hashtag"></i></span>',
                    '<input type="text" name="payment_lines[' + index + '][reference_number]" id="payment_lines_' + index + '_reference_number" class="form-control">',
                    '</div></div></div>',
                '<div class="col-md-3"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_payment_docs">' + labels.payment_doc + ':</label>',
                    '<textarea name="payment_lines[' + index + '][payment_doc_text]" id="payment_lines_' + index + '_payment_doc_text" class="form-control payment-line-doc-text" rows="2" placeholder="Write or paste payment document text"></textarea>',
                    '<input type="file" name="payment_lines[' + index + '][payment_docs][]" id="payment_lines_' + index + '_payment_docs" class="form-control payment-line-doc" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" multiple>',
                    '<small class="help-block payment-doc-help">Write text, paste a screenshot/file, or upload multiple files.</small>',
                    '</div></div>',
                '<div class="col-md-2"><div class="form-group">',
                    '<label for="payment_lines_' + index + '_note">' + labels.payment_note + ':</label>',
                    '<div class="loan-payment-note-group">',
                        '<textarea name="payment_lines[' + index + '][note]" id="payment_lines_' + index + '_note" class="form-control payment-line-note" rows="1" placeholder="Add payment note"></textarea>',
                        '<button type="button" class="btn btn-default add-payment-note-datetime" title="' + labels.note_date_time + '"><i class="fa fa-calendar"></i></button>',
                    '</div>',
                    '<input type="datetime-local" class="payment-note-datetime" tabindex="-1">',
                    '</div></div>',
                '<div class="col-md-1 loan-payment-line-action"><button type="button" class="btn btn-danger btn-sm remove-loan-payment-line"><i class="fa fa-times"></i></button></div>',
            '</div>'
        ].join('');

        $form.find('.loan-payment-lines').append(row);
        resizePaymentNotes($form.find('.loan-payment-lines'));
        refreshRemoveButtons();
        updateLoanPaymentTotal();
    });

    $form.on('click', '.remove-loan-payment-line', function () {
        $(this).closest('.loan-payment-line').remove();
        if ($form.find('.loan-payment-line').length === 0) {
            $form.find('.add-loan-payment-line').first().trigger('click');
        } else {
            refreshRemoveButtons();
        }
        updateLoanPaymentTotal();
    });

    $form.on('change', '.payment-line-doc', function () {
        updatePaymentDocHelp($(this));
    });

    $form.on('focus click', '.payment-line-doc', function () {
        $form.find('.payment-line-doc').removeClass('active-payment-doc-input');
        $(this).addClass('active-payment-doc-input');
    });

    $form.on('click', '.add-payment-note-datetime', function () {
        var $line = $(this).closest('.loan-payment-line');
        var $picker = $line.find('.payment-note-datetime');
        if (!$picker.val()) {
            $picker.val(localDateTimeValue(new Date()));
        }
        var picker = $picker[0];
        if (picker && typeof picker.showPicker === 'function') {
            picker.showPicker();
            return;
        }
        $picker.trigger('focus').trigger('click');
    });

    $form.on('change', '.payment-note-datetime', function () {
        appendDateTimeToNote($(this).closest('.loan-payment-line'));
    });

    $form.on('input', '.payment-line-note', function () {
        resizePaymentNoteField(this);
    });

    $form.on('paste', function (event) {
        var files = clipboardFiles(event);
        var text = clipboardText(event);

        if (files.length) {
            var input = $form.find('.payment-line-doc.active-payment-doc-input')[0]
            || $form.find('.payment-line-doc:visible').last()[0];

            if (appendFilesToInput(input, files)) {
                event.preventDefault();
                if (window.toastr) {
                    toastr.success(files.length + ' pasted file(s) added to Payment Doc');
                }
            }
            return;
        }

        if (text && !$(event.target).is('input, textarea')) {
            var $text = $form.find('.payment-line-doc-text:visible').last();
            $text.val($.trim(($text.val() || '') + "\n" + text));
            event.preventDefault();
            if (window.toastr) {
                toastr.success('Pasted text added to Payment Doc');
            }
        }
    });

    $form.on('input change', '.payment-line-amount', updateLoanPaymentTotal);
    $form.on('change', '.loan-pay-off-option', applyPayTarget);
    $form.on('input', '.loan-pay-off-discount', function () {
        if ($form.find('.loan-pay-off-option').is(':checked')) {
            applyPayTarget(false);
        }
    });
    $form.on('change blur', '.loan-pay-off-discount', function () {
        if ($form.find('.loan-pay-off-option').is(':checked')) {
            applyPayTarget(true);
        }
    });
    $form.on('change', '[name="schedule_id"]', function () {
        if ($form.find('.loan-pay-off-option').is(':checked')) {
            updateScheduleDisplay();
            return;
        }

        previousScheduleId = $(this).val();
        updateScheduleDisplay();
        applyPayTarget();
    });
    } catch (err) {
        if (window.console) { console.error('Installment payment form init error:', err); }
    }

    refreshRemoveButtons();
    updateScheduleDisplay();
    updateLoanPaymentTotal();
    resizePaymentNotes($form);
});
</script>
