@inject('request', 'Illuminate\Http\Request')

@php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    $whitelist = ['127.0.0.1', '::1'];
    $moduleCssPath = base_path('Modules/LoanManagement/Resources/assets/css/loan-management.css');
    $moduleJsPath = base_path('Modules/LoanManagement/Resources/assets/js/loan-management.js');
    $loanBadgeCounts = LoanMenuHelper::badgeCounts();
    $pageTitle = trim($__env->yieldContent('title')) !== '' ? $__env->yieldContent('title').' - LoanManagement' : 'LoanManagement';
    $businessName = Session::get('business.name');
    $isLoanEmbeddedModal = request()->boolean('_lm_modal');
@endphp

<!DOCTYPE html>
<html class="tw-bg-white tw-scroll-smooth" lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}@if(!empty($businessName)) - {{ $businessName }}@endif</title>

    @include('layouts.partials.css')
    @include('layouts.partials.extracss')

    @if (file_exists($moduleCssPath))
        <style>{!! file_get_contents($moduleCssPath) !!}</style>
    @endif
    @yield('loan_css')
</head>
<body class="hold-transition skin-blue-light sidebar-mini loan-management-page {{ $isLoanEmbeddedModal ? 'loan-management-embedded-modal' : '' }} tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100">
    @if (in_array($request->ip(), $whitelist, true))
        <input type="hidden" id="__is_localhost" value="true">
    @endif

    <input type="hidden" id="__code" value="{{ session('currency.code') }}">
    <input type="hidden" id="__symbol" value="{{ session('currency.symbol') }}">
    <input type="hidden" id="__thousand" value="{{ session('currency.thousand_separator') }}">
    <input type="hidden" id="__decimal" value="{{ session('currency.decimal_separator') }}">
    <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') }}">
    <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
    <input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">

    @can('view_export_buttons')
        <input type="hidden" id="view_export_buttons">
    @endcan
    @if (isMobile())
        <input type="hidden" id="__is_mobile">
    @endif
    @if (session('status'))
        @php
            $loanSessionStatus = session('status');
            $loanSessionStatusSuccess = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'success', 1) : 1;
            $loanSessionStatusMessage = is_array($loanSessionStatus) ? data_get($loanSessionStatus, 'msg', 'Saved successfully.') : $loanSessionStatus;
        @endphp
        <input type="hidden" id="status_span" data-status="{{ $loanSessionStatusSuccess }}"
            data-msg="{{ $loanSessionStatusMessage }}">
    @endif
    @if (config('constants.iraqi_selling_price_adjustment'))
        <input type="hidden" id="iraqi_selling_price_adjustment">
    @endif

    <div class="tw-flex thetop">
        <main class="tw-flex tw-flex-col tw-flex-1 tw-h-full tw-min-w-0 tw-bg-gray-100">
            <div id="main_app_header"></div>
            <div id="app"></div>
            <div class="tw-flex-1 tw-overflow-y-auto tw-h-screen" id="scrollable-container">
                <div class="lm-app" id="loanManagementApp">
                    @unless($isLoanEmbeddedModal)
                        @include('loanmanagement::layouts.sidebar', ['loanBadgeCounts' => $loanBadgeCounts])
                    @endunless

                    <div class="lm-main" id="loanManagementMain">
                        @unless($isLoanEmbeddedModal)
                            @include('loanmanagement::layouts.header')
                        @endunless

                        <main class="lm-content">
                            @unless($isLoanEmbeddedModal)
                                @include('loanmanagement::layouts.breadcrumb')
                            @endunless
                            <div class="container-fluid lm-workspace">
                                @yield('content_body')
                            </div>
                        </main>

                        @if(!$isLoanEmbeddedModal && \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
                            @include('loanmanagement::layouts.partials.auto_installment_modal')
                        @endif

                        @if(!$isLoanEmbeddedModal && (auth()->user()?->can('superadmin') || auth()->user()?->can('sell.create')))
                            @include('loanmanagement::layouts.partials.sell_pos_modal')
                        @endif

                        @unless($isLoanEmbeddedModal)
                            @include('loanmanagement::layouts.footer')

                            @include('loanmanagement::layouts.partials.mobile_nav')
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="invoice print_section" id="receipt_section"></section>
    <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    <div class="modal fade no-print" id="standaloneLoanModal" tabindex="-1" role="dialog" aria-labelledby="standaloneLoanModalLabel">
        <div class="modal-dialog modal-xl" role="document" style="width: 96%; max-width: 1200px; margin: 10px auto;">
            <div class="modal-content">
                <div class="modal-body" id="standaloneLoanModalBody" style="padding: 0;">
                    <div class="text-center" style="padding: 40px 16px;">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i>
                        <p style="margin-top: 12px; color: #64748b;">Loading form...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade no-print" id="quickPayModal" tabindex="-1" role="dialog" aria-labelledby="quickPayModalLabel">
        <div class="modal-dialog" role="document" style="max-width: 480px; margin: auto;">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.2);">
                <div class="modal-body" id="quickPayModalBody" style="padding: 0;">
                    <div class="text-center" style="padding: 40px 16px;">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i>
                        <p style="margin-top: 12px; color: #64748b;">Loading payment form...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay tw-hidden"></div>

    <audio id="success-audio">
        <source src="{{ asset('/audio/success.ogg?v=' . $asset_v) }}" type="audio/ogg">
        <source src="{{ asset('/audio/success.mp3?v=' . $asset_v) }}" type="audio/mpeg">
    </audio>
    <audio id="error-audio">
        <source src="{{ asset('/audio/error.ogg?v=' . $asset_v) }}" type="audio/ogg">
        <source src="{{ asset('/audio/error.mp3?v=' . $asset_v) }}" type="audio/mpeg">
    </audio>
    <audio id="warning-audio">
        <source src="{{ asset('/audio/warning.ogg?v=' . $asset_v) }}" type="audio/ogg">
        <source src="{{ asset('/audio/warning.mp3?v=' . $asset_v) }}" type="audio/mpeg">
    </audio>

    @if (!empty($__additional_html))
        {!! $__additional_html !!}
    @endif

    @include('layouts.partials.javascripts')
    @include('layouts.module-assets')
    @if (file_exists($moduleJsPath))
        <script>{!! file_get_contents($moduleJsPath) !!}</script>
    @endif
    @include('loanmanagement::layouts.partials.language_runtime')

    @if (!empty($__additional_views) && is_array($__additional_views))
        @foreach ($__additional_views as $additional_view)
            @includeIf($additional_view)
        @endforeach
    @endif

        <script>
            (function($){
                var loanPosRoutes = {
                    previewSchedule: "{{ route('loan-management.loans.preview-schedule') }}",
                    cloneSellBase: "{{ url('/loan-management/loans/sell') }}",
                    loanViewBase: "{{ url('/loan-management/loans') }}",
                    loanPrintModalBase: "{{ url('/loan-management/loans') }}"
                };
                var lastAutoInstallmentTransactionId = null;

                function escLoanModal(value) {
                    return $('<div>').text(value == null ? '' : value).html();
                }

                function openLoanDetailFrameModal(url, title) {
                    if (!url || !$('.view_modal').length) {
                        return;
                    }

                    var modalUrl = url;
                    if (modalUrl.indexOf('_lm_modal=1') === -1) {
                        modalUrl += (modalUrl.indexOf('?') === -1 ? '?' : '&') + '_lm_modal=1';
                    }

                    var html = '' +
                        '<div class="modal-dialog modal-xl lm-dashboard-iframe-modal" role="document">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                        '<span aria-hidden="true">&times;</span>' +
                                    '</button>' +
                                    '<h4 class="modal-title">' + escLoanModal(title || 'Loan Detail') + '</h4>' +
                                '</div>' +
                                '<div class="modal-body" style="padding:0;height:85vh;">' +
                                    '<iframe src="' + escLoanModal(modalUrl) + '" style="width:100%;height:100%;border:0;" title="' + escLoanModal(title || 'Loan Detail') + '"></iframe>' +
                                '</div>' +
                            '</div>' +
                        '</div>';

                    $('.view_modal').html(html).modal('show');
                }

                function showExistingLoanWarning(body, message, loanUrl) {
                    var actions = '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>';
                    if (loanUrl) {
                        actions = '<button type="button" class="btn btn-primary js-open-existing-loan-detail" data-loan-url="' + escLoanModal(loanUrl) + '">' +
                            '<i class="fa fa-eye"></i> View Loan' +
                        '</button> ' + actions;
                    }

                    body.html(
                        '<div class="alert alert-warning">' +
                            '<strong>' + escLoanModal(message || 'This sale already has installment loan.') + '</strong>' +
                            '<div class="m-t-15">' + actions + '</div>' +
                        '</div>'
                    );
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

                    $.ajax({
                        url: loanPosRoutes.loanPrintModalBase + '/' + encodeURIComponent(loanId) + '/print-modal',
                        dataType: 'html',
                        success: function(result) {
                            $('.view_modal').html(result).modal('show');
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

                    form.find('#principal_amount_input, #payment_amount_input, [name="interest_rate"], input[name="duration_months"]')
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
                                    showExistingLoanWarning(container, xhr.responseJSON?.message || 'This sale already has installment loan.', xhr.responseJSON.data.loan_url);
                                } else {
                                    alert(xhr.responseJSON?.message || 'Failed to create installment loan');
                                }
                            },
                            complete: function(){
                                buttons.prop('disabled', false);
                            }
                        });
                    });
                }

                function directLoanManagementPrintUrl(loanId) {
                    return loanPosRoutes.loanPrintModalBase + '/' + encodeURIComponent(loanId) + '/print?auto_print=1&_lm_direct_print=1&_lm_reload=' + Date.now();
                }

                function openAutoInstallment(transactionId) {
                    if (!transactionId || transactionId === lastAutoInstallmentTransactionId) {
                        return;
                    }

                    lastAutoInstallmentTransactionId = transactionId;

                    var container = $('#loanAutoInstallmentModal');
                    var modalBody = container.find('#loanAutoInstallmentModalBody');

                    modalBody.html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading selected sale...</div>');
                    container.modal('show');

                    $.ajax({
                        url: loanPosRoutes.cloneSellBase + '/' + encodeURIComponent(transactionId) + '/clone',
                        dataType: 'json',
                        success: function(result){
                            var formHtml = result && result.data ? result.data.form_html : '';

                            if (!formHtml) {
                                modalBody.html('<div class="alert alert-warning" style="margin-bottom:0;">Unable to load installment form.</div>');
                                lastAutoInstallmentTransactionId = null;
                                return;
                            }

                            modalBody.html(formHtml);
                            bindAutoInstallmentForm(container);
                        },
                        error: function(xhr){
                            lastAutoInstallmentTransactionId = null;
                            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.loan_url) {
                                showExistingLoanWarning(modalBody, xhr.responseJSON.message || 'This sale already has installment loan.', xhr.responseJSON.data.loan_url);
                                return;
                            }

                            modalBody.html('<div class="alert alert-danger" style="margin-bottom:0;">' + escLoanModal(xhr.responseJSON?.message || 'Unable to open installment loan form') + '</div>');
                        }
                    });
                }

                function waitForLoanPrintToFinish(frameWindow, callback) {
                    var attempts = 0;
                    var timer = window.setInterval(function(){
                        attempts++;
                        try {
                            if (frameWindow.document.readyState !== 'complete') {
                                if (attempts > 40) {
                                    window.clearInterval(timer);
                                }
                                return;
                            }
                        } catch (e) {
                            if (attempts > 40) {
                                window.clearInterval(timer);
                            }
                            return;
                        }

                        window.clearInterval(timer);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    }, 250);
                }

                function installPosPrintBridge(frameId) {
                    var frame = document.getElementById(frameId);
                    if (!frame) {
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

                $(document).on('click', '#loanHeaderOpenSellPos', function(event){
                    if (openLoanSellPosModal($(this).data('pos-url'))) {
                        event.preventDefault();
                    }
                });

                $('#loanSellPosFrame').on('load', function(){
                    installPosPrintBridge('loanSellPosFrame');
                });

                $('#loanSellPosModal').on('shown.bs.modal', function(){
                    installPosPrintBridge('loanSellPosFrame');
                });

                $(document).on('click', '.js-open-existing-loan-detail', function(event){
                    event.preventDefault();
                    openLoanDetailFrameModal($(this).data('loan-url'), 'Loan Detail');
                });

                window.loanManagementOpenAutoInstallment = openAutoInstallment;
                window.loanManagementDirectPrintUrl = directLoanManagementPrintUrl;
                window.loanManagementOpenSellPos = openLoanSellPosModal;
                window.loanManagementOpenPrintModal = openLoanPrintModal;
            })(jQuery);
        </script>
    <script>
        (function ($) {
            $(document).on('click', '.lm-btn-modal', function (e) {
                e.preventDefault();

                var $trigger = $(this);
                var container = $trigger.data('container') || '.view_modal';
                var url = $trigger.data('href') || $trigger.attr('href');

                if (!url || !$(container).length) {
                    return;
                }

                $.ajax({
                    url: url,
                    dataType: 'html',
                    beforeSend: function () {
                        $(container).html(
                            '<div class="modal-dialog modal-lg" role="document">' +
                                '<div class="modal-content">' +
                                    '<div class="modal-body text-center" style="padding:32px 16px;">' +
                                        '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
                                    '</div>' +
                                '</div>' +
                            '</div>'
                        ).modal('show');
                    },
                    success: function (result) {
                        $(container).html(result).modal('show');
                    },
                    error: function (xhr) {
                        var message = 'Unable to load this window.';

                        if (xhr && xhr.responseText) {
                            message = xhr.responseText;
                        }

                        $(container).html(
                            '<div class="modal-dialog modal-lg" role="document">' +
                                '<div class="modal-content">' +
                                    '<div class="modal-header">' +
                                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                                        '<h4 class="modal-title">Load Error</h4>' +
                                    '</div>' +
                                    '<div class="modal-body"><div class="alert alert-danger" style="margin-bottom:0;">' + $('<div>').text(message).html() + '</div></div>' +
                                '</div>' +
                            '</div>'
                        ).modal('show');
                    }
                });
            });

            $(document).on('click', '.js-loan-calculator-modal', function (e) {
                e.preventDefault();

                var $trigger = $(this);
                var url = $trigger.data('href') || $trigger.attr('href');
                var title = $trigger.data('title') || 'Loan Calculator';

                if (!url || !$('.view_modal').length) {
                    window.location.href = url;
                    return;
                }

                if (url.indexOf('_lm_modal=1') === -1) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') + '_lm_modal=1';
                }

                $('.view_modal').html(
                    '<div class="modal-dialog modal-xl lm-dashboard-iframe-modal" role="document" style="width:96%;max-width:1180px;">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header">' +
                                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                                '<h4 class="modal-title">' + $('<div>').text(title).html() + '</h4>' +
                            '</div>' +
                            '<div class="modal-body" style="padding:0;height:86vh;">' +
                                '<iframe src="' + $('<div>').text(url).html() + '" style="width:100%;height:100%;border:0;" title="' + $('<div>').text(title).html() + '"></iframe>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                ).modal('show');
            });
        })(jQuery);
    </script>
    <script>
        (function($){
            var modalUrls = {
                searchCustomers: "{{ route('loan-management.loans.ajax.search-customers') }}",
                previewSchedule: "{{ route('loan-management.loans.preview-standalone-schedule') }}",
                storeLoan: "{{ route('loan-management.loans.store-standalone') }}",
                loanList: "{{ route('loan-management.loans') }}"
            };

            var modalSearchTimer = null;
            var modalFormLoaded = false;

            function modalMoney(v) {
                var n = parseFloat(v || 0);
                return Number.isFinite(n) ? n.toFixed(2) : '0.00';
            }

            function modalParseNum(v) {
                var n = parseFloat(String(v || '').replace(/,/g, '').trim());
                return Number.isFinite(n) ? n : 0;
            }

            function openStandaloneLoanModal() {
                var $modal = $('#standaloneLoanModal');
                var $body = $modal.find('#standaloneLoanModalBody');

                if (modalFormLoaded) {
                    $modal.modal('show');
                    return;
                }

                var url = $modal.data('form-url');
                if (!url) {
                    var $trigger = $('.lm-standalone-loan-trigger').first();
                    url = $trigger.data('url');
                    $modal.data('form-url', url);
                }

                $body.html(
                    '<div class="text-center" style="padding: 40px 16px;">' +
                        '<i class="fa fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i>' +
                        '<p style="margin-top: 12px; color: #64748b;">Loading form...</p>' +
                    '</div>'
                );
                $modal.modal('show');

                $.ajax({
                    url: url,
                    dataType: 'html',
                    success: function(result) {
                        $body.html(result);
                        modalFormLoaded = true;
                        initStandaloneLoanModalEvents($body);
                    },
                    error: function(xhr) {
                        $body.html(
                            '<div class="text-center" style="padding: 40px 16px;">' +
                                '<div class="alert alert-danger" style="margin-bottom:0;">Failed to load form. Please try again.</div>' +
                            '</div>'
                        );
                    }
                });
            }

            function initStandaloneLoanModalEvents($body) {
                if ($body.data('lm-events-bound')) return;
                $body.data('lm-events-bound', true);

                // Item management
                $body.on('click', '#modalBtnAddItem', function() {
                    var idx = $body.find('#mobProductList .mob-product-item').length;
                    var inputUid = Date.now() + '-' + idx;
                    var card = '<div class="mob-product-item" data-idx="'+idx+'">' +
                        '<div class="mob-product-ocr-row">' +
                            '<div class="mob-prod-img"><i class="fa fa-image"></i><img class="mob-product-photo-preview" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:8px;"><input type="hidden" name="items['+idx+'][product_photo]" class="modal-item-image"><input type="hidden" name="items['+idx+'][product_ocr_raw_text]" class="modal-item-ocr-raw"></div>' +
                            '<div class="mob-product-actions">' +
                                '<button type="button" class="mob-product-photo-btn" onclick="mobOpenProductPhotoSheet(this)"><i class="fa fa-camera"></i> Take or Upload Product</button>' +
                            '</div>' +
                            '<div class="mob-prod-num">#'+(idx+1)+'</div>' +
                            '<div class="mob-prod-del modal-btn-remove-item"><i class="fa fa-trash"></i></div>' +
                            '<div class="mob-product-ocr-status"></div>' +
                        '</div>' +
                        '<input type="file" id="mobProductCamera'+inputUid+'" accept="image/*" capture="environment" style="display:none;" onchange="mobHandleProductPhoto(this)">' +
                        '<input type="file" id="mobProductUpload'+inputUid+'" accept="image/*" style="display:none;" onchange="mobHandleProductPhoto(this)">' +
                        '<div class="mob-product-fields">' +
                            '<div class="mob-field wide"><label>Product</label><input type="text" name="items['+idx+'][product_name]" class="mob-input modal-item-name" placeholder="Product name"></div>' +
                            '<div class="mob-field"><label>IMEI</label><input type="text" name="items['+idx+'][imei]" class="mob-input" placeholder="IMEI"></div>' +
                            '<div class="mob-field"><label>Serial Number</label><input type="text" name="items['+idx+'][serial_number]" class="mob-input" placeholder="Serial number"></div>' +
                            '<div class="mob-field"><label>Color</label><input type="text" name="items['+idx+'][color]" class="mob-input" placeholder="Color"></div>' +
                            '<div class="mob-field"><label>Storage</label><input type="text" name="items['+idx+'][storage]" class="mob-input" placeholder="128GB"></div>' +
                            '<div class="mob-field"><label>SKU</label><input type="text" name="items['+idx+'][sku]" class="mob-input" placeholder="SKU"></div>' +
                            '<div class="mob-field"><label>Qty</label><input type="number" name="items['+idx+'][qty]" class="mob-input modal-item-qty" min="1" value="1"></div>' +
                            '<div class="mob-field"><label>Unit Price</label><input type="number" name="items['+idx+'][unit_price]" class="mob-input modal-item-price" min="0" step="0.01" value="0"></div>' +
                            '<div class="mob-product-total"><label>Total</label><div class="modal-item-total">$0.00</div></div>' +
                        '</div>' +
                    '</div>';
                    $body.find('#mobProductList').append(card);
                });

                $body.on('click', '.modal-btn-remove-item', function() {
                    $(this).closest('.mob-product-item').remove();
                    modalRecalcItemTotals($body);
                });

                $body.on('input change', '.modal-item-qty, .modal-item-price', function() {
                    modalRecalcItemTotals($body);
                });

                // Customer Select2
                if ($.fn.select2) {
                    $body.find('#modalCustomerSelect').select2({
                        ajax: {
                            url: '/contacts/customers',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return { q: params.term, page: params.page };
                            },
                            processResults: function(data) {
                                return { results: data };
                            }
                        },
                        templateResult: function(data) {
                            if (!data.id) return data.text;
                            var html = '';
                            if (data.supplier_business_name) {
                                html += '<strong>' + data.supplier_business_name + '</strong><br>';
                            }
                            html += data.text;
                            if (data.mobile) {
                                html += '<br><small style="color:#6b7280;">' + data.mobile + '</small>';
                            }
                            return html;
                        },
                        templateSelection: function(data) {
                            return data.text || data.id;
                        },
                        minimumInputLength: 1,
                        language: {
                            inputTooShort: function(args) {
                                return 'Please enter ' + args.minimum + ' or more characters';
                            },
                            noResults: function() {
                                return 'No customer found';
                            }
                        },
                        escapeMarkup: function(markup) { return markup; },
                        dropdownParent: $body.closest('.modal-content')
                    });
                }

                modalInitAddressSelects($body);
                modalLoadAddressOptions($body, 'province');

                $body.on('change', '#modalProvinceSelect', function() {
                    modalResetAddressSelect($body, 'district');
                    modalResetAddressSelect($body, 'commune');
                    modalResetAddressSelect($body, 'village');
                    modalSyncAddressFields($body);

                    if ($(this).val()) {
                        modalLoadAddressOptions($body, 'district', {
                            province_code: $(this).val()
                        });
                    }
                });

                $body.on('change', '#modalDistrictSelect', function() {
                    modalResetAddressSelect($body, 'commune');
                    modalResetAddressSelect($body, 'village');
                    modalSyncAddressFields($body);

                    if ($(this).val()) {
                        modalLoadAddressOptions($body, 'commune', {
                            district_code: $(this).val()
                        });
                    }
                });

                $body.on('change', '#modalCommuneSelect', function() {
                    modalResetAddressSelect($body, 'village');
                    modalSyncAddressFields($body);

                    if ($(this).val()) {
                        modalLoadAddressOptions($body, 'village', {
                            commune_code: $(this).val()
                        });
                    }
                });

                $body.on('change', '#modalVillageSelect', function() {
                    modalSyncAddressFields($body);
                });

                $body.on('select2:select', '#modalCustomerSelect', function(e) {
                    var data = e.params.data;
                    $body.find('#modalCustomerId').val(data.id || '');
                    $body.find('#modalCustomerName').val(data.text || data.name || '');
                    $body.find('#modalCustomerPhone').val(data.mobile || data.phone || '');
                    $body.find('#modalAlternatePhone').val(data.alternate_phone || data.alternate_number || '');
                    $body.find('#modalAlternatePhoneGroup').toggle(!!String(data.alternate_phone || data.alternate_number || '').trim());
                    $body.find('#modalCustomerAddress').val(data.shipping_address || data.address || '');
                    $body.find('#modalCustomerIdCard').val(data.id_card_number || '');
                    modalSelectAddressFromCustomer($body, data);
                    var name = data.text || data.name || '';
                    var initials = name.split(' ').map(function(w){ return w.charAt(0); }).join('').substring(0,2).toUpperCase();
                    $body.find('#modalCustomerAvatar').html(initials || '<i class="fa fa-user"></i>');
                });

                $(document).on('contact.created', function(e, contact) {
                    if (contact && contact.id) {
                        var $opt = new Option(contact.name, contact.id, true, true);
                        $body.find('#modalCustomerSelect').append($opt).trigger('change');
                        $body.find('#modalCustomerId').val(contact.id);
                        $body.find('#modalCustomerName').val(contact.name || '');
                        $body.find('#modalCustomerPhone').val(contact.mobile || '');
                        $body.find('#modalAlternatePhone').val(contact.alternate_phone || contact.alternate_number || '');
                        $body.find('#modalAlternatePhoneGroup').toggle(!!String(contact.alternate_phone || contact.alternate_number || '').trim());
                        $body.find('#modalCustomerAddress').val(contact.shipping_address || '');
                        $body.find('#modalCustomerIdCard').val(contact.id_card_number || '');
                        modalClearAddressSelects($body);
                        var name = contact.name || '';
                        var initials = name.split(' ').map(function(w){ return w.charAt(0); }).join('').substring(0,2).toUpperCase();
                        $body.find('#modalCustomerAvatar').html(initials || '<i class="fa fa-user"></i>');
                    }
                });

                $body.on('click', '#modalClearCustomer', function() {
                    $body.find('#modalCustomerSelect').val(null).trigger('change');
                    $body.find('#modalCustomerId').val('');
                    $body.find('#modalCustomerName').val('');
                    $body.find('#modalCustomerPhone').val('');
                    $body.find('#modalAlternatePhone').val('');
                    $body.find('#modalAlternatePhoneGroup').hide();
                    $body.find('#modalCustomerAddress').val('');
                    $body.find('#modalCustomerIdCard').val('');
                    modalClearAddressSelects($body);
                    $body.find('#modalCustomerAvatar').html('<i class="fa fa-user"></i>');
                });

                // Quick add contact form handler (pos.js not loaded in LM)
                $(document).off('submit', 'form#quick_add_contact').on('submit', 'form#quick_add_contact', function(e) {
                    e.preventDefault();
                    var $form = $(this);
                    var $submitBtn = $form.find('button[type="submit"]');
                    $submitBtn.prop('disabled', true);
                    $.ajax({
                        method: 'POST',
                        url: $form.attr('action'),
                        data: $form.serialize(),
                        dataType: 'json',
                        success: function(result) {
                            if (result.success == true) {
                                var contact = result.data;
                                var name = contact.name || '';
                                if (contact.supplier_business_name) {
                                    name += ' ' + contact.supplier_business_name;
                                }
                                var $opt = new Option(name.trim(), contact.id, true, true);
                                $body.find('#modalCustomerSelect').append($opt).trigger('change');
                                $body.find('#modalCustomerId').val(contact.id);
                                $body.find('#modalCustomerName').val(contact.name || '');
                                $body.find('#modalCustomerPhone').val(contact.mobile || '');
                                $body.find('#modalAlternatePhone').val(contact.alternate_phone || contact.alternate_number || '');
                                $body.find('#modalAlternatePhoneGroup').toggle(!!String(contact.alternate_phone || contact.alternate_number || '').trim());
                                $body.find('#modalCustomerAddress').val(contact.shipping_address || '');
                                $body.find('#modalCustomerIdCard').val(contact.id_card_number || '');
                                modalClearAddressSelects($body);
                                $body.find('.contact_modal').modal('hide');
                                if (window.toastr) {
                                    toastr.success(result.msg || 'Contact created');
                                }
                            } else {
                                if (window.toastr) {
                                    toastr.error(result.msg || 'Failed to create contact');
                                }
                            }
                        },
                        error: function() {
                            if (window.toastr) {
                                toastr.error('Failed to submit contact form');
                            }
                        },
                        complete: function() {
                            $submitBtn.prop('disabled', false);
                        }
                    });
                });

                $(document).on('hidden.bs.modal', '.contact_modal', function() {
                    var $form = $(this).find('form#quick_add_contact');
                    $form.find('button[type="submit"]').removeAttr('disabled');
                    $form[0] && $form[0].reset();
                });

                // Summary recalc
                $body.on('input change', '.modal-payment-amount, #modalPrincipalAmount', function() {
                    modalRecalcSummary($body);
                });

                $body.on('click', '#modalBtnAddPayment', function() {
                    modalAddPaymentRow($body);
                    modalRecalcSummary($body);
                });

                $body.on('click', '.modal-btn-remove-payment', function() {
                    $(this).closest('.mob-payment-row').remove();
                    modalReindexPaymentRows($body);
                    modalRecalcSummary($body);
                });

                // Preview schedule
                $body.on('click', '#modalBtnPreviewSchedule', function() {
                    var $form = $body.find('#standaloneLoanModalForm');
                    $.post(modalUrls.previewSchedule, $form.serialize(), function(res) {
                        var rows = res.data || [];
                        var $tb = $body.find('#modalScheduleTable tbody');
                        var $table = $tb.closest('table');
                        var totalP = 0, totalI = 0, totalA = 0, totalB = 0;
                        $tb.empty();
                        rows.forEach(function(r) {
                            totalP += Number(r.principal || 0);
                            totalI += Number(r.interest || 0);
                            totalA += Number(r.total || 0);
                            totalB += Number(r.balance || 0);
                            $tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td class="text-right">'+modalMoney(r.principal)+'</td><td class="text-right">'+modalMoney(r.interest)+'</td><td class="text-right">'+modalMoney(r.total)+'</td><td class="text-right">'+modalMoney(r.balance)+'</td></tr>');
                        });
                        $table.find('tfoot th').eq(1).text(totalP.toFixed(2));
                        $table.find('tfoot th').eq(2).text(totalI.toFixed(2));
                        $table.find('tfoot th').eq(3).text(totalA.toFixed(2));
                        $table.find('tfoot th').eq(4).text(totalB.toFixed(2));
                        $body.find('#modalScheduleSection').show();

                        // Update monthly estimate
                        var months = parseInt($body.find('input[name="duration_months"]').val()) || 1;
                        $body.find('#modalSummaryMonthly').text(modalMoney(totalA / months));
                    }).fail(function(xhr) {
                        if (window.toastr) {
                            toastr.error(xhr.responseJSON?.message || 'Failed to preview schedule');
                        } else {
                            alert(xhr.responseJSON?.message || 'Failed to preview schedule');
                        }
                    });
                });

                // Form submit safety net (prevents native submit on Enter)
                $body.on('submit', '#standaloneLoanModalForm', function(e) {
                    e.preventDefault();
                });
            }

            function modalLoadAddressOptions($body, level, data, selectedCode, callback) {
                var urls = {
                    sync: "{{ route('loan-management.cambodia-address.sync') }}",
                    province: "{{ route('loan-management.cambodia-address.provinces') }}",
                    district: "{{ route('loan-management.cambodia-address.districts') }}",
                    commune: "{{ route('loan-management.cambodia-address.communes') }}",
                    village: "{{ route('loan-management.cambodia-address.villages') }}"
                };
                var $select = modalAddressSelect($body, level);

                if (!$select.length) {
                    return;
                }

                $select.prop('disabled', true);
                modalRefreshAddressSelect($select);
                $body.find('#modalAddressLoadStatus').text(
                    level === 'province'
                        ? 'Loading Cambodia address list...'
                        : 'Loading ' + level + ' list...'
                );

                $.ajax({
                    method: 'GET',
                    url: urls[level],
                    data: data || {},
                    dataType: 'json',
                    timeout: 120000,
                    success: function(response) {
                        if (level === 'province' && response.needs_sync) {
                            modalSyncCambodiaAddressData($body, function() {
                                modalLoadAddressOptions($body, level, data, selectedCode, callback);
                            });
                            return;
                        }

                        modalPopulateAddressSelect($select, response.items || [], selectedCode);
                        $body.find('#modalAddressLoadStatus').text('');

                        if (typeof callback === 'function') {
                            callback();
                        }
                    },
                    error: function() {
                        $body.find('#modalAddressLoadStatus').text('Unable to load Cambodia address list. Please try again.');
                        if (window.toastr) {
                            toastr.error('Unable to load Cambodia address list');
                        }
                    }
                });
            }

            function modalSyncCambodiaAddressData($body, callback, page) {
                var $province = modalAddressSelect($body, 'province');

                $province.empty().append($('<option>', {
                    value: '',
                    text: 'Preparing Cambodia address list...'
                })).prop('disabled', true);
                modalRefreshAddressSelect($province);
                $body.find('#modalAddressLoadStatus').text('Preparing Cambodia address list...');

                $.ajax({
                    method: 'GET',
                    url: "{{ route('loan-management.cambodia-address.sync') }}",
                    data: page ? { page: page } : {},
                    dataType: 'json',
                    timeout: 120000,
                    success: function(response) {
                        var sync = response.sync || {};
                        var totalPages = sync.total_pages || '';
                        var nextPage = sync.next_page || '';
                        var retryAfter = (parseInt(sync.retry_after, 10) || 0) * 1000;

                        if (!sync.complete && nextPage) {
                            var message = 'Preparing Cambodia address list ' + nextPage + '/' + totalPages + '...';
                            $province.find('option:first').text(message);
                            modalRefreshAddressSelect($province);
                            $body.find('#modalAddressLoadStatus').text(message);
                            setTimeout(function() {
                                modalSyncCambodiaAddressData($body, callback, nextPage);
                            }, retryAfter);
                            return;
                        }

                        $body.find('#modalAddressLoadStatus').text('');

                        if (typeof callback === 'function') {
                            callback();
                        }
                    },
                    error: function() {
                        $province.empty().append($('<option>', {
                            value: '',
                            text: '-- Select --'
                        })).prop('disabled', true);
                        modalRefreshAddressSelect($province);
                        $body.find('#modalAddressLoadStatus').text('Unable to prepare Cambodia address list. Please try again.');
                        if (window.toastr) {
                            toastr.error('Unable to prepare Cambodia address list');
                        }
                    }
                });
            }

            function modalInitAddressSelects($body) {
                if (!$.fn.select2) {
                    return;
                }

                $body.find('#modalProvinceSelect, #modalDistrictSelect, #modalCommuneSelect, #modalVillageSelect').each(function() {
                    var $select = $(this);

                    if ($select.data('select2')) {
                        return;
                    }

                    $select.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: '-- Select --',
                        dropdownParent: $body.closest('.modal-content')
                    });
                });
            }

            function modalRefreshAddressSelect($select) {
                if ($.fn.select2 && $select.data('select2')) {
                    $select.trigger('change.select2');
                }
            }

            function modalPopulateAddressSelect($select, items, selectedCode) {
                $select.empty().append($('<option>', {
                    value: '',
                    text: '-- Select --'
                }));

                $.each(items, function(index, item) {
                    $select.append($('<option>', {
                        value: item.code,
                        text: item.label || item.kh || item.en || item.code
                    }).attr({
                        'data-kh': item.kh || '',
                        'data-en': item.en || ''
                    }));
                });

                $select.prop('disabled', items.length === 0);

                if (selectedCode) {
                    $select.val(selectedCode);
                }

                modalRefreshAddressSelect($select);
                modalSyncAddressFields($select.closest('#standaloneLoanModalBody'));
            }

            function modalResetAddressSelect($body, level) {
                modalAddressSelect($body, level)
                    .empty()
                    .append($('<option>', {
                        value: '',
                        text: '-- Select --'
                    }))
                    .prop('disabled', true);
                modalRefreshAddressSelect(modalAddressSelect($body, level));
                modalAddressNameInput($body, level).val('');
            }

            function modalClearAddressSelects($body) {
                modalAddressSelect($body, 'province').val('');
                modalRefreshAddressSelect(modalAddressSelect($body, 'province'));
                modalResetAddressSelect($body, 'district');
                modalResetAddressSelect($body, 'commune');
                modalResetAddressSelect($body, 'village');
                modalSyncAddressFields($body);
            }

            function modalSelectAddressFromCustomer($body, data) {
                var provinceCode = data.province_code || '';
                var districtCode = data.district_code || '';
                var communeCode = data.commune_code || '';
                var villageCode = data.village_code || '';

                if (!provinceCode) {
                    modalClearAddressSelects($body);
                    return;
                }

                modalLoadAddressOptions($body, 'province', {}, provinceCode, function() {
                    if (!districtCode) {
                        modalSyncAddressFields($body);
                        return;
                    }

                    modalLoadAddressOptions($body, 'district', { province_code: provinceCode }, districtCode, function() {
                        if (!communeCode) {
                            modalSyncAddressFields($body);
                            return;
                        }

                        modalLoadAddressOptions($body, 'commune', { district_code: districtCode }, communeCode, function() {
                            if (!villageCode) {
                                modalSyncAddressFields($body);
                                return;
                            }

                            modalLoadAddressOptions($body, 'village', { commune_code: communeCode }, villageCode, function() {
                                modalSyncAddressFields($body);
                            });
                        });
                    });
                });
            }

            function modalSyncAddressFields($body) {
                $.each(['province', 'district', 'commune', 'village'], function(index, level) {
                    modalAddressNameInput($body, level).val(modalSelectedAddressText($body, level));
                });
            }

            function modalSelectedAddressText($body, level) {
                var $select = modalAddressSelect($body, level);
                var $option = $select.find('option:selected');

                if (!$select.val()) {
                    return '';
                }

                return $option.data('kh') || $option.text() || '';
            }

            function modalAddressSelect($body, level) {
                var ids = {
                    province: '#modalProvinceSelect',
                    district: '#modalDistrictSelect',
                    commune: '#modalCommuneSelect',
                    village: '#modalVillageSelect'
                };

                return $body.find(ids[level]);
            }

            function modalAddressNameInput($body, level) {
                var ids = {
                    province: '#modalProvinceName',
                    district: '#modalDistrictName',
                    commune: '#modalCommuneName',
                    village: '#modalVillageName'
                };

                return $body.find(ids[level]);
            }

            function modalRecalcItemTotals($body) {
                var total = 0;
                $body.find('#mobProductList .mob-product-item').each(function() {
                    var qty = modalParseNum($(this).find('.modal-item-qty').val());
                    var price = modalParseNum($(this).find('.modal-item-price').val());
                    var lineTotal = Math.round(qty * price * 100) / 100;
                    $(this).find('.modal-item-total').text('$' + lineTotal.toFixed(2));
                    total += lineTotal;
                });
                $body.data('product-total', total);
                modalRecalcSummary($body);
            }

            function modalRecalcSummary($body) {
                var productTotal = modalProductTotal($body);
                var downPayment = modalPaymentTotal($body);
                var due = Math.max(0, Math.round((productTotal - downPayment) * 100) / 100);
                $body.find('#modalComputedPrincipal').text('$' + due.toFixed(2));
                $body.find('#modalPrincipalAmount').val(due > 0 ? due.toFixed(2) : '');
                $body.find('#modalSummaryTotal').text(modalMoney(productTotal));
                $body.find('#modalSummaryDownPayment').text(modalMoney(downPayment));
                $body.find('#modalSummaryDue').text(modalMoney(due));
                $body.find('#modalDownPaymentHidden').val(downPayment.toFixed(2));
            }

            function modalProductTotal($body) {
                var storedTotal = parseFloat($body.data('product-total'));
                if (Number.isFinite(storedTotal)) {
                    return Math.round(storedTotal * 100) / 100;
                }

                var total = 0;
                $body.find('#mobProductList .mob-product-item').each(function() {
                    var qty = modalParseNum($(this).find('.modal-item-qty').val());
                    var price = modalParseNum($(this).find('.modal-item-price').val());
                    total += Math.round(qty * price * 100) / 100;
                });
                return Math.round(total * 100) / 100;
            }

            function modalPaymentTotal($body) {
                var total = 0;
                $body.find('.modal-payment-amount').each(function() {
                    total += modalParseNum($(this).val());
                });
                return Math.round(total * 100) / 100;
            }

            function modalAddPaymentRow($body) {
                var $list = $body.find('#mobDepositPayments');
                var $source = $list.find('.mob-payment-row:first');
                if (!$source.length) return;

                var $row = $source.clone(false);
                $row.find('input').each(function() {
                    var type = ($(this).attr('type') || '').toLowerCase();
                    if (type === 'date') {
                        $(this).val($source.find('input[type="date"]').first().val() || '');
                    } else if (type === 'hidden') {
                        $(this).val($(this).attr('name') && $(this).attr('name').indexOf('[exchange_rate]') !== -1 ? '1' : ($(this).attr('name') && $(this).attr('name').indexOf('[currency]') !== -1 ? 'USD' : ''));
                    } else {
                        $(this).val(type === 'number' ? '0' : '');
                    }
                });
                $row.find('select').each(function() {
                    $(this).val($source.find('select').first().val() || $(this).find('option:first').val());
                });
                $list.append($row);
                modalReindexPaymentRows($body);
            }

            function modalReindexPaymentRows($body) {
                var $rows = $body.find('#mobDepositPayments .mob-payment-row');
                $rows.each(function(index) {
                    var $row = $(this);
                    $row.attr('data-payment-index', index);
                    $row.find('.mob-payment-title').text('Payment #' + (index + 1));
                    $row.find('.modal-btn-remove-payment').toggle($rows.length > 1);
                    $row.find('[name]').each(function() {
                        this.name = this.name.replace(/payments\[\d+\]/, 'payments[' + index + ']');
                    });
                });
            }

            // Bind triggers
            $(document).on('click', '.lm-standalone-loan-trigger', function() {
                var url = $(this).data('url');
                var target = $(this).data('target') || '#standaloneLoanModal';
                if (url) {
                    $(target).data('form-url', url);
                }
                modalFormLoaded = false;
                openStandaloneLoanModal();
            });

            // Reset form on modal close
            $('#standaloneLoanModal').on('hidden.bs.modal', function() {
                // Keep form loaded for speed, just scroll to top
                $(this).find('#standaloneLoanModalBody').scrollTop(0);
            });

            // ============ QUICK PAY MODAL ============
            var quickPayLoaded = {};

            $(document).on('click', '.lm-quick-pay-trigger', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                var loanId = $(this).data('loan-id');
                if (!url) return;

                var $modal = $('#quickPayModal');
                var $body = $modal.find('#quickPayModalBody');

                if (quickPayLoaded[loanId]) {
                    $modal.modal('show');
                    return;
                }

                $body.html(
                    '<div class="text-center" style="padding: 40px 16px;">' +
                        '<i class="fa fa-spinner fa-spin fa-2x" style="color: #3b82f6;"></i>' +
                        '<p style="margin-top: 12px; color: #64748b;">Loading payment form...</p>' +
                    '</div>'
                );
                $modal.modal('show');

                $.ajax({
                    url: url,
                    dataType: 'html',
                    success: function(result) {
                        $body.html(result);
                        quickPayLoaded[loanId] = true;
                    },
                    error: function(xhr) {
                        $body.html(
                            '<div class="text-center" style="padding: 40px 16px;">' +
                                '<div class="alert alert-danger" style="margin-bottom:0;">Failed to load payment form.</div>' +
                            '</div>'
                        );
                    }
                });
            });

            $('#quickPayModal').on('hidden.bs.modal', function() {
                quickPayLoaded = {};
            });

        })(jQuery);
    </script>
    @yield('loan_js')

    <style>
        @media print {
            body,
            .lm-main,
            .lm-content {
                overflow: visible !important;
            }

            .lm-sidebar,
            #loanManagementHeader,
            .lm-breadcrumb-wrap,
            .lm-footer {
                display: none !important;
            }
        }
    </style>
</body>
</html>
