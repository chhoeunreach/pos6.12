@php
    $lmLang = session('user.language', config('app.locale'));
    $lmIsKhmer = request('lang') === 'km' || $lmLang === 'km' || request()->cookie('lm_lang') === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<!-- ========================================================
     ENTERPRISE LOAN MANAGEMENT - PROFESSIONAL ALERT & DIALOG SYSTEM
     ======================================================== -->
<style>
    /* Modern Dialog Overlay */
    .lm-dialog-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999999;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.22s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.22s;
    }
    .lm-dialog-overlay.is-active {
        opacity: 1;
        visibility: visible;
    }

    /* Dialog Card */
    .lm-dialog-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 25px 60px -12px rgba(15, 23, 42, 0.25), 0 0 1px 1px rgba(15, 23, 42, 0.05);
        width: 100%;
        max-width: 440px;
        padding: 34px 30px 28px;
        text-align: center;
        transform: scale(0.92) translateY(14px);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        font-family: var(--lm-font-family, 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
        box-sizing: border-box;
    }
    .lm-dialog-overlay.is-active .lm-dialog-card {
        transform: scale(1) translateY(0);
    }

    /* Icon Container */
    .lm-dialog-icon-wrap {
        margin: 0 auto 20px;
        display: flex;
        justify-content: center;
    }
    .lm-dialog-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    .lm-dialog-icon svg {
        width: 36px;
        height: 36px;
        stroke-width: 2.2;
    }
    .lm-dialog-icon--success {
        background: #ecfdf5;
        color: #10b981;
        box-shadow: 0 0 0 10px #f0fdf4;
    }
    .lm-dialog-icon--danger,
    .lm-dialog-icon--error {
        background: #fef2f2;
        color: #ef4444;
        box-shadow: 0 0 0 10px #fff1f2;
    }
    .lm-dialog-icon--warning {
        background: #fffbeb;
        color: #f59e0b;
        box-shadow: 0 0 0 10px #fefce8;
    }
    .lm-dialog-icon--info,
    .lm-dialog-icon--question {
        background: #eef2ff;
        color: #6366f1;
        box-shadow: 0 0 0 10px #f5f3ff;
    }

    /* Typography */
    .lm-dialog-title {
        margin: 0 0 10px;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.015em;
        line-height: 1.35;
    }
    .lm-dialog-message {
        font-size: 14px;
        color: #64748b;
        line-height: 1.6;
        margin: 0 0 26px;
        word-break: break-word;
        white-space: pre-line;
    }

    /* Action Buttons */
    .lm-dialog-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        justify-content: center;
    }
    .lm-dialog-btn {
        flex: 1;
        padding: 11px 20px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        outline: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        font-family: inherit;
    }
    .lm-dialog-btn:active {
        transform: scale(0.97);
    }
    .lm-dialog-btn--cancel {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #475569;
    }
    .lm-dialog-btn--cancel:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }
    .lm-dialog-btn--confirm {
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
    }
    .lm-dialog-btn--confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px -2px rgba(15, 23, 42, 0.25);
    }
    .lm-dialog-btn--primary {
        background: linear-gradient(135deg, var(--lm-primary, #6366f1) 0%, #4f46e5 100%);
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    }
    .lm-dialog-btn--danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }
    .lm-dialog-btn--success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
    }
    .lm-dialog-btn--warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
    }

    /* ========================================================
       MODERN TOASTR NOTIFICATION OVERRIDES
       ======================================================== */
    #toast-container {
        z-index: 99999999 !important;
        font-family: var(--lm-font-family, 'Kantumruy Pro', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif) !important;
    }
    #toast-container > div {
        border-radius: 16px !important;
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.16), 0 4px 12px -2px rgba(15, 23, 42, 0.08) !important;
        opacity: 0.98 !important;
        padding: 16px 20px 16px 56px !important;
        width: 380px !important;
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        backdrop-filter: blur(12px) !important;
        -webkit-backdrop-filter: blur(12px) !important;
        margin-bottom: 12px !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    #toast-container > div:hover {
        box-shadow: 0 20px 45px -4px rgba(15, 23, 42, 0.22) !important;
        transform: translateY(-2px) !important;
    }
    #toast-container > .toast-success {
        background-color: #ecfdf5 !important;
        color: #065f46 !important;
        border-left: 6px solid #10b981 !important;
        background-image: none !important;
        position: relative !important;
    }
    #toast-container > .toast-success::before {
        content: '';
        position: absolute;
        left: 18px;
        top: 18px;
        width: 22px;
        height: 22px;
        background-repeat: no-repeat;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2310b981' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'%3E%3C/path%3E%3Cpolyline points='22 4 12 14.01 9 11.01'%3E%3C/polyline%3E%3C/svg%3E");
    }
    #toast-container > .toast-error {
        background-color: #fef2f2 !important;
        color: #991b1b !important;
        border-left: 6px solid #ef4444 !important;
        background-image: none !important;
        position: relative !important;
    }
    #toast-container > .toast-error::before {
        content: '';
        position: absolute;
        left: 18px;
        top: 18px;
        width: 22px;
        height: 22px;
        background-repeat: no-repeat;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ef4444' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='15' y1='9' x2='9' y2='15'%3E%3C/line%3E%3Cline x1='9' y1='9' x2='15' y2='15'%3E%3C/line%3E%3C/svg%3E");
    }
    #toast-container > .toast-warning {
        background-color: #fffbeb !important;
        color: #92400e !important;
        border-left: 6px solid #f59e0b !important;
        background-image: none !important;
        position: relative !important;
    }
    #toast-container > .toast-warning::before {
        content: '';
        position: absolute;
        left: 18px;
        top: 18px;
        width: 22px;
        height: 22px;
        background-repeat: no-repeat;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23f59e0b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'%3E%3C/path%3E%3Cline x1='12' y1='9' x2='12' y2='13'%3E%3C/line%3E%3Cline x1='12' y1='17' x2='12.01' y2='17'%3E%3C/line%3E%3C/svg%3E");
    }
    #toast-container > .toast-info {
        background-color: #f0f9ff !important;
        color: #075985 !important;
        border-left: 6px solid #38bdf8 !important;
        background-image: none !important;
        position: relative !important;
    }
    #toast-container > .toast-info::before {
        content: '';
        position: absolute;
        left: 18px;
        top: 18px;
        width: 22px;
        height: 22px;
        background-repeat: no-repeat;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230284c7' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='12' y1='16' x2='12' y2='12'%3E%3C/line%3E%3Cline x1='12' y1='8' x2='12.01' y2='8'%3E%3C/line%3E%3C/svg%3E");
    }
    #toast-container .toast-title {
        font-weight: 800 !important;
        font-size: 14.5px !important;
        margin-bottom: 4px !important;
        line-height: 1.3 !important;
        color: inherit !important;
    }
    #toast-container .toast-message {
        font-size: 13px !important;
        line-height: 1.5 !important;
        font-weight: 500 !important;
        color: inherit !important;
        opacity: 0.92 !important;
    }
    #toast-container .toast-close-button {
        color: inherit !important;
        opacity: 0.4 !important;
        font-size: 18px !important;
        top: -6px !important;
        right: -2px !important;
        text-shadow: none !important;
        transition: opacity 0.15s ease !important;
    }
    #toast-container .toast-close-button:hover {
        opacity: 0.9 !important;
    }
    #toast-container .toast-progress {
        opacity: 0.35 !important;
        height: 3px !important;
    }

    /* Fallback Standalone Toast Stack */
    .lm-toast-stack {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 99999999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 380px;
        pointer-events: none;
    }
    .lm-toast-item {
        pointer-events: auto;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.85);
        padding: 16px 20px;
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.16);
        display: flex;
        align-items: flex-start;
        gap: 14px;
        animation: lmToastSlideIn 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        font-family: var(--lm-font-family, 'Kantumruy Pro', sans-serif);
    }
    .lm-toast-item--success { border-left: 6px solid #10b981; background: #ecfdf5; color: #065f46; }
    .lm-toast-item--error { border-left: 6px solid #ef4444; background: #fef2f2; color: #991b1b; }
    .lm-toast-item--warning { border-left: 6px solid #f59e0b; background: #fffbeb; color: #92400e; }
    .lm-toast-item--info { border-left: 6px solid #38bdf8; background: #f0f9ff; color: #075985; }
    @keyframes lmToastSlideIn {
        from { transform: translateX(110%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<!-- Dialog Modal DOM -->
<div id="lmGlobalDialogOverlay" class="lm-dialog-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="lm-dialog-card" id="lmDialogCard">
        <div class="lm-dialog-icon-wrap">
            <span class="lm-dialog-icon lm-dialog-icon--info" id="lmDialogIcon">
                <svg id="lmDialogIconSvg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </span>
        </div>
        <h3 class="lm-dialog-title" id="lmDialogTitle">{{ $lmText('Notification', 'ដំណឹង') }}</h3>
        <div class="lm-dialog-message" id="lmDialogMessage"></div>
        <div class="lm-dialog-actions" id="lmDialogActions">
            <button type="button" class="lm-dialog-btn lm-dialog-btn--cancel" id="lmDialogCancelBtn">
                {{ $lmText('Cancel', 'បោះបង់') }}
            </button>
            <button type="button" class="lm-dialog-btn lm-dialog-btn--confirm lm-dialog-btn--primary" id="lmDialogConfirmBtn">
                {{ $lmText('Confirm', 'យល់ព្រម') }}
            </button>
        </div>
    </div>
</div>

<div class="lm-toast-stack" id="lmToastStack"></div>

<script>
    (function () {
        var isKhmer = {{ $lmIsKhmer ? 'true' : 'false' }};
        var activeResolve = null;

        var i18n = {
            confirm: isKhmer ? 'យល់ព្រម' : 'Confirm',
            cancel: isKhmer ? 'បោះបង់' : 'Cancel',
            ok: isKhmer ? 'យល់ព្រម' : 'OK',
            delete: isKhmer ? 'លុប' : 'Delete',
            notice: isKhmer ? 'ដំណឹង' : 'Notice',
            success: isKhmer ? 'ជោគជ័យ' : 'Success',
            warning: isKhmer ? 'ការព្រមាន' : 'Warning',
            error: isKhmer ? 'កំហុស' : 'Error',
            areYouSure: isKhmer ? 'តើអ្នកប្រាកដជាចង់បន្ត?' : 'Are you sure you want to proceed?',
            deleteConfirm: isKhmer ? 'តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?' : 'Are you sure you want to delete this?'
        };

        var overlay = document.getElementById('lmGlobalDialogOverlay');
        var card = document.getElementById('lmDialogCard');
        var iconWrap = document.getElementById('lmDialogIcon');
        var iconSvg = document.getElementById('lmDialogIconSvg');
        var titleElem = document.getElementById('lmDialogTitle');
        var msgElem = document.getElementById('lmDialogMessage');
        var cancelBtn = document.getElementById('lmDialogCancelBtn');
        var confirmBtn = document.getElementById('lmDialogConfirmBtn');

        var svgPaths = {
            success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
            error: '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>',
            danger: '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>',
            warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
            info: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>',
            question: '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>'
        };

        var iconClassMap = {
            success: 'lm-dialog-icon--success',
            error: 'lm-dialog-icon--error',
            danger: 'lm-dialog-icon--danger',
            warning: 'lm-dialog-icon--warning',
            info: 'lm-dialog-icon--info',
            question: 'lm-dialog-icon--question'
        };

        var btnClassMap = {
            primary: 'lm-dialog-btn--primary',
            danger: 'lm-dialog-btn--danger',
            success: 'lm-dialog-btn--success',
            warning: 'lm-dialog-btn--warning'
        };

        function closeDialog(result) {
            if (!overlay) return;
            overlay.classList.remove('is-active');
            overlay.setAttribute('aria-hidden', 'true');
            if (activeResolve) {
                var resolve = activeResolve;
                activeResolve = null;
                resolve(result);
            }
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                closeDialog(false);
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                closeDialog(true);
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeDialog(false);
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (!overlay || !overlay.classList.contains('is-active')) return;
            if (e.key === 'Escape') {
                closeDialog(false);
            } else if (e.key === 'Enter') {
                closeDialog(true);
            }
        });

        var LoanAlert = {
            /**
             * Open a professional message alert dialog (replaces window.alert)
             */
            alert: function (message, options) {
                return new Promise(function (resolve) {
                    activeResolve = resolve;
                    options = typeof options === 'string' ? { title: options } : (options || {});
                    var type = options.type || 'info';
                    var title = options.title || i18n.notice;

                    iconWrap.className = 'lm-dialog-icon ' + (iconClassMap[type] || iconClassMap.info);
                    iconSvg.innerHTML = svgPaths[type] || svgPaths.info;

                    titleElem.textContent = title;
                    msgElem.textContent = message || '';

                    cancelBtn.style.display = 'none';
                    confirmBtn.textContent = options.confirmText || i18n.ok;
                    confirmBtn.className = 'lm-dialog-btn lm-dialog-btn--confirm ' + (btnClassMap[options.btnType || 'primary'] || 'lm-dialog-btn--primary');

                    overlay.classList.add('is-active');
                    overlay.setAttribute('aria-hidden', 'false');
                    confirmBtn.focus();
                });
            },

            /**
             * Open a professional confirmation dialog (replaces window.confirm)
             */
            confirm: function (message, options) {
                return new Promise(function (resolve) {
                    activeResolve = resolve;
                    options = options || {};
                    var type = options.type || (options.isDelete ? 'danger' : 'warning');
                    var isDel = type === 'danger' || options.isDelete;
                    var title = options.title || (isDel ? (isKhmer ? 'បញ្ជាក់ការលុប' : 'Confirm Action') : i18n.areYouSure);

                    iconWrap.className = 'lm-dialog-icon ' + (iconClassMap[type] || (isDel ? iconClassMap.danger : iconClassMap.warning));
                    iconSvg.innerHTML = svgPaths[type] || (isDel ? svgPaths.danger : svgPaths.warning);

                    titleElem.textContent = title;
                    msgElem.textContent = message || '';

                    cancelBtn.style.display = '';
                    cancelBtn.textContent = options.cancelText || i18n.cancel;

                    confirmBtn.textContent = options.confirmText || (isDel ? i18n.delete : i18n.confirm);
                    confirmBtn.className = 'lm-dialog-btn lm-dialog-btn--confirm ' + (isDel ? 'lm-dialog-btn--danger' : 'lm-dialog-btn--primary');

                    overlay.classList.add('is-active');
                    overlay.setAttribute('aria-hidden', 'false');
                    confirmBtn.focus();
                });
            },

            /**
             * Trigger modern toast notification
             */
            toast: function (type, message, title) {
                type = type || 'success';
                if (type === 'danger') type = 'error';

                if (window.toastr) {
                    toastr.options = {
                        closeButton: true,
                        debug: false,
                        newestOnTop: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        preventDuplicates: true,
                        escapeHtml: true,
                        showDuration: '300',
                        hideDuration: '600',
                        timeOut: '4500',
                        extendedTimeOut: '1500',
                        showEasing: 'swing',
                        hideEasing: 'linear',
                        showMethod: 'fadeIn',
                        hideMethod: 'fadeOut'
                    };
                    if (type === 'success') toastr.success(message, title || i18n.success);
                    else if (type === 'error') toastr.error(message, title || i18n.error);
                    else if (type === 'warning') toastr.warning(message, title || i18n.warning);
                    else toastr.info(message, title || i18n.notice);
                    return;
                }

                // Fallback custom toast if toastr is not loaded
                var stack = document.getElementById('lmToastStack');
                if (!stack) return;
                var item = document.createElement('div');
                item.className = 'lm-toast-item lm-toast-item--' + type;
                var iconContent = '<svg style="width:20px;height:20px;stroke-width:2.2;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">' + (svgPaths[type] || svgPaths.info) + '</svg>';

                item.innerHTML = '<div>' + iconContent + '</div><div style="flex:1;"><strong style="display:block; font-size:13.5px; margin-bottom:2px;"></strong><span style="font-size:12.5px;"></span></div>';
                item.querySelector('strong').textContent = title || (type.charAt(0).toUpperCase() + type.slice(1));
                item.querySelector('span').textContent = message;
                stack.appendChild(item);
                setTimeout(function () {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(110%)';
                    item.style.transition = 'all 0.3s ease';
                    setTimeout(function () { item.remove(); }, 350);
                }, 4000);
            },

            success: function (msg, title) { this.toast('success', msg, title); },
            error: function (msg, title) { this.toast('error', msg, title); },
            warning: function (msg, title) { this.toast('warning', msg, title); },
            info: function (msg, title) { this.toast('info', msg, title); }
        };

        window.LoanAlert = LoanAlert;

    })();
</script>

