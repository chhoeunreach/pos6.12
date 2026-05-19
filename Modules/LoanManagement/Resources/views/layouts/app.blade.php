@extends('layouts.app')

@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    if (! function_exists('loan_user_can')) {
        function loan_user_can($permission) {
            return LoanMenuHelper::loanUserCan((string) $permission);
        }
    }

    $moduleCssPath = base_path('Modules/LoanManagement/Resources/assets/css/loan-management.css');
    $moduleJsPath = base_path('Modules/LoanManagement/Resources/assets/js/loan-management.js');
    $loanBadgeCounts = LoanMenuHelper::badgeCounts();
@endphp

@section('title', trim($__env->yieldContent('title')) !== '' ? $__env->yieldContent('title') . ' - LoanManagement' : 'LoanManagement')

@section('css')
    @parent
    @if (file_exists($moduleCssPath))
        <style>{!! file_get_contents($moduleCssPath) !!}</style>
    @endif
    @yield('loan_css')
@endsection

@section('content')
<div class="lm-app" id="loanManagementApp">
    @include('loanmanagement::layouts.sidebar', ['loanBadgeCounts' => $loanBadgeCounts])

    <div class="lm-main" id="loanManagementMain">
        @include('loanmanagement::layouts.header')

        <main class="lm-content">
            @include('loanmanagement::layouts.breadcrumb')
            <div class="container-fluid lm-workspace">
                @yield('content_body')
            </div>
        </main>

        @if(auth()->user()?->can('superadmin') || auth()->user()?->can('sell.create'))
            @include('loanmanagement::layouts.partials.sell_pos_modal')
        @endif

        @if(loan_user_can('loan_management.create_from_sell|loan_management.loans.create|loan_management.create'))
            @include('loanmanagement::layouts.partials.auto_installment_modal')
        @endif

        @include('loanmanagement::layouts.footer')
    </div>
</div>
@endsection

