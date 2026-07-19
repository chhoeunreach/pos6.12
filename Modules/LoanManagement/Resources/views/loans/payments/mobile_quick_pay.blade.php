@php
    $loanNumber = $loanRow->loan_number ?? $loanRow->id;
    $loanBalance = (float) ($loanRow->balance_amount ?? 0);
    $loanCurrency = $loanRow->currency ?? 'USD';
    $loanPrincipal = (float) ($loanRow->principal_amount ?? 0);
    $paidAmount = (float) ($loanRow->paid_amount ?? 0);
    $customerName = trim((string) ($loanRow->customer_khmer_name ?? '')) ?: ($loanRow->customer_name_snapshot ?? '-');
    $customerPhone = $loanRow->customer_phone_snapshot ?? '-';
    $payOffAmount = number_format(max(0.01, (float) ($payOffAmount ?? $loanBalance)), 2, '.', '');
    $defaultAmount = number_format(max(0.01, (float) ($defaultAmount ?? $loanBalance)), 2, '.', '');
@endphp

<div class="modal-dialog lm-pay-modal" role="document" style="max-width: 480px; margin: auto;">
    <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.2);">
        {{-- Header --}}
        <div class="lm-pay-header">
            <button type="button" class="lm-pay-close" data-dismiss="modal"><i class="fa fa-times"></i></button>
            <div class="lm-pay-header-info">
                <div class="lm-pay-avatar"><i class="fa fa-user"></i></div>
                <div>
                    <div class="lm-pay-customer">{{ $customerName }}</div>
                    <div class="lm-pay-phone">{{ $customerPhone }}</div>
                </div>
            </div>
            <div class="lm-pay-balance-box">
                <span class="lm-pay-balance-label">Outstanding Balance</span>
                <span class="lm-pay-balance-amount" id="lmPayBalanceDisplay">{{ $payOffAmount }}</span>
                <span class="lm-pay-currency">{{ $loanCurrency }}</span>
            </div>
        </div>

        <form id="lmQuickPayForm" method="POST" action="{{ route('loan-management.loans.payment.store', $loanRow->id) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="return_to" value="{{ url('/loan-management/loans/'.$loanRow->id.'/view') }}">
            <input type="hidden" name="pay_off" id="lmPayPayOff" value="0">
            <input type="hidden" name="pay_off_discount_amount" id="lmPayPayOffDiscountHidden" value="0.00">
            <input type="hidden" name="paid_date" value="{{ date('Y-m-d') }}">
            <input type="hidden" name="schedule_id" id="lmPayScheduleId" value="{{ $selectedScheduleId ?? '' }}">

            <div class="lm-pay-body">
                {{-- Quick Amount Buttons --}}
                <div class="lm-pay-section">
                    <div class="lm-pay-section-title">Quick Amount</div>
                    <div class="lm-pay-quick-amounts" id="lmPayQuickAmounts">
                        @php
                            $suggested = (float) $defaultAmount;
                            $quarter = max(1, round($loanBalance * 0.25, 2));
                            $half = max(1, round($loanBalance * 0.50, 2));
                            $threeQ = max(1, round($loanBalance * 0.75, 2));
                        @endphp
                        <button type="button" class="lm-pay-qbtn" data-amount="{{ number_format($quarter, 2, '.', '') }}">
                            25%<small>{{ number_format($quarter, 0) }}</small>
                        </button>
                        <button type="button" class="lm-pay-qbtn" data-amount="{{ number_format($half, 2, '.', '') }}">
                            50%<small>{{ number_format($half, 0) }}</small>
                        </button>
                        <button type="button" class="lm-pay-qbtn" data-amount="{{ number_format($threeQ, 2, '.', '') }}">
                            75%<small>{{ number_format($threeQ, 0) }}</small>
                        </button>
                        <button type="button" class="lm-pay-qbtn lm-pay-qbtn-payoff" data-amount="{{ $payOffAmount }}">
                            <i class="fa fa-check-circle"></i> Pay Off<small>{{ number_format($loanBalance, 0) }}</small>
                        </button>
                    </div>
                </div>

                {{-- Amount Input --}}
                <div class="lm-pay-section">
                    <div class="lm-pay-amount-input-wrap">
                        <span class="lm-pay-currency-symbol">{{ $loanCurrency }}</span>
                        <input type="number" step="0.01" min="0.01" name="payment_lines[0][amount]"
                               id="lmPayAmountInput" class="lm-pay-amount-input"
                               value="{{ $defaultAmount }}" required>
                    </div>
                </div>

                <div class="lm-pay-section" id="lmPayDiscountSection" style="display:none;">
                    <div class="lm-pay-field">
                        <label>Pay off discount</label>
                        <input type="number" step="0.01" min="0" id="lmPayDiscountInput" class="lm-pay-field-input" value="0.00">
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="lm-pay-section">
                    <div class="lm-pay-section-title">Payment Method</div>
                    <div class="lm-pay-method-chips" id="lmPayMethodChips">
                        @foreach($paymentTypes as $key => $label)
                            <button type="button" class="lm-pay-chip {{ $key === $defaultPaymentMethod ? 'active' : '' }}"
                                    data-method="{{ $key }}">
                                @if($key === 'cash')
                                    <i class="fa fa-money"></i>
                                @elseif($key === 'bank_transfer')
                                    <i class="fa fa-university"></i>
                                @elseif($key === 'card')
                                    <i class="fa fa-credit-card"></i>
                                @else
                                    <i class="fa fa-wallet"></i>
                                @endif
                                {{ $label }}
                            </button>
                        @endforeach
                        <input type="hidden" name="payment_lines[0][method]" id="lmPayMethodInput" value="{{ $defaultPaymentMethod }}">
                    </div>
                </div>

                {{-- Reference (optional, collapsed) --}}
                <div class="lm-pay-section lm-pay-optional-fields">
                    <button type="button" class="lm-pay-toggle-optional" id="lmPayToggleOptional">
                        <i class="fa fa-chevron-down"></i> More details (optional)
                    </button>
                    <div class="lm-pay-optional-content" style="display: none;">
                        <div class="lm-pay-field">
                            <label>Reference #</label>
                            <input type="text" name="payment_lines[0][reference_number]" class="lm-pay-field-input" placeholder="Ref #">
                        </div>
                        <div class="lm-pay-field">
                            <label>Payment Doc</label>
                            <textarea name="payment_lines[0][payment_doc_text]" class="lm-pay-field-input lm-payment-doc-text" rows="3" placeholder="Write or paste payment document text"></textarea>
                            <input type="file" name="payment_lines[0][payment_docs][]" class="lm-pay-field-input lm-payment-doc-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" multiple>
                            <small class="lm-payment-doc-help">Write text, paste a screenshot/file, or upload multiple files.</small>
                        </div>
                        <div class="lm-pay-field">
                            <label>Note</label>
                            <input type="text" name="payment_lines[0][note]" class="lm-pay-field-input" placeholder="Payment note">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="lm-pay-footer">
                <div class="lm-pay-footer-total">
                    <span>Amount:</span>
                    <strong id="lmPayFooterTotal">{{ $defaultAmount }}</strong>
                    <span class="lm-pay-currency">{{ $loanCurrency }}</span>
                </div>
                <button type="submit" class="lm-pay-submit-btn" id="lmPaySubmitBtn">
                    <i class="fa fa-check-circle"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.lm-pay-modal { width: 100%; max-width: 480px; margin: auto; }
