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
                    loanViewBase: "{{ url('/loan-management/loans') }}",
                    loanPrintModalBase: "{{ url('/loan-management/loans') }}"
                };
                var lastAutoInstallmentTransactionId = null;
                var loanPosPrintFinalizeTimer = null;
                function escLoanModal(value) {
                    return $('<div>').text(value == null ? '' : value).html();
                }

                function moneyLoanModal(value) {
                    var number = parseFloat(value || 0);
                    return Number.isFinite(number) ? number.toFixed(2) : '0.00';
                }

                function openLoanPrintModal(loanId, options) {
                    if (!loanId || !$('.view_modal').length) {
                        return;
                    }

                    var settings = $.extend({ autostart: false }, options || {});
                    window.__loanPrintLaunchState = window.__loanPrintLaunchState || { loanId: null, ts: 0 };
                    var now = Date.now();
                    if (settings.autostart && String(window.__loanPrintLaunchState.loanId || '') === String(loanId) && (now - window.__loanPrintLaunchState.ts) < 2500) {
                        return;
                    }
                    window.__loanPrintLaunchState = {
                        loanId: loanId,
                        ts: now
                    };

                    if (settings.autostart) {
                        var iframeId = 'loan_direct_print_frame_' + String(loanId).replace(/[^a-zA-Z0-9_-]/g, '') + '_' + now;
                        var iframe = document.createElement('iframe');
                        iframe.id = iframeId;
                        iframe.src = loanPosRoutes.loanPrintModalBase + '/' + encodeURIComponent(loanId) + '/print?auto_print=1&_lm_direct_print=1&_lm_reload=' + now;
                        iframe.style.position = 'fixed';
                        iframe.style.width = '1px';
                        iframe.style.height = '1px';
                        iframe.style.opacity = '0';
                        iframe.style.pointerEvents = 'none';
                        iframe.style.border = '0';
                        iframe.style.right = '0';
                        iframe.style.bottom = '0';
                        document.body.appendChild(iframe);

                        window.setTimeout(function () {
                            var mountedFrame = document.getElementById(iframeId);
                            if (mountedFrame && mountedFrame.parentNode) {
                                mountedFrame.parentNode.removeChild(mountedFrame);
                            }
                        }, 60000);
                        return;
                    }

                    var modalUrl = loanPosRoutes.loanPrintModalBase + '/' + encodeURIComponent(loanId) + '/print-modal';

                    if (settings.autostart) {
                        modalUrl += '?autostart=1';
                    }

                    $.ajax({
                        url: modalUrl,
                        dataType: 'html',
                        success: function(result) {
                            $('.view_modal')
                                .html(result)
                                .modal('show');
                        }
                    });
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
                                    $('#loanAutoInstallmentModal').modal('hide');
                                    openLoanPrintModal(res.data.loan_id, {autostart: true});
                                    return;
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

                function finalizeLoanPosSaleSaved(receipt, transactionId) {
                    $('#loanSellPosModal').modal('hide');
                    $('#addSellModal').modal('hide');
                    $(document).trigger('loan:sell-pos-saved', [receipt || null, transactionId || (receipt ? receipt.transaction_id : null) || null]);
                }

                function waitForLoanPrintToFinish(printWindow, onComplete) {
                    var completed = false;

                    function cleanup() {
                        if (printWindow) {
                            printWindow.removeEventListener('afterprint', afterPrintHandler);
                            printWindow.removeEventListener('focus', focusHandler);
                        }
                        window.removeEventListener('focus', parentFocusHandler);
                        if (loanPosPrintFinalizeTimer) {
                            window.clearTimeout(loanPosPrintFinalizeTimer);
                            loanPosPrintFinalizeTimer = null;
                        }
                    }

                    function done() {
                        if (completed) {
                            return;
                        }

                        completed = true;
                        cleanup();
                        onComplete();
                    }

                    function afterPrintHandler() {
                        done();
                    }

                    function focusHandler() {
                        window.setTimeout(done, 150);
                    }

                    function parentFocusHandler() {
                        window.setTimeout(done, 150);
                    }

                    cleanup();
                    if (printWindow) {
                        printWindow.addEventListener('afterprint', afterPrintHandler);
                        printWindow.addEventListener('focus', focusHandler);
                    }
                    window.addEventListener('focus', parentFocusHandler);
                    loanPosPrintFinalizeTimer = window.setTimeout(done, 30000);
                }

                function installPosPrintBridge(frameId) {
                    var frame = document.getElementById(frameId);
                    if (!frame || !frame.contentWindow) {
                        return;
                    }

                    var attempts = 0;
                    var timer = window.setInterval(function(){
                        attempts++;
                        try {
                            var child = frame.contentWindow;
                            if (!child || typeof child.pos_print !== 'function' || typeof child.notify_loan_module_pos_saved !== 'function') {
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
                            var originalNotify = child.notify_loan_module_pos_saved;
                            child.__loanSellPosPrintBridgeInstalled = true;
                            child.__loanSellPosPendingPayload = null;
                            child.__loanSellPosFinalizePendingPayload = function() {
                                var payload = child.__loanSellPosPendingPayload;
                                child.__loanSellPosPendingPayload = null;

                                if (!payload) {
                                    return;
                                }

                                finalizeLoanPosSaleSaved(payload.receipt || null, payload.transaction_id || null);
                            };
                            child.notify_loan_module_pos_saved = function(result) {
                                var payload = {
                                    type: 'loan-pos-sale-saved',
                                    transaction_id: result.transaction_id || (result.receipt && result.receipt.transaction_id) || null,
                                    invoice_no: result.invoice_no || (result.receipt && result.receipt.invoice_no) || null,
                                    receipt: result.receipt || null
                                };
                                var receipt = payload.receipt || null;

                                if (receipt && receipt.is_enabled && receipt.print_type !== 'printer' && receipt.html_content) {
                                    child.__loanSellPosPendingPayload = payload;
                                    return;
                                }

                                child.__loanSellPosPendingPayload = null;
                                originalNotify.call(child, result);
                            };
                            child.pos_print = function(receipt) {
                                var payload = child.__loanSellPosPendingPayload;

                                if (receipt && receipt.print_type !== 'printer' && receipt.html_content) {
                                    waitForLoanPrintToFinish(child, function(){
                                        child.__loanSellPosFinalizePendingPayload();
                                    });
                                }

                                var response = originalPrint.call(child, receipt);

                                if (!receipt || receipt.print_type === 'printer' || !receipt.html_content) {
                                    child.__loanSellPosFinalizePendingPayload();
                                }

                                return response;
                            };
                            window.clearInterval(timer);
                        } catch (e) {
                            window.clearInterval(timer);
                        }
                    }, 250);
                }

                function buildLoanPosModalUrl(baseUrl) {
                    if (!baseUrl) {
                        return '';
                    }

                    var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
                    return baseUrl + separator + '_lm_pos_modal=1&_lm_reload=' + Date.now();
                }

                function openLoanSellPosModal(posUrl) {
                    var frame = $('#loanSellPosFrame');
                    if (!frame.length || !$('#loanSellPosModal').length) {
                        return false;
                    }

                    var targetUrl = posUrl || frame.data('pos-url');
                    if (!targetUrl) {
                        return false;
                    }

                    frame.attr('src', buildLoanPosModalUrl(targetUrl));
                    $('#loanSellPosModal').modal('show');
                    return true;
                }

                $(document).on('click', '#loanHeaderOpenSellPos', function(){
                    openLoanSellPosModal($(this).data('pos-url'));
                });

                $('#loanSellPosFrame').on('load', function(){
                    installPosPrintBridge('loanSellPosFrame');
                });
                $('#ultimatePosSellFrame').on('load', function(){
                    installPosPrintBridge('ultimatePosSellFrame');
                });
                $('#loanSellPosModal').on('shown.bs.modal', function(){
                    installPosPrintBridge('loanSellPosFrame');
                });
                $('#addSellModal').on('shown.bs.modal', function(){
                    installPosPrintBridge('ultimatePosSellFrame');
                });

                $(document).on('loan:sell-pos-saved', function(event, receipt, transactionId){
                    openAutoInstallment(transactionId || (receipt ? receipt.transaction_id : null));
                });

                window.loanManagementOpenSellPos = openLoanSellPosModal;
                window.loanManagementOpenPrintModal = openLoanPrintModal;

                window.addEventListener('message', function(event){
                    if (event.origin !== window.location.origin || !event.data || event.data.type !== 'loan-pos-sale-saved') {
                        return;
                    }

                    var receipt = event.data.receipt || null;
                    var transactionId = event.data.transaction_id || (receipt ? receipt.transaction_id : null) || null;

                    finalizeLoanPosSaleSaved(receipt, transactionId);
                });
            })(jQuery);
        </script>
    @endif
    @yield('loan_js')
@endsection
