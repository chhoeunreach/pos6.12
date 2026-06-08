<?php $request = app('Illuminate\Http\Request'); ?>

<?php
    use Modules\LoanManagement\Helpers\LoanMenuHelper;

    if (! function_exists('loan_user_can')) {
        function loan_user_can($permission) {
            return LoanMenuHelper::loanUserCan((string) $permission);
        }
    }

    $whitelist = ['127.0.0.1', '::1'];
    $moduleCssPath = base_path('Modules/LoanManagement/Resources/assets/css/loan-management.css');
    $moduleJsPath = base_path('Modules/LoanManagement/Resources/assets/js/loan-management.js');
    $loanBadgeCounts = LoanMenuHelper::badgeCounts();
    $pageTitle = trim($__env->yieldContent('title')) !== '' ? $__env->yieldContent('title').' - LoanManagement' : 'LoanManagement';
    $businessName = Session::get('business.name');
?>

<!DOCTYPE html>
<html class="tw-bg-white tw-scroll-smooth" lang="<?php echo e(app()->getLocale(), false); ?>"
    dir="<?php echo e(in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr', false); ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="<?php echo e(csrf_token(), false); ?>">
    <title><?php echo e($pageTitle, false); ?><?php if(!empty($businessName)): ?> - <?php echo e($businessName, false); ?><?php endif; ?></title>

    <?php echo $__env->make('layouts.partials.css', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('layouts.partials.extracss', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php if(file_exists($moduleCssPath)): ?>
        <style><?php echo file_get_contents($moduleCssPath); ?></style>
    <?php endif; ?>
    <?php echo $__env->yieldContent('loan_css'); ?>
</head>
<body class="hold-transition skin-blue-light sidebar-mini loan-management-page tw-font-sans tw-antialiased tw-text-gray-900 tw-bg-gray-100">
    <?php if(in_array($request->ip(), $whitelist, true)): ?>
        <input type="hidden" id="__is_localhost" value="true">
    <?php endif; ?>

    <input type="hidden" id="__code" value="<?php echo e(session('currency.code'), false); ?>">
    <input type="hidden" id="__symbol" value="<?php echo e(session('currency.symbol'), false); ?>">
    <input type="hidden" id="__thousand" value="<?php echo e(session('currency.thousand_separator'), false); ?>">
    <input type="hidden" id="__decimal" value="<?php echo e(session('currency.decimal_separator'), false); ?>">
    <input type="hidden" id="__symbol_placement" value="<?php echo e(session('business.currency_symbol_placement'), false); ?>">
    <input type="hidden" id="__precision" value="<?php echo e(session('business.currency_precision', 2), false); ?>">
    <input type="hidden" id="__quantity_precision" value="<?php echo e(session('business.quantity_precision', 2), false); ?>">

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view_export_buttons')): ?>
        <input type="hidden" id="view_export_buttons">
    <?php endif; ?>
    <?php if(isMobile()): ?>
        <input type="hidden" id="__is_mobile">
    <?php endif; ?>
    <?php if(session('status')): ?>
        <input type="hidden" id="status_span" data-status="<?php echo e(session('status.success'), false); ?>"
            data-msg="<?php echo e(session('status.msg'), false); ?>">
    <?php endif; ?>
    <?php if(config('constants.iraqi_selling_price_adjustment')): ?>
        <input type="hidden" id="iraqi_selling_price_adjustment">
    <?php endif; ?>

    <div class="tw-flex thetop">
        <main class="tw-flex tw-flex-col tw-flex-1 tw-h-full tw-min-w-0 tw-bg-gray-100">
            <div id="main_app_header"></div>
            <div id="app"></div>
            <div class="tw-flex-1 tw-overflow-y-auto tw-h-screen" id="scrollable-container">
                <div class="lm-app" id="loanManagementApp">
                    <?php echo $__env->make('loanmanagement::layouts.sidebar', ['loanBadgeCounts' => $loanBadgeCounts], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                    <div class="lm-main" id="loanManagementMain">
                        <?php echo $__env->make('loanmanagement::layouts.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                        <main class="lm-content">
                            <?php echo $__env->make('loanmanagement::layouts.breadcrumb', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                            <div class="container-fluid lm-workspace">
                                <?php echo $__env->yieldContent('content_body'); ?>
                            </div>
                        </main>

                        <?php if(auth()->user()?->can('superadmin') || auth()->user()?->can('sell.create')): ?>
                            <?php echo $__env->make('loanmanagement::layouts.partials.sell_pos_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <?php endif; ?>

                        <?php if(loan_user_can('loan_management.create_from_sell|loan_management.loans.create|loan_management.create')): ?>
                            <?php echo $__env->make('loanmanagement::layouts.partials.auto_installment_modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <?php endif; ?>

                        <?php echo $__env->make('loanmanagement::layouts.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="invoice print_section" id="receipt_section"></section>
    <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    <div class="overlay tw-hidden"></div>

    <audio id="success-audio">
        <source src="<?php echo e(asset('/audio/success.ogg?v=' . $asset_v), false); ?>" type="audio/ogg">
        <source src="<?php echo e(asset('/audio/success.mp3?v=' . $asset_v), false); ?>" type="audio/mpeg">
    </audio>
    <audio id="error-audio">
        <source src="<?php echo e(asset('/audio/error.ogg?v=' . $asset_v), false); ?>" type="audio/ogg">
        <source src="<?php echo e(asset('/audio/error.mp3?v=' . $asset_v), false); ?>" type="audio/mpeg">
    </audio>
    <audio id="warning-audio">
        <source src="<?php echo e(asset('/audio/warning.ogg?v=' . $asset_v), false); ?>" type="audio/ogg">
        <source src="<?php echo e(asset('/audio/warning.mp3?v=' . $asset_v), false); ?>" type="audio/mpeg">
    </audio>

    <?php if(!empty($__additional_html)): ?>
        <?php echo $__additional_html; ?>

    <?php endif; ?>

    <?php echo $__env->make('layouts.partials.javascripts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('layouts.module-assets', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php if(file_exists($moduleJsPath)): ?>
        <script><?php echo file_get_contents($moduleJsPath); ?></script>
    <?php endif; ?>

    <?php if(!empty($__additional_views) && is_array($__additional_views)): ?>
        <?php $__currentLoopData = $__additional_views; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $additional_view): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if ($__env->exists($additional_view)) echo $__env->make($additional_view, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

    <?php if(auth()->user()?->can('superadmin') || auth()->user()?->can('sell.create')): ?>
        <script>
            (function($){
                var loanPosRoutes = {
                    cloneSellBase: "<?php echo e(url('/loan-management/loans/sell'), false); ?>",
                    previewSchedule: "<?php echo e(route('loan-management.loans.preview-schedule'), false); ?>",
                    loanViewBase: "<?php echo e(url('/loan-management/loans'), false); ?>",
                    loanPrintModalBase: "<?php echo e(url('/loan-management/loans'), false); ?>"
                };
                var lastAutoInstallmentTransactionId = null;
                var loanPosPrintFinalizeTimer = null;

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

                function finalizeLoanPosSaleSaved(receipt, transactionId) {
                    if (loanPosPrintFinalizeTimer) {
                        window.clearTimeout(loanPosPrintFinalizeTimer);
                    }

                    loanPosPrintFinalizeTimer = window.setTimeout(function(){
                        $(document).trigger('loan:sell-pos-saved', [receipt || null, transactionId || null]);
                    }, 400);
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

                $(document).on('click', '.js-open-existing-loan-detail', function(event){
                    event.preventDefault();
                    openLoanDetailFrameModal($(this).data('loan-url'), 'Loan Detail');
                });

                window.loanManagementOpenAutoInstallment = openAutoInstallment;
                window.loanManagementDirectPrintUrl = directLoanManagementPrintUrl;
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
    <?php endif; ?>

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
    <?php echo $__env->yieldContent('loan_js'); ?>

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
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/layouts/app.blade.php ENDPATH**/ ?>