.lm-pay-header {
    background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 16px 20px;
    position: relative; border-radius: 16px 16px 0 0;
}
.lm-pay-close {
    position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,.2);
    border: 0; color: #fff; width: 32px; height: 32px; border-radius: 50%;
    font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.lm-pay-header-info { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.lm-pay-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.lm-pay-customer { font-weight: 700; font-size: 15px; }
.lm-pay-phone { font-size: 12px; opacity: .8; }
.lm-pay-balance-box {
    background: rgba(255,255,255,.15); border-radius: 10px; padding: 10px 14px;
    display: flex; align-items: center; gap: 8px; backdrop-filter: blur(4px);
}
.lm-pay-balance-label { font-size: 11px; opacity: .8; flex: 1; }
.lm-pay-balance-amount { font-size: 22px; font-weight: 800; }
.lm-pay-currency { font-size: 12px; opacity: .7; }

.lm-pay-body { padding: 16px; max-height: calc(100vh - 340px); overflow-y: auto; }
.lm-pay-section { margin-bottom: 16px; }
.lm-pay-section-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }

.lm-pay-quick-amounts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.lm-pay-qbtn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 10px 4px; border: 2px solid #e2e8f0; border-radius: 10px;
    background: #fff; cursor: pointer; font-size: 14px; font-weight: 700; color: #1e3a8a;
    transition: all .15s; min-height: 52px;
}
.lm-pay-qbtn small { font-size: 10px; font-weight: 500; color: #94a3b8; margin-top: 2px; }
.lm-pay-qbtn:hover, .lm-pay-qbtn.active { border-color: #2563eb; background: #eff6ff; color: #2563eb; }
.lm-pay-qbtn-payoff { border-color: #bbf7d0; color: #16a34a; }
.lm-pay-qbtn-payoff:hover, .lm-pay-qbtn-payoff.active { border-color: #16a34a; background: #f0fdf4; }

.lm-pay-amount-input-wrap {
    display: flex; align-items: center; background: #f8fafc; border: 2px solid #e2e8f0;
    border-radius: 12px; overflow: hidden; transition: border-color .15s;
}
.lm-pay-amount-input-wrap:focus-within { border-color: #2563eb; }
.lm-pay-currency-symbol {
    padding: 0 14px; font-size: 18px; font-weight: 700; color: #64748b;
}
.lm-pay-amount-input {
    flex: 1; border: 0; background: none; padding: 14px; font-size: 24px; font-weight: 800;
    color: #0f172a; outline: none; min-width: 0;
}

.lm-pay-method-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.lm-pay-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
    border: 2px solid #e2e8f0; border-radius: 20px; background: #fff;
    font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;
    transition: all .15s; min-height: 40px;
}
.lm-pay-chip i { font-size: 14px; }
.lm-pay-chip:hover { border-color: #93c5fd; }
.lm-pay-chip.active { border-color: #2563eb; background: #eff6ff; color: #2563eb; }

.lm-pay-toggle-optional {
    width: 100%; border: 0; background: none; color: #94a3b8; font-size: 12px;
    padding: 8px 0; cursor: pointer; text-align: center;
}
.lm-pay-toggle-optional i { transition: transform .2s; }
.lm-pay-toggle-optional.open i { transform: rotate(180deg); }
.lm-pay-optional-content { padding-top: 8px; }
.lm-pay-field { margin-bottom: 10px; }
.lm-pay-field label { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block; }
.lm-pay-field-input {
    width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 14px; outline: none;
}
.lm-pay-field-input:focus { border-color: #2563eb; }
.lm-payment-doc-help { display: block; margin-top: 4px; font-size: 11px; color: #94a3b8; }

.lm-pay-footer {
    padding: 12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
    border-top: 1px solid #f1f5f9; background: #fff;
}
.lm-pay-footer-total {
    display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px;
    font-size: 14px; color: #64748b;
}
.lm-pay-footer-total strong { font-size: 20px; color: #0f172a; }
.lm-pay-submit-btn {
    width: 100%; padding: 14px; border: 0; border-radius: 12px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff;
    font-size: 16px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    min-height: 52px; transition: all .15s; box-shadow: 0 4px 12px rgba(37,99,235,.3);
}
.lm-pay-submit-btn:hover { background: linear-gradient(135deg, #1d4ed8, #1e3a8a); transform: translateY(-1px); }
.lm-pay-submit-btn:active { transform: translateY(0); }
.lm-pay-submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

@media (max-width: 576px) {
    .lm-pay-modal { margin: 8px; }
    .lm-pay-quick-amounts { grid-template-columns: repeat(2, 1fr); }
    .lm-pay-amount-input { font-size: 28px; }
}
</style>

<script>
$(function() {
    var $form = $('#lmQuickPayForm');
    var loanBalance = {{ $loanBalance }};
    var payOffAmount = parseFloat('{{ $payOffAmount }}');

    function payOffDiscount() {
        return Math.min(parseFloat($form.find('#lmPayDiscountInput').val()) || 0, payOffAmount);
    }

    function applyQuickPayOffAmount() {
        var discount = payOffDiscount();
        $form.find('#lmPayDiscountInput').val(discount.toFixed(2));
        $form.find('#lmPayPayOffDiscountHidden').val(discount.toFixed(2));
        $form.find('#lmPayAmountInput').val(Math.max(payOffAmount - discount, 0.01).toFixed(2));
        updatePayDisplay();
    }

    // Quick amount buttons
    $form.on('click', '.lm-pay-qbtn', function() {
        var amount = parseFloat($(this).data('amount'));
        $form.find('.lm-pay-qbtn').removeClass('active');
        $(this).addClass('active');
        $form.find('#lmPayAmountInput').val(amount.toFixed(2));
        updatePayDisplay();

        if ($(this).hasClass('lm-pay-qbtn-payoff')) {
            $form.find('#lmPayPayOff').val('1');
            $form.find('#lmPayDiscountSection').show();
            applyQuickPayOffAmount();
        } else {
            $form.find('#lmPayPayOff').val('0');
            $form.find('#lmPayDiscountSection').hide();
            $form.find('#lmPayDiscountInput, #lmPayPayOffDiscountHidden').val('0.00');
        }
    });

    // Amount input change
    $form.on('input', '#lmPayAmountInput', function() {
        $form.find('.lm-pay-qbtn').removeClass('active');
        $form.find('#lmPayPayOff').val('0');
        $form.find('#lmPayDiscountSection').hide();
        $form.find('#lmPayDiscountInput, #lmPayPayOffDiscountHidden').val('0.00');
        updatePayDisplay();
    });

    $form.on('input change', '#lmPayDiscountInput', function() {
        if ($form.find('#lmPayPayOff').val() === '1') {
            applyQuickPayOffAmount();
        }
    });

    // Method chips
    $form.on('click', '.lm-pay-chip', function() {
        $form.find('.lm-pay-chip').removeClass('active');
        $(this).addClass('active');
        $form.find('#lmPayMethodInput').val($(this).data('method'));
    });

    // Toggle optional
    $form.on('click', '#lmPayToggleOptional', function() {
        $(this).toggleClass('open');
        $form.find('.lm-pay-optional-content').slideToggle(200);
    });

    function updatePayDisplay() {
        var amount = parseFloat($form.find('#lmPayAmountInput').val()) || 0;
        $form.find('#lmPayFooterTotal').text(amount.toFixed(2));
    }

    function updatePaymentDocHelp($input) {
        var files = $input[0].files || [];
        var text = files.length ? files.length + ' file(s) selected' : 'Write text, paste a screenshot/file, or upload multiple files.';
        $input.closest('.lm-pay-field').find('.lm-payment-doc-help').text(text);
    }

    function appendFilesToInput(input, files) {
        if (!input || !files || !files.length || typeof DataTransfer === 'undefined') {
            return false;
        }

        var transfer = new DataTransfer();
        Array.prototype.forEach.call(input.files || [], function(file) {
            transfer.items.add(file);
        });
        Array.prototype.forEach.call(files, function(file) {
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
        Array.prototype.forEach.call(clipboard.items || [], function(item) {
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

    $form.on('change', '.lm-payment-doc-input', function() {
        updatePaymentDocHelp($(this));
    });

    $form.on('paste', function(event) {
        var files = clipboardFiles(event);
        var text = clipboardText(event);

        if (files.length) {
            if (appendFilesToInput($form.find('.lm-payment-doc-input')[0], files)) {
                event.preventDefault();
                if (window.toastr) {
                    toastr.success(files.length + ' pasted file(s) added to Payment Doc');
                }
            }
            return;
        }

        if (text && !$(event.target).is('input, textarea')) {
            var $text = $form.find('.lm-payment-doc-text');
            $text.val($.trim(($text.val() || '') + "\n" + text));
            event.preventDefault();
            if (window.toastr) {
                toastr.success('Pasted text added to Payment Doc');
            }
        }
    });

    // AJAX submit
    $form.off('submit.lmQuickPay').on('submit.lmQuickPay', function(e) {
        e.preventDefault();
        var $btn = $form.find('#lmPaySubmitBtn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: new FormData($form[0]),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (window.toastr) toastr.success(res.message || 'Payment saved!');
                $('#quickPayModal').modal('hide');
                var $loanSections = $('.view_modal').find('#loanShowSections');
                if ($loanSections.length && $loanSections.data('url')) {
                    window.jQuery.ajax({
                        url: $loanSections.data('url'),
                        dataType: 'html',
                        success: function (html) { $loanSections.html(html); }
                    });
                    return;
                }
                if (res.data && res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                } else {
                    location.reload();
                }
            },
            error: function(xhr) {
                var msg = 'Payment failed';
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    msg = xhr.responseJSON.errors[firstKey][0];
                } else if (xhr.responseJSON?.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) toastr.error(msg);
                else alert(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Confirm Payment');
            }
        });
    });

    updatePayDisplay();
});
</script>