@section('javascript')
    @parent
    @if (file_exists($moduleJsPath))
        <script>{!! file_get_contents($moduleJsPath) !!}</script>
    @endif
    @if(auth()->user()?->can('superadmin') || auth()->user()?->can('sell.create'))
        <script>
            (function($){
                var loanPosRoutes = {
                    cloneBase: "{{ url('/loan-management/loans/sales') }}",
                    previewSchedule: "{{ route('loan-management.loans.preview-schedule') }}",
                    loanViewBase: "{{ url('/loan-management/loans') }}"
                };
                var lastAutoInstallmentTransactionId = null;

                function escLoanModal(value) {
                    return $('<div>').text(value == null ? '' : value).html();
                }

                function moneyLoanModal(value) {
                    var number = parseFloat(value || 0);
                    return Number.isFinite(number) ? number.toFixed(2) : '0.00';
                }

                function bindAutoInstallmentForm(container) {
                    var form = container.find('#createLoanFromSellForm');
                    if (!form.length) {
                        return;
                    }

                    function parseNum(v) {
                        var n = parseFloat(String(v || '').replace(/,/g, '').trim());
                        return Number.isFinite(n) ? n : 0;
                    }

                    function updatePaymentSummary() {
                        var totalAmount = parseNum(form.find('#loan_total_amount_value').val() || form.find('#loan_total_amount_display').val());
                        var paid = parseNum(form.find('#payment_amount_input').val());
                        var due = Math.max(0, totalAmount - paid);
                        form.find('#down_payment_hidden').val(paid.toFixed(2));
                        form.find('#loan_total_paid_display').val(paid.toFixed(2));
                        form.find('#loan_total_due_display').val(due.toFixed(2));
                    }

                    form.find('#principal_amount_input, #payment_amount_input, input[name="interest_rate"], input[name="duration_months"]')
                        .off('input.loanAutoInstallment change.loanAutoInstallment')
                        .on('input.loanAutoInstallment change.loanAutoInstallment', updatePaymentSummary);
                    updatePaymentSummary();

                    container.find('#btnPreviewSchedule').off('click.loanAutoInstallment').on('click.loanAutoInstallment', function(){
                        $.post(loanPosRoutes.previewSchedule, form.serialize(), function(res){
                            var rows = res.data || [];
                            var tb = form.find('#schedulePreviewTable tbody').first();
                            var table = tb.closest('table');
                            var totalPrincipal = 0, totalInterest = 0, totalAmount = 0, totalBalance = 0;
                            tb.empty();
                            rows.forEach(function(r){
                                totalPrincipal += Number(r.principal || 0);
                                totalInterest += Number(r.interest || 0);
                                totalAmount += Number(r.total || 0);
                                totalBalance += Number(r.balance || 0);
                                tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td>'+moneyLoanModal(r.principal)+'</td><td>'+moneyLoanModal(r.interest)+'</td><td>'+moneyLoanModal(r.total)+'</td><td>'+moneyLoanModal(r.balance)+'</td></tr>');
                            });
                            table.find('tfoot tr th').eq(1).text(totalPrincipal.toFixed(2));
                            table.find('tfoot tr th').eq(2).text(totalInterest.toFixed(2));
                            table.find('tfoot tr th').eq(3).text(totalAmount.toFixed(2));
                            table.find('tfoot tr th').eq(4).text(totalBalance.toFixed(2));
                        }).fail(function(xhr){
                            alert(xhr.responseJSON?.message || 'Failed to preview schedule');
                        });
                    });

                    container.find('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan').off('click.loanAutoInstallment').on('click.loanAutoInstallment', function(){
                        form.find('input[name="action_type"]').val($(this).data('action'));
                        form.trigger('submit');
                    });

                    form.off('submit.loanAutoInstallment').on('submit.loanAutoInstallment', function(e){
                        e.preventDefault();
                        var buttons = container.find('#btnSaveDraft, #btnCreateLoan, #btnCreateApproveLoan');
                        buttons.prop('disabled', true);
                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            success: function(res){
                                if (window.toastr) {
                                    toastr.success(res.message || 'Installment loan created successfully');
                                } else {
                                    alert(res.message || 'Installment loan created successfully');
                                }
                                if(res?.data?.loan_id){
                                    window.location = loanPosRoutes.loanViewBase + '/' + res.data.loan_id + '/view';
                                }
                            },
                            error: function(xhr){
                                if(xhr.status === 422 && xhr.responseJSON?.errors){
                                    var errors = xhr.responseJSON.errors;
                                    var firstKey = Object.keys(errors)[0];
                                    alert(errors[firstKey][0] || xhr.responseJSON?.message || 'Validation failed');
                                } else if (xhr.responseJSON?.data?.loan_url) {
                                    window.location = xhr.responseJSON.data.loan_url;
                                } else {
                                    alert(xhr.responseJSON?.message || 'Failed to create loan');
                                }
                            },
                            complete: function(){ buttons.prop('disabled', false); }
                        });
                    });
                }

                function openAutoInstallment(transactionId) {
                    if (!transactionId || !$('#loanAutoInstallmentModal').length) {
                        return;
                    }
                    if (String(lastAutoInstallmentTransactionId || '') === String(transactionId)) {
                        return;
                    }
                    lastAutoInstallmentTransactionId = transactionId;

                    var body = $('#loanAutoInstallmentModalBody');
                    body.html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading selected sale...</div>');
                    $('#loanAutoInstallmentModal').modal('show');

                    $.get(loanPosRoutes.cloneBase + '/' + encodeURIComponent(transactionId) + '/clone-data', function(res){
                        if (!res.success) {
                            if (res.data && res.data.loan_url) {
                                window.location = res.data.loan_url;
                                return;
                            }
                            body.html('<div class="alert alert-warning">'+escLoanModal(res.message || 'Unable to add this sale to installment.')+'</div>');
                            return;
                        }

                        body.html(res.data.form_html);
                        bindAutoInstallmentForm(body);
                    }).fail(function(xhr){
                        var data = xhr.responseJSON?.data || {};
                        if (data.loan_url) {
                            window.location = data.loan_url;
                            return;
                        }
                        body.html('<div class="alert alert-danger">'+escLoanModal(xhr.responseJSON?.message || 'Failed to load sale data')+'</div>');
                    });
                }

                function printReceiptInLoanWindow(receipt) {
                    if (!receipt || !receipt.is_enabled || !receipt.html_content) {
                        return false;
                    }

                    var previousTitle = document.title;
                    if (receipt.print_title) {
                        document.title = receipt.print_title;
                    }

                    $('#receipt_section').html(receipt.html_content);
                    if (typeof __currency_convert_recursively === 'function') {
                        __currency_convert_recursively($('#receipt_section'));
                    }

                    if (typeof __print_receipt === 'function') {
                        __print_receipt('receipt_section');
                    } else {
                        window.print();
                    }

                    window.setTimeout(function(){
                        document.title = previousTitle;
                    }, 1200);

                    return true;
                }

                function installPosPrintBridge() {
                    var frame = document.getElementById('loanSellPosFrame');
                    if (!frame || !frame.contentWindow) {
                        return;
                    }

                    var attempts = 0;
                    var timer = window.setInterval(function(){
                        attempts++;
                        try {
                            var child = frame.contentWindow;
                            if (!child || typeof child.pos_print !== 'function') {
                                if (attempts > 40) {
                                    window.clearInterval(timer);
                                }
                                return;
                            }

                            if (child.__loanSellPosPrintBridgeInstalled) {
                                window.clearInterval(timer);
                                return;
                            }

                            var originalPrint = child.pos_print;
                            child.__loanSellPosPrintBridgeInstalled = true;
                            child.pos_print = function(receipt) {
                                try {
                                    $('#loanSellPosModal').modal('hide');
                                    if (printReceiptInLoanWindow(receipt)) {
                                        $(document).trigger('loan:sell-pos-saved', [receipt, receipt ? receipt.transaction_id : null]);
                                        return;
                                    }
                                } catch (e) {}

                                return originalPrint.call(child, receipt);
                            };
                            window.clearInterval(timer);
                        } catch (e) {
                            window.clearInterval(timer);
                        }
                    }, 250);
                }

                $(document).on('click', '#loanHeaderOpenSellPos', function(){
                    var frame = $('#loanSellPosFrame');
                    var posUrl = $(this).data('pos-url') || frame.data('pos-url');

                    if (frame.attr('src') !== posUrl) {
                        frame.attr('src', posUrl);
                    }

                    $('#loanSellPosModal').modal('show');
                });

                $('#loanSellPosFrame').on('load', installPosPrintBridge);
                $('#loanSellPosModal').on('shown.bs.modal', installPosPrintBridge);

                $(document).on('loan:sell-pos-saved', function(event, receipt, transactionId){
                    openAutoInstallment(transactionId || (receipt ? receipt.transaction_id : null));
                });

                window.addEventListener('message', function(event){
                    if (event.origin !== window.location.origin || !event.data || event.data.type !== 'loan-pos-sale-saved') {
                        return;
                    }

                    $('#loanSellPosModal').modal('hide');
                    $('#addSellModal').modal('hide');
                    $(document).trigger('loan:sell-pos-saved', [event.data.receipt || null, event.data.transaction_id || null]);
                });
            })(jQuery);
        </script>
    @endif
    @yield('loan_js')
@endsection
