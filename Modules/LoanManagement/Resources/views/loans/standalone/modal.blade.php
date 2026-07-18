<div class="modal-dialog modal-xl" role="document" style="max-width: 1200px; margin: 10px auto;">
    <div class="modal-content lm-mob-loan" style="border-radius: 0; border: none; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
        <style>
            *, *::before, *::after { box-sizing: border-box; }

            .lm-mob-loan { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
            .lm-mob-loan .modal-body { padding: 0 !important; flex: 1; display: flex; flex-direction: column; overflow: hidden; max-height: calc(100vh - 10px) !important; }

            /* ===== TOP BAR ===== */
            .mob-topbar {
                display: flex; align-items: center; padding: 10px 16px; background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #fff; position: relative; z-index: 5; min-height: 52px;
            }
            .mob-topbar .mob-close {
                width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.15);
                border: none; color: #fff; font-size: 18px; display: flex; align-items: center; justify-content: center;
                cursor: pointer; flex-shrink: 0; -webkit-tap-highlight-color: transparent;
            }
            .mob-topbar .mob-title { flex: 1; text-align: center; font-size: 16px; font-weight: 700; }
            .mob-topbar .mob-action { width: 36px; flex-shrink: 0; }

            /* ===== STEP PROGRESS ===== */
            .mob-progress {
                display: flex; padding: 12px 20px; background: #fff; border-bottom: 1px solid #f0f0f0;
            }
            .mob-progress .mob-step {
                flex: 1; height: 4px; border-radius: 2px; background: #e5e7eb; margin: 0 2px;
                position: relative; transition: background .3s;
            }
            .mob-progress .mob-step.active { background: #2563eb; }
            .mob-progress .mob-step.done { background: #22c55e; }
            .mob-step-dot {
                position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 2px solid #d1d5db;
                display: none; font-size: 8px; color: #94a3b8; align-items: center; justify-content: center;
            }
            .mob-step.active .mob-step-dot { display: flex; border-color: #2563eb; color: #2563eb; }
            .mob-step.done .mob-step-dot { display: flex; border-color: #22c55e; background: #22c55e; color: #fff; }

            .mob-step-labels {
                display: flex; padding: 0 20px 10px; background: #fff;
            }
            .mob-step-labels span {
                flex: 1; text-align: center; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .2px;
            }
            .mob-step-labels span.active { color: #2563eb; }
            .mob-step-labels span.done { color: #22c55e; }

            /* ===== STEP CONTENT ===== */
            .mob-steps-wrap { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; position: relative; }
            .mob-step-panel {
                display: none; padding: 16px; animation: mobSlideIn .25s ease-out;
                min-height: 100%;
            }
            .mob-step-panel.active { display: block; }
            @keyframes mobSlideIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

            .mob-section-title {
                font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;
                letter-spacing: .5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
            }
            .mob-section-title i { font-size: 12px; }
            .mob-collapse-title {
                width: 100%; border: 0; background: transparent; padding: 0 0 10px;
                justify-content: space-between; cursor: pointer; text-align: left;
            }
            .mob-collapse-title span {
                display: inline-flex; align-items: center; gap: 6px;
            }
            .mob-collapse-title .mob-collapse-icon {
                color: #94a3b8; transition: transform .15s;
            }
            .mob-card.is-collapsed .mob-collapse-icon {
                transform: rotate(-90deg);
            }
            .mob-card.is-collapsed .mob-collapsible-body {
                display: none;
            }

            .mob-card {
                background: #fff; border-radius: 12px; padding: 14px; margin-bottom: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,.04);
            }
            .mob-customer-step {
                display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, .72fr);
                gap: 14px; align-items: start;
            }
            .mob-customer-main,
            .mob-customer-side {
                min-width: 0;
            }
            .mob-customer-main {
                display: flex;
                flex-direction: column;
            }
            .mob-customer-step .mob-card {
                margin-bottom: 14px;
            }
            .mob-search-card .mob-field {
                margin-bottom: 0;
            }
            .mob-search-card .select2-container .select2-selection--single {
                min-height: 46px;
                border-color: #dfe5ec;
                border-radius: 10px;
                background: #fbfcfe;
            }
            .mob-search-card .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 44px;
                padding-left: 14px;
                color: #1f2937;
                font-size: 15px;
            }
            .mob-id-card-panel,
            .mob-doc-card {
                border: 1px solid #eef2f7;
                box-shadow: 0 8px 22px rgba(15,23,42,.05);
            }

            .mob-field { margin-bottom: 14px; }
            .mob-field:last-child { margin-bottom: 0; }
            .mob-field label {
                display: block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px;
                text-transform: uppercase; letter-spacing: .2px;
            }
            .mob-field .mob-required { color: #ef4444; }
            .mob-input {
                width: 100%; height: 40px; padding: 0 12px; border: 1px solid #e1e7ef; border-radius: 9px;
                font-size: 14px; background: #fbfcfe; color: #1f2937; transition: all .15s;
                -webkit-appearance: none; appearance: none;
            }
            .mob-input:focus { outline: none; border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
            .mob-input::placeholder { color: #c4c4c4; }
            select.mob-input { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%239ca3af' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
            .mob-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

            /* ===== CUSTOMER CARD ===== */
            .mob-customer-header {
                display: flex; align-items: center; gap: 14px; padding: 16px;
                background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom: 1px solid #bfdbfe;
                border-radius: 12px; margin-bottom: 12px;
            }
            .mob-avatar {
                width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #93c5fd, #60a5fa);
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                font-size: 20px; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.2);
            }
            .mob-avatar img { width: 100%; height: 100%; border-radius: 14px; object-fit: cover; }
            .mob-avatar-empty { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #94a3b8; }

            /* ===== PRODUCT CARDS ===== */
            .mob-product-item {
                background: #fff; border-radius: 16px; padding: 14px; margin-bottom: 12px;
                box-shadow: 0 8px 24px rgba(15,23,42,.06); position: relative; border: 1px solid #e9edf3;
            }
            .mob-product-item .mob-prod-img {
                width: 58px; height: 58px; border-radius: 14px; background: #f1f5f9;
                display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 18px; flex-shrink: 0;
                overflow: hidden; border: 1px solid #e5e7eb;
            }
            .mob-product-item .mob-prod-img img { width: 100%; height: 100%; object-fit: cover; }
            .mob-product-item .mob-prod-num {
                min-width: 34px; height: 26px; padding: 0 9px; border-radius: 999px; background: #eef2ff;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: 12px; font-weight: 800; color: #2563eb;
            }
            .mob-product-item .mob-prod-del {
                width: 36px; height: 36px; border-radius: 50%;
                background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; font-size: 13px;
                display: flex; align-items: center; justify-content: center; cursor: pointer;
            }
            .mob-product-ocr-row {
                display: grid; grid-template-columns: 58px 1fr auto auto; gap: 10px; align-items: center; margin-bottom: 12px;
            }
            .mob-product-actions { display: flex; gap: 8px; flex-wrap: wrap; }
            .mob-product-photo-btn {
                height: 36px; min-width: 154px; padding: 0 12px; border-radius: 999px; border: 1px solid #d9e2ef;
                display: inline-flex; align-items: center; justify-content: center; gap: 6px;
                background: #f8fafc; color: #52647b; cursor: pointer; transition: all .15s;
                font-size: 12px; font-weight: 700; -webkit-tap-highlight-color: transparent;
            }
            .mob-product-photo-btn:active { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
            .mob-product-photo-btn i { font-size: 14px; }
            .mob-product-ocr-status {
                grid-column: 1 / 5; min-height: 0; margin: -2px 0 0; font-size: 12px; color: #64748b;
            }
            .mob-product-ocr-status:not(:empty) {
                display: inline-flex; width: fit-content; max-width: 100%; padding: 6px 10px; border-radius: 999px;
                background: #f1f5f9; color: #52647b; font-weight: 700;
            }
            .mob-product-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .mob-product-fields .mob-field { margin-bottom: 0; }
            .mob-product-fields .wide { grid-column: 1 / -1; }
            .mob-product-fields .mob-field label {
                font-size: 11px; color: #7b8798; margin-bottom: 5px; letter-spacing: .25px;
            }
            .mob-product-fields .mob-input {
                height: 42px; border-radius: 12px; font-size: 15px; background: #fbfcfe; border-color: #dfe5ec;
            }
            .mob-product-fields .wide .mob-input { height: 48px; font-size: 16px; font-weight: 600; }
            .mob-product-bottom {
                display: grid; grid-template-columns: 1fr minmax(96px, auto); gap: 10px; align-items: end;
            }
            .mob-product-total {
                min-height: 42px; padding: 7px 10px; border-radius: 12px; text-align: right; background: #eff6ff;
            }
            .mob-product-total label { display: block; font-size: 11px; font-weight: 700; color: #6b7280; margin-bottom: 3px; }
            .mob-product-total .modal-item-total { font-size: 18px; font-weight: 900; color: #2563eb; line-height: 1.1; }
            .mob-products-principal {
                margin-top: 12px; border-radius: 16px; border: 1px solid #dfe5ec; background: #fff;
                padding: 14px; text-align: center; box-shadow: 0 4px 14px rgba(15,23,42,.04);
            }
            .mob-products-principal .mob-s-label { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .3px; }
            .mob-products-principal .mob-s-value { margin-top: 4px; font-size: 20px; font-weight: 900; color: #2563eb; }
            @media (max-width: 520px) {
                .mob-product-ocr-row { grid-template-columns: 52px 1fr auto; }
                .mob-product-actions { grid-column: 2; gap: 6px; }
                .mob-product-photo-btn { min-width: 0; flex: 1; }
                .mob-prod-del { grid-column: 3; grid-row: 1; }
                .mob-product-ocr-status { grid-column: 1 / 4; margin-top: 0; }
                .mob-product-fields { gap: 9px; }
                .mob-product-bottom { grid-template-columns: 1fr minmax(92px, auto); }
            }
            .mob-product-crop-overlay {
                position: fixed; inset: 0; z-index: 1065; display: none; align-items: center; justify-content: center;
                padding: 14px; background: rgba(15,23,42,.62);
            }
            .mob-product-crop-box {
                width: min(760px, 100%); max-height: calc(100vh - 28px); overflow: auto;
                background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 24px 70px rgba(15,23,42,.28);
            }
            .mob-product-crop-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
            .mob-product-crop-title { font-size: 14px; font-weight: 800; color: #1f2937; }
            .mob-product-crop-canvas {
                width: 100%; max-height: 62vh; border-radius: 10px; border: 1px solid #e5e7eb;
                background: #f8fafc; touch-action: none; cursor: move;
            }
            .mob-product-crop-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; justify-content: flex-end; }
            .mob-product-crop-actions button {
                height: 38px; border-radius: 10px; border: 1px solid #e5e7eb; padding: 0 12px;
                font-size: 12px; font-weight: 700; background: #fff; color: #475569;
            }
            .mob-product-crop-actions .primary { border-color: #2563eb; background: #2563eb; color: #fff; }
            .mob-product-crop-status { min-height: 16px; margin-top: 8px; font-size: 11px; color: #64748b; }
            .mob-add-product {
                display: flex; align-items: center; justify-content: center; gap: 8px;
                padding: 14px; border: 2px dashed #d1d5db; border-radius: 12px;
                background: #fafafa; color: #2563eb; font-weight: 700; font-size: 14px;
                cursor: pointer; transition: all .15s;
            }
            .mob-add-product:active { background: #eff6ff; border-color: #2563eb; }

            /* ===== SUMMARY STRIP ===== */
            .mob-summary {
                display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
                padding: 12px 16px; background: #fff; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;
            }
            .mob-summary-item { text-align: center; }
            .mob-summary-item .mob-s-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .3px; }
            .mob-summary-item .mob-s-value { font-size: 15px; font-weight: 800; color: #1f2937; margin-top: 2px; }
            .mob-summary-item .mob-s-value.green { color: #16a34a; }
            .mob-summary-item .mob-s-value.blue { color: #2563eb; }

            /* ===== BOTTOM BAR ===== */
            .mob-bottombar {
                display: flex; gap: 8px; padding: 10px 16px; background: #fff; border-top: 1px solid #e5e7eb;
                position: relative; z-index: 5;
            }
            .mob-bottombar button {
                flex: 1; height: 48px; border-radius: 12px; font-size: 14px; font-weight: 700;
                border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
                transition: opacity .15s; -webkit-tap-highlight-color: transparent;
            }
            .mob-bottombar button:active { opacity: .85; }
            .mob-btn-primary { background: #2563eb; color: #fff; }
            .mob-btn-success { background: #22c55e; color: #fff; }
            .mob-btn-ghost { background: #f1f5f9; color: #475569; }
            .mob-btn-back { background: #f1f5f9; color: #475569; flex: 0 0 auto; width: 48px; }
            .mob-btn-next { flex: 2; }
            .mob-btn-submit { background: #22c55e; color: #fff; flex: 2; }

            /* ===== DOWN PAYMENT TOGGLE ===== */
            .mob-toggle-row {
                display: flex; align-items: center; gap: 10px; padding: 12px 0; cursor: pointer;
                -webkit-tap-highlight-color: transparent;
            }
            .mob-switch {
                width: 48px; height: 28px; border-radius: 14px; background: #e5e7eb; position: relative; transition: background .2s; flex-shrink: 0;
            }
            .mob-switch::after {
                content: ''; position: absolute; top: 3px; left: 3px; width: 22px; height: 22px;
                border-radius: 50%; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.15); transition: transform .2s;
            }
            .mob-toggle-row.on .mob-switch { background: #2563eb; }
            .mob-toggle-row.on .mob-switch::after { transform: translateX(20px); }
            .mob-toggle-label { font-size: 14px; font-weight: 600; color: #374151; }
            .mob-toggle-sub { font-size: 12px; color: #94a3b8; }
            .mob-deposit-card {
                margin-top: 12px; border-radius: 16px; border: 1px solid #dfe5ec; background: #fff;
                padding: 14px; box-shadow: 0 6px 20px rgba(15,23,42,.05);
            }
            .mob-deposit-card .mob-toggle-row {
                padding: 0; margin-bottom: 12px; align-items: center;
            }
            .mob-deposit-card .mob-switch {
                width: 52px; height: 30px; border-radius: 999px; background: #e2e8f0;
            }
            .mob-deposit-card .mob-switch::after {
                width: 24px; height: 24px; top: 3px; left: 3px;
            }
            .mob-deposit-card .mob-toggle-row.on .mob-switch::after { transform: translateX(22px); }
            .mob-deposit-card .mob-toggle-label { font-size: 15px; font-weight: 800; color: #1f2937; line-height: 1.15; }
            .mob-deposit-card .mob-toggle-sub { margin-top: 2px; font-size: 12px; font-weight: 600; color: #8a98ad; }
            .mob-deposit-fields { display: grid; grid-template-columns: 1.15fr .85fr; gap: 10px; }
            .mob-deposit-fields .mob-field { margin-bottom: 0; }
            .mob-deposit-fields .wide { grid-column: 1 / -1; }
            .mob-deposit-fields .mob-field label {
                font-size: 11px; color: #7b8798; margin-bottom: 5px; letter-spacing: .25px;
            }
            .mob-deposit-fields .mob-input {
                height: 42px; border-radius: 12px; font-size: 15px; background: #fbfcfe; border-color: #dfe5ec;
            }
            .mob-deposit-fields .amount .mob-input {
                height: 48px; font-size: 18px; font-weight: 800; color: #2563eb; background: #eff6ff; border-color: #bfdbfe;
            }
            .mob-payment-row {
                position: relative; padding: 12px; border: 1px solid #e5ebf3; border-radius: 14px; background: #fbfcfe;
                margin-bottom: 10px;
            }
            .mob-payment-row:last-of-type { margin-bottom: 0; }
            .mob-payment-head {
                display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;
            }
            .mob-payment-title { font-size: 12px; font-weight: 800; color: #52647b; }
            .mob-payment-remove {
                width: 30px; height: 30px; border-radius: 50%; border: 1px solid #fecaca; background: #fff5f5;
                color: #ef4444; display: inline-flex; align-items: center; justify-content: center;
            }
            .mob-add-payment {
                height: 40px; width: 100%; border-radius: 12px; border: 1.5px dashed #cbd5e1;
                background: #f8fafc; color: #2563eb; font-size: 13px; font-weight: 800;
                display: flex; align-items: center; justify-content: center; gap: 7px;
            }
            @media (max-width: 520px) {
                .mob-deposit-fields { grid-template-columns: 1fr 1fr; gap: 9px; }
                .mob-deposit-fields .amount { grid-column: 1 / -1; }
            }

            /* ===== REVIEW STEP ===== */
            .mob-review-row {
                display: flex; justify-content: space-between; align-items: center; padding: 10px 0;
                border-bottom: 1px solid #f5f5f5;
            }
            .mob-review-row:last-child { border-bottom: 0; }
            .mob-review-label { font-size: 13px; color: #6b7280; }
            .mob-review-value { font-size: 14px; font-weight: 600; color: #1f2937; text-align: right; max-width: 60%; }

            /* ===== SCHEDULE ===== */
            .mob-schedule-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; border: 1px solid #e5e7eb; }
            .mob-schedule-tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 380px; }
            .mob-schedule-tbl th { background: #f9fafb; padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
            .mob-schedule-tbl td { padding: 8px 10px; border-bottom: 1px solid #f5f5f5; }
            .mob-schedule-tbl tfoot th { background: #eff6ff; border-top: 2px solid #2563eb; font-size: 12px; }

            /* ===== ID CARD / DOCUMENT UPLOAD ===== */
            .mob-upload-area {
                display: flex; gap: 8px; margin-top: 6px;
            }
            .mob-customer-photo-strip {
                display: grid; grid-template-columns: 1fr 1fr; gap: 8px; align-items: stretch; margin-bottom: 10px;
            }
            .mob-mini-photo-panel {
                display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center;
                border: 1px solid #e8edf5; border-radius: 10px; padding: 8px; background: #fbfcfe;
            }
            .mob-mini-photo-panel .mob-section-title {
                margin-bottom: 6px; font-size: 10px; line-height: 1.15;
            }
            .mob-mini-upload-area {
                display: block;
            }
            .mob-mini-upload-btn {
                width: 100%; min-height: 30px; padding: 5px 8px; border-radius: 9px;
                border: 1px dashed #cbd5e1; background: #fff; color: #52647b;
                display: inline-flex; align-items: center; justify-content: center; gap: 5px;
                font-size: 10px; font-weight: 800; cursor: pointer;
            }
            .mob-mini-upload-btn:active { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
            .mob-profile-preview {
                position: relative; width: 54px; height: 54px; margin: 0; border-radius: 50%;
                overflow: hidden; display: flex; align-items: center; justify-content: center;
                background: #eef2f7; color: #94a3b8; border: 1px solid #dbe3ef; font-size: 20px;
            }
            .mob-profile-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .mob-profile-preview .mob-id-remove {
                position: absolute; top: 2px; right: 2px; width: 22px; height: 22px; border-radius: 50%;
                background: rgba(239,68,68,.92); border: none; color: #fff; font-size: 10px;
                display: none; align-items: center; justify-content: center; cursor: pointer;
            }
            .mob-profile-preview.has-image .mob-id-remove { display: flex; }
            .mob-customer-detail-fields {
                display: none;
                animation: mobFadeUp .18s ease-out;
            }
            .mob-customer-detail-fields.is-visible {
                display: block;
            }
            @keyframes mobFadeUp {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .mob-photo-sheet {
                position: fixed; inset: 0; z-index: 1070; display: none;
                align-items: flex-end; background: rgba(15,23,42,.45);
            }
            .mob-photo-sheet.is-open { display: flex; }
            .mob-photo-sheet-panel {
                width: 100%; background: #fff; border-radius: 18px 18px 0 0; padding: 14px;
                box-shadow: 0 -18px 50px rgba(15,23,42,.18);
            }
            .mob-photo-sheet-title {
                font-size: 13px; font-weight: 800; color: #1f2937; margin-bottom: 10px;
            }
            .mob-photo-sheet-option {
                width: 100%; min-height: 44px; border: 1px solid #e8edf5; border-radius: 12px;
                background: #fbfcfe; color: #334155; display: flex; align-items: center; gap: 10px;
                padding: 0 12px; margin-bottom: 8px; font-size: 13px; font-weight: 800;
            }
            .mob-photo-sheet-option i { width: 20px; text-align: center; color: #2563eb; }
            .mob-photo-sheet-cancel {
                width: 100%; min-height: 42px; border: 0; border-radius: 12px;
                background: #f1f5f9; color: #64748b; font-size: 13px; font-weight: 800;
            }
            .mob-upload-btn {
                flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
                min-height: 42px; padding: 10px 12px; border: 1.5px dashed #cbd5e1; border-radius: 12px;
                background: #fbfcfe; color: #475569; font-size: 12px; font-weight: 800;
                cursor: pointer; transition: all .15s; -webkit-tap-highlight-color: transparent;
            }
            .mob-upload-btn:active { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
            .mob-upload-btn i { font-size: 16px; }
            .mob-id-preview {
                position: relative; width: 112px; min-height: 64px; border-radius: 10px; overflow: hidden;
                margin-top: 0; display: none; background: #f1f5f9; border: 1px solid #e5e7eb;
            }
            .mob-id-preview img { width: 112px; height: 64px; object-fit: cover; display: block; }
            .mob-id-preview .mob-id-remove {
                position: absolute; top: 3px; right: 3px; width: 20px; height: 20px; border-radius: 50%;
                background: rgba(239,68,68,.9); border: none; color: #fff; font-size: 9px;
                display: flex; align-items: center; justify-content: center; cursor: pointer;
            }
            .mob-doc-grid {
                display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 124px));
                gap: 10px; margin-top: 8px; align-items: start;
            }
            .mob-doc-thumb {
                position: relative; width: 100%; aspect-ratio: 1; border-radius: 12px; overflow: hidden;
                background: #f1f5f9; border: 1px solid #e5e7eb;
            }
            .mob-doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
            .mob-doc-thumb .mob-doc-icon { text-align: center; color: #64748b; padding: 8px; }
            .mob-doc-thumb .mob-doc-icon i { font-size: 28px; display: block; margin-bottom: 4px; }
            .mob-doc-thumb .mob-doc-icon span { font-size: 9px; word-break: break-all; display: block; }
            .mob-doc-thumb .mob-doc-remove {
                position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%;
                background: rgba(239,68,68,.9); border: none; color: #fff; font-size: 10px;
                display: flex; align-items: center; justify-content: center; cursor: pointer;
            }
            .mob-doc-thumb .mob-doc-badge {
                position: absolute; bottom: 4px; left: 4px; padding: 2px 6px; border-radius: 4px;
                background: rgba(0,0,0,.6); color: #fff; font-size: 8px; font-weight: 600;
            }
            .mob-doc-add {
                width: 100%; height: 112px; border-radius: 12px; border: 2px dashed #cbd5e1;
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                gap: 6px; background: #fbfcfe; color: #8a98ad; cursor: pointer; transition: all .15s;
                font-size: 11px; font-weight: 800; -webkit-tap-highlight-color: transparent;
            }
            .mob-doc-add:active { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
            .mob-doc-add i { font-size: 20px; }
            .mob-compressing-overlay {
                position: absolute; inset: 0; background: rgba(255,255,255,.8);
                display: flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 700; color: #2563eb;
            }

            /* ===== MOBILE ONLY ===== */
            @media (max-width: 767px) {
                .mob-customer-step { display: block; }
                .mob-customer-photo-strip { grid-template-columns: 1fr 1fr; }
                .mob-customer-main .mob-card { margin-bottom: 10px; }
                .mob-doc-grid { grid-template-columns: repeat(3, 1fr); }
                .mob-doc-add { height: auto; aspect-ratio: 1; }
                .lm-mob-loan .modal-dialog { margin: 0 !important; max-width: 100% !important; width: 100% !important; height: 100vh; display: flex; }
                .lm-mob-loan { height: 100vh; }
                .lm-mob-loan .modal-body { max-height: none !important; flex: 1; overflow: hidden; display: flex; flex-direction: column; }
                .mob-bottombar { padding-bottom: max(10px, env(safe-area-inset-bottom)); }
            }
            @media (max-width: 380px) {
                .mob-customer-photo-strip { grid-template-columns: 1fr; }
            }
        </style>

        <div class="mob-topbar">
            <button type="button" class="mob-close" data-dismiss="modal" aria-label="Close">&times;</button>
            <div class="mob-title">Create Loan</div>
            <div class="mob-action"></div>
        </div>

        <div class="mob-progress" id="mobProgress">
            <div class="mob-step active" data-step="0"><div class="mob-step-dot">1</div></div>
            <div class="mob-step" data-step="1"><div class="mob-step-dot">2</div></div>
            <div class="mob-step" data-step="2"><div class="mob-step-dot">3</div></div>
            <div class="mob-step" data-step="3"><div class="mob-step-dot">4</div></div>
        </div>
        <div class="mob-step-labels" id="mobStepLabels">
            <span class="active">Invoice</span>
            <span>Customer</span>
            <span>Products</span>
            <span>Review</span>
        </div>

        <form id="standaloneLoanModalForm" method="POST" action="{{ route('loan-management.loans.store-standalone') }}" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
            @csrf
            <input type="hidden" name="action_type" value="create_approve">

            <div class="mob-steps-wrap" id="mobStepsWrap">

                {{-- ========== STEP 0: INVOICE ========== --}}
                <div class="mob-step-panel active" data-panel="0">
                    <div class="mob-card">
                        <div class="mob-section-title"><i class="fa fa-file-invoice"></i> Invoice Details</div>
                        <div class="mob-grid-2">
                            <div class="mob-field">
                                <label>Loan #</label>
                                <input type="text" name="loan_number" class="mob-input" placeholder="Auto">
                            </div>
                            <div class="mob-field">
                                <label>Date <span class="mob-required">*</span></label>
                                <input type="date" name="loan_date" class="mob-input" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="mob-field">
                            <label>Location</label>
                            <select name="business_location_id" class="mob-input">
                                <option value="">-- Select --</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" {{ (string) $id === (string) ($defaultLocationId ?? '') ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mob-field">
                            <label>Collector</label>
                            <select name="assigned_collector_id" class="mob-input">
                                <option value="">-- None --</option>
                                @foreach($collectors as $c)
                                    <option value="{{ $c->id }}" {{ (string) $c->id === (string) ($defaultCollectorId ?? '') ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="currency" value="USD">
                        <input type="hidden" name="exchange_rate" value="1">
                        <div class="mob-field">
                            <label>Note</label>
                            <input name="note" class="mob-input" placeholder="Optional">
                        </div>
                    </div>
                </div>

                {{-- ========== STEP 1: CUSTOMER ========== --}}
                <div class="mob-step-panel" data-panel="1">
                    <div class="mob-customer-step">
                        <div class="mob-customer-main">
                            <div class="mob-card" id="mobCustomerInfoCard">
                                <input type="hidden" name="customer_id" id="modalCustomerId" value="">
                                <button type="button" class="mob-section-title mob-collapse-title" onclick="mobToggleCustomerInfo()" aria-expanded="true" aria-controls="mobCustomerInfoBody">
                                    <span><i class="fa fa-user-edit"></i> Customer</span>
                                    <i class="fa fa-chevron-down mob-collapse-icon"></i>
                                </button>
                                <div class="mob-collapsible-body" id="mobCustomerInfoBody">
                                    <div class="mob-customer-photo-strip">
                                        <div class="mob-mini-photo-panel">
                                            <div>
                                                <div class="mob-section-title"><i class="fa fa-user-circle"></i> Profile Photo</div>
                                                <div class="mob-mini-upload-area">
                                                    <button type="button" class="mob-mini-upload-btn" onclick="mobOpenPhotoSheet('profile')">
                                                        <i class="fa fa-plus-circle"></i> Add Photo
                                                    </button>
                                                    <input type="file" id="mobCustomerPhotoCamera" accept="image/*" capture="user" style="display:none;" onchange="mobHandleCustomerProfile(this)">
                                                    <input type="file" id="mobCustomerPhotoGallery" accept="image/*" style="display:none;" onchange="mobHandleCustomerProfile(this)">
                                                </div>
                                            </div>
                                            <div class="mob-profile-preview" id="mobCustomerPhotoPreview">
                                                <i class="fa fa-user"></i>
                                                <button type="button" class="mob-id-remove" onclick="mobRemoveCustomerProfile()"><i class="fa fa-times"></i></button>
                                            </div>
                                        </div>
                                        <div class="mob-mini-photo-panel">
                                            <div>
                                                <div class="mob-section-title"><i class="fa fa-id-card"></i> ID Card Photo</div>
                                                <div class="mob-mini-upload-area">
                                                    <button type="button" class="mob-mini-upload-btn" onclick="mobOpenPhotoSheet('id_card')">
                                                        <i class="fa fa-plus-circle"></i> Add Photo
                                                    </button>
                                                    <input type="file" id="mobIdCardCamera" accept="image/*" capture="environment" style="display:none;" onchange="mobHandleIdCard(this)">
                                                    <input type="file" id="mobIdCardGallery" accept="image/*" style="display:none;" onchange="mobHandleIdCard(this)">
                                                </div>
                                            </div>
                                            <div class="mob-id-preview" id="mobIdCardPreview">
                                                <img id="mobIdCardImg" src="">
                                                <button type="button" class="mob-id-remove" onclick="mobRemoveIdCard()"><i class="fa fa-times"></i></button>
                                            </div>
                                            <input type="hidden" name="id_card_ocr_raw_text" id="mobIdCardOcrRawText">
                                            <input type="hidden" name="id_card_ocr_fields[id_card_number]" id="mobIdCardOcrNumber">
                                            <input type="hidden" name="id_card_ocr_fields[khmer_name]" id="mobIdCardOcrKhmerName">
                                            <input type="hidden" name="id_card_ocr_fields[english_name]" id="mobIdCardOcrEnglishName">
                                            <input type="hidden" name="id_card_ocr_fields[address]" id="mobIdCardOcrAddress">
                                            <div id="mobIdCardOcrStatus" style="margin-top:6px; font-size:11px; color:#64748b;"></div>
                                        </div>
                                    </div>
                                    <div class="mob-customer-detail-fields" id="mobCustomerDetailFields">
                                        <div class="mob-grid-2">
                                            <div class="mob-field">
                                                <label>Name in Khmer <span class="mob-required">*</span></label>
                                                <input type="text" name="customer_khmer_name" id="modalCustomerKhmerName" class="mob-input" required placeholder="Khmer name">
                                            </div>
                                            <div class="mob-field">
                                                <label>Name in English <span class="mob-required">*</span></label>
                                                <input type="text" name="customer_english_name" id="modalCustomerEnglishName" class="mob-input" required placeholder="English name">
                                                <input type="hidden" name="customer_name" id="modalCustomerName">
                                            </div>
                                        </div>
                                        <div class="mob-grid-2">
                                            <div class="mob-field">
                                                <label>Phone</label>
                                                <div style="display:flex; gap:6px;">
                                                    <input type="text" name="customer_phone" id="modalCustomerPhone" class="mob-input" placeholder="Phone" style="flex:1;">
                                                    <button type="button" class="mob-product-photo-btn" id="modalBtnShowAlternatePhone" style="min-width:42px; padding:0;" title="Add alternate phone">
                                                        <i class="fa fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mob-field">
                                                <label>ID Card #</label>
                                                <input type="text" name="id_card_number" id="modalCustomerIdCard" class="mob-input" placeholder="ID Card">
                                            </div>
                                        </div>
                                        <div class="mob-field" id="modalAlternatePhoneGroup" style="display:none;">
                                            <label>Alternate Phone</label>
                                            <input type="text" name="alternate_phone" id="modalAlternatePhone" class="mob-input" placeholder="Alternate phone">
                                        </div>
                                        <div class="mob-field" style="display:none;">
                                            <label>ID Card Address</label>
                                            <input type="hidden" name="customer_address" id="modalCustomerAddress" class="mob-input" placeholder="ID card address">
                                        </div>
                                        <div class="mob-grid-2">
                                            <div class="mob-field">
                                                <label>Province</label>
                                                <select name="province_code" id="modalProvinceSelect" class="mob-input">
                                                    <option value="">-- Select --</option>
                                                </select>
                                                <input type="hidden" name="province_name" id="modalProvinceName">
                                            </div>
                                            <div class="mob-field">
                                                <label>District</label>
                                                <select name="district_code" id="modalDistrictSelect" class="mob-input" disabled>
                                                    <option value="">-- Select --</option>
                                                </select>
                                                <input type="hidden" name="district_name" id="modalDistrictName">
                                            </div>
                                            <div class="mob-field">
                                                <label>Commune</label>
                                                <select name="commune_code" id="modalCommuneSelect" class="mob-input" disabled>
                                                    <option value="">-- Select --</option>
                                                </select>
                                                <input type="hidden" name="commune_name" id="modalCommuneName">
                                            </div>
                                            <div class="mob-field">
                                                <label>Village</label>
                                                <select name="village_code" id="modalVillageSelect" class="mob-input" disabled>
                                                    <option value="">-- Select --</option>
                                                </select>
                                                <input type="hidden" name="village_name" id="modalVillageName">
                                            </div>
                                        </div>
                                        <div id="modalAddressLoadStatus" style="margin-top:6px; font-size:11px; color:#64748b;"></div>
                                        <div class="mob-field">
                                            <label>Group</label>
                                            <input name="customer_group_name" class="mob-input" value="រំលស់">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mob-customer-side">
                            <div class="mob-card mob-doc-card">
                                <div class="mob-section-title"><i class="fa fa-paperclip"></i> Documents</div>
                                <div class="mob-doc-grid" id="mobDocGrid">
                                    <label class="mob-doc-add" for="mobDocInput">
                                        <i class="fa fa-plus-circle"></i> Add File
                                    </label>
                                </div>
                                <input type="file" id="mobDocInput" accept="image/*,.pdf,.txt,.csv,.doc,.docx" multiple style="display:none;" onchange="mobHandleDocs(this)">
                                <textarea name="document_text" class="mob-input" rows="3" placeholder="Write document note or extra information to send with Telegram" style="margin-top:8px;"></textarea>
                                <div id="mobDocumentLinks" style="margin-top:8px;">
                                    <div class="mob-doc-link-row" style="display:flex; gap:6px; margin-bottom:6px;">
                                        <input type="url" name="document_links[]" class="mob-input" placeholder="Paste document link">
                                        <button type="button" class="btn btn-default btn-sm" id="mobAddDocumentLink" title="Add another link">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div style="margin-top:8px; font-size:10px; color:#94a3b8;">
                                    <i class="fa fa-clipboard"></i> Paste images with Ctrl+V &middot; Photos compressed, files kept as-is
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ========== STEP 2: PRODUCTS ========== --}}
                <div class="mob-step-panel" data-panel="2">
                    <div id="mobProductList"></div>
                    <button type="button" class="mob-add-product" id="modalBtnAddItem">
                        <i class="fa fa-plus-circle"></i> Add Product
                    </button>

                    <div class="mob-products-principal">
                        <div class="mob-s-label">Principal After Deposit</div>
                        <div class="mob-s-value" id="modalComputedPrincipal">$0.00</div>
                    </div>

                    <div class="mob-deposit-card">
                        <div class="mob-toggle-row" id="mobDpToggle" onclick="this.classList.toggle('on'); document.getElementById('mobDpFields').style.display = this.classList.contains('on') ? 'block' : 'none';">
                            <div class="mob-switch"></div>
                            <div>
                                <div class="mob-toggle-label">ចូលរួមកក់ខ្លះ</div>
                                <div class="mob-toggle-sub">Customer deposit payment</div>
                            </div>
                        </div>
                        <div id="mobDpFields" style="display: none;">
                            <input type="hidden" id="modalDownPaymentHidden" name="down_payment" value="0">
                            <div id="mobDepositPayments">
                                <div class="mob-payment-row" data-payment-index="0">
                                    <div class="mob-payment-head">
                                        <div class="mob-payment-title">Payment #1</div>
                                        <button type="button" class="mob-payment-remove modal-btn-remove-payment" style="display:none;"><i class="fa fa-trash"></i></button>
                                    </div>
                                    <div class="mob-deposit-fields">
                                        <div class="mob-field amount">
                                            <label>Amount</label>
                                            <input type="number" step="0.01" name="payments[0][amount]" class="mob-input modal-payment-amount" value="0" min="0">
                                        </div>
                                        <div class="mob-field">
                                            <label>Date</label>
                                            <input type="date" name="payments[0][paid_date]" class="mob-input" value="{{ date('Y-m-d') }}">
                                        </div>
                                        <div class="mob-field">
                                            <label>Method</label>
                                            {!! Form::select('payments[0][method]', $paymentTypes ?? [], $defaultPaymentMethod ?? 'cash', ['class' => 'mob-input modal-payment-method']) !!}
                                        </div>
                                        <div class="mob-field">
                                            <label>Ref #</label>
                                            <input name="payments[0][reference_number]" class="mob-input" placeholder="Ref #">
                                        </div>
                                        <input type="hidden" name="payments[0][currency]" value="USD">
                                        <input type="hidden" name="payments[0][exchange_rate]" value="1">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="mob-add-payment" id="modalBtnAddPayment"><i class="fa fa-plus-circle"></i> Add Payment</button>
                        </div>
                    </div>

                    <div class="mob-card" style="margin-top: 12px;">
                        <div class="mob-section-title"><i class="fa fa-sliders-h"></i> Loan Conditions</div>
                        <div class="mob-field">
                            <label>Principal After Deposit <span class="mob-required">*</span></label>
                            <input type="number" step="0.01" id="modalPrincipalAmount" name="principal_amount" class="mob-input" min="0.01" required placeholder="Auto" readonly>
                        </div>
                        <div class="mob-grid-2">
                            <div class="mob-field">
                                <label>Interest %</label>
                                <input type="number" step="0.01" name="interest_rate" class="mob-input" value="{{ old('interest_rate', 4) }}" min="0">
                            </div>
                            <div class="mob-field">
                                <label>Interest Type <span class="mob-required">*</span></label>
                                <select name="interest_type" class="mob-input">
                                    <option value="flat">Flat</option>
                                    <option value="reducing_balance">Reducing</option>
                                </select>
                            </div>
                        </div>
                        <div class="mob-grid-2">
                            <div class="mob-field">
                                <label>Duration (M) <span class="mob-required">*</span></label>
                                <input type="number" name="duration_months" class="mob-input" min="1" max="360" value="12" required>
                            </div>
                            <div class="mob-field">
                                <label>Frequency <span class="mob-required">*</span></label>
                                <select name="payment_frequency" class="mob-input">
                                    <option value="monthly">Monthly</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="daily">Daily</option>
                                </select>
                            </div>
                        </div>
                        <div class="mob-grid-2">
                            <div class="mob-field">
                                <label>First Due <span class="mob-required">*</span></label>
                                <input type="date" name="first_due_date" class="mob-input" value="{{ \Carbon\Carbon::today()->addMonth()->format('Y-m-d') }}">
                            </div>
                            <div class="mob-field">
                                <label>Penalty</label>
                                <select name="penalty_type" class="mob-input">
                                    <option value="fixed">Fixed</option>
                                    <option value="percentage">Percent</option>
                                </select>
                            </div>
                        </div>
                        <div class="mob-field">
                            <label>Penalty Amount</label>
                            <input type="number" step="0.01" name="penalty_amount" class="mob-input" value="0" min="0">
                        </div>
                    </div>

                </div>

                {{-- ========== STEP 3: REVIEW ========== --}}
                <div class="mob-step-panel" data-panel="3">
                    <div class="mob-card">
                        <div class="mob-section-title"><i class="fa fa-receipt"></i> Summary</div>
                        <div class="mob-review-row"><span class="mob-review-label">Loan Date</span><span class="mob-review-value" id="mobRevDate">{{ date('Y-m-d') }}</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Location</span><span class="mob-review-value" id="mobRevLocation">-</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Collector</span><span class="mob-review-value" id="mobRevCollector">-</span></div>
                    </div>
                    <div class="mob-card">
                        <div class="mob-section-title"><i class="fa fa-user"></i> Customer</div>
                        <div class="mob-review-row"><span class="mob-review-label">Name</span><span class="mob-review-value" id="mobRevCustName">-</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Phone</span><span class="mob-review-value" id="mobRevCustPhone">-</span></div>
                    </div>
                    <div class="mob-card">
                        <div class="mob-section-title"><i class="fa fa-shopping-bag"></i> Products</div>
                        <div id="mobRevProducts" style="font-size:13px; color:#6b7280;">No products added</div>
                    </div>
                    <div class="mob-card">
                        <div class="mob-section-title"><i class="fa fa-calculator"></i> Loan Terms</div>
                        <div class="mob-review-row"><span class="mob-review-label">Principal</span><span class="mob-review-value" id="mobRevPrincipal">-</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Interest</span><span class="mob-review-value" id="mobRevInterest">-</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Duration</span><span class="mob-review-value" id="mobRevDuration">-</span></div>
                        <div class="mob-review-row"><span class="mob-review-label">Customer Deposit</span><span class="mob-review-value" id="mobRevDownPayment">-</span></div>
                    </div>

                    <div class="mob-summary" style="margin-top: 12px; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div class="mob-summary-item">
                            <div class="mob-s-label">Total</div>
                            <div class="mob-s-value" id="modalSummaryTotal">$0.00</div>
                        </div>
                        <div class="mob-summary-item">
                            <div class="mob-s-label">Deposit</div>
                            <div class="mob-s-value" id="modalSummaryDownPayment">$0.00</div>
                        </div>
                        <div class="mob-summary-item">
                            <div class="mob-s-label">Due</div>
                            <div class="mob-s-value green" id="modalSummaryDue">$0.00</div>
                        </div>
                        <div class="mob-summary-item">
                            <div class="mob-s-label">Monthly</div>
                            <div class="mob-s-value blue" id="modalSummaryMonthly">$0.00</div>
                        </div>
                    </div>

                    <div class="mob-card" id="modalScheduleSection" style="display: none;">
                        <div class="mob-section-title"><i class="fa fa-calendar"></i> Payment Schedule</div>
                        <div class="mob-schedule-wrap">
                            <table class="mob-schedule-tbl" id="modalScheduleTable">
                                <thead><tr><th>#</th><th>Date</th><th class="text-right">Principal</th><th class="text-right">Interest</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                                <tbody></tbody>
                                <tfoot><tr><th colspan="2" class="text-right">Total</th><th class="text-right">0.00</th><th class="text-right">0.00</th><th class="text-right">0.00</th><th class="text-right">0.00</th></tr></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mob-bottombar" id="mobBottombar">
                <button type="button" class="mob-btn-back" id="mobBtnBack" style="display:none;" onclick="mobGoStep(mobCurrentStep - 1)">
                    <i class="fa fa-arrow-left"></i>
                </button>
                <button type="button" class="mob-btn-ghost" id="mobBtnPreviewSchedule" style="display:none;" onclick="mobPreviewSchedule()">
                    <i class="fa fa-table"></i> Schedule
                </button>
                <button type="button" class="mob-btn-primary mob-btn-next" id="mobBtnNext" onclick="mobGoStep(mobCurrentStep + 1)">
                    Next <i class="fa fa-arrow-right"></i>
                </button>
                <button type="button" class="mob-btn-submit" id="mobBtnSubmit" style="display:none;" onclick="mobSubmit('create_approve')">
                    <i class="fa fa-check"></i> Create Loan
                </button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="contactModalLabel">
    @include('contact.create', ['quick_add' => true, 'selected_type' => 'customer'])
</div>
<div class="mob-photo-sheet" id="mobPhotoSheet" aria-hidden="true" onclick="mobClosePhotoSheet()">
    <div class="mob-photo-sheet-panel" onclick="event.stopPropagation()">
        <div class="mob-photo-sheet-title" id="mobPhotoSheetTitle">Add Photo</div>
        <button type="button" class="mob-photo-sheet-option" onclick="mobChoosePhotoSource('camera')">
            <i class="fa fa-camera"></i> Take Photo
        </button>
        <button type="button" class="mob-photo-sheet-option" onclick="mobChoosePhotoSource('library')">
            <i class="fa fa-image"></i> Choose from Library
        </button>
        <button type="button" class="mob-photo-sheet-cancel" onclick="mobClosePhotoSheet()">Cancel</button>
    </div>
</div>
<div class="mob-product-crop-overlay" id="mobProductCropOverlay" aria-hidden="true">
    <div class="mob-product-crop-box">
        <div class="mob-product-crop-head">
            <div class="mob-product-crop-title"><i class="fa fa-crop"></i> Crop Product Photo</div>
            <button type="button" class="mob-prod-del" onclick="mobCancelProductCrop()" style="position:static;">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <canvas class="mob-product-crop-canvas" id="mobProductCropCanvas"></canvas>
        <div class="mob-product-crop-status" id="mobProductCropStatus">Drag the box or corners to keep only the product label.</div>
        <div class="mob-product-crop-actions">
            <button type="button" onclick="mobResetProductCrop()"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" onclick="mobUseOriginalProductPhoto()"><i class="fa fa-image"></i> Original</button>
            <button type="button" class="primary" onclick="mobUseCroppedProductPhoto()"><i class="fa fa-check"></i> Use Cropped Photo</button>
        </div>
    </div>
</div>
<div class="mob-product-crop-overlay" id="mobIdCardCropOverlay" aria-hidden="true">
    <div class="mob-product-crop-box">
        <div class="mob-product-crop-head">
            <div class="mob-product-crop-title"><i class="fa fa-crop"></i> Crop ID Card Photo</div>
            <button type="button" class="mob-prod-del" onclick="mobCancelIdCardCrop()" style="position:static;">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <canvas class="mob-product-crop-canvas" id="mobIdCardCropCanvas"></canvas>
        <div class="mob-product-crop-status" id="mobIdCardCropStatus">Drag the box or corners to keep only the ID card.</div>
        <div class="mob-product-crop-actions">
            <button type="button" onclick="mobResetIdCardCrop()"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" onclick="mobUseOriginalIdCardPhoto()"><i class="fa fa-image"></i> Original</button>
            <button type="button" class="primary" onclick="mobUseCroppedIdCardPhoto()"><i class="fa fa-check"></i> Use Cropped Photo</button>
        </div>
    </div>
</div>
<div class="mob-product-crop-overlay" id="mobProfileCropOverlay" aria-hidden="true">
    <div class="mob-product-crop-box">
        <div class="mob-product-crop-head">
            <div class="mob-product-crop-title"><i class="fa fa-crop"></i> Crop Profile Photo</div>
            <button type="button" class="mob-prod-del" onclick="mobCancelProfileCrop()" style="position:static;">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <canvas class="mob-product-crop-canvas" id="mobProfileCropCanvas"></canvas>
        <div class="mob-product-crop-status" id="mobProfileCropStatus">Drag the box or corners to keep the customer's face centered.</div>
        <div class="mob-product-crop-actions">
            <button type="button" onclick="mobResetProfileCrop()"><i class="fa fa-refresh"></i> Reset</button>
            <button type="button" onclick="mobUseOriginalProfilePhoto()"><i class="fa fa-image"></i> Original</button>
            <button type="button" class="primary" onclick="mobUseCroppedProfilePhoto()"><i class="fa fa-check"></i> Use Cropped Photo</button>
        </div>
    </div>
</div>

<script>
var mobCurrentStep = 0;
var mobTotalSteps = 4;

function mobGoStep(step) {
    if (step < 0 || step >= mobTotalSteps) return;
    mobCurrentStep = step;

    document.querySelectorAll('.mob-step-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelector('.mob-step-panel[data-panel="'+step+'"]').classList.add('active');

    document.querySelectorAll('.mob-progress .mob-step').forEach(function(s, i) {
        s.classList.remove('active', 'done');
        if (i < step) s.classList.add('done');
        if (i === step) s.classList.add('active');
    });
    document.querySelectorAll('.mob-step-labels span').forEach(function(s, i) {
        s.classList.remove('active', 'done');
        if (i < step) s.classList.add('done');
        if (i === step) s.classList.add('active');
    });

    document.getElementById('mobBtnBack').style.display = step > 0 ? '' : 'none';
    document.getElementById('mobBtnNext').style.display = step < mobTotalSteps - 1 ? '' : 'none';
    var isLast = step === mobTotalSteps - 1;
    document.getElementById('mobBtnSubmit').style.display = isLast ? '' : 'none';
    document.getElementById('mobBtnPreviewSchedule').style.display = isLast ? '' : 'none';

    document.getElementById('mobStepsWrap').scrollTop = 0;

    if (isLast) mobPopulateReview();
}

function mobPopulateReview() {
    var $w = document.getElementById('standaloneLoanModalForm');
    var getVal = function(sel) { var el = $w.querySelector(sel); return el ? el.value : ''; };
    var getText = function(sel) { var el = $w.querySelector(sel); return el ? (el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text : el.value) : ''; };

    document.getElementById('mobRevDate').textContent = getVal('input[name="loan_date"]') || '-';
    document.getElementById('mobRevLocation').textContent = getText('select[name="business_location_id"]') || '-';
    document.getElementById('mobRevCollector').textContent = getText('select[name="assigned_collector_id"]') || '-';
    document.getElementById('mobRevCustName').textContent = getVal('#modalCustomerKhmerName') || getVal('#modalCustomerEnglishName') || '-';
    document.getElementById('mobRevCustPhone').textContent = getVal('#modalCustomerPhone') || '-';

    var principal = parseFloat(getVal('#modalPrincipalAmount')) || 0;
    var rate = parseFloat(getVal('[name="interest_rate"]')) || 0;
    var dur = parseInt(getVal('input[name="duration_months"]')) || 0;
    var freq = getText('select[name="payment_frequency"]');
    var dp = 0;
    $w.querySelectorAll('.modal-payment-amount').forEach(function(input) {
        dp += parseFloat(input.value || 0) || 0;
    });

    document.getElementById('mobRevPrincipal').textContent = '$' + principal.toFixed(2);
    document.getElementById('mobRevInterest').textContent = rate + '%';
    document.getElementById('mobRevDuration').textContent = dur + ' months / ' + freq;
    document.getElementById('mobRevDownPayment').textContent = '$' + dp.toFixed(2);

    var cards = document.querySelectorAll('#mobProductList .mob-product-item');
    var html = '';
    cards.forEach(function(card, i) {
        var name = card.querySelector('.mob-field input[name*="product_name"]');
        var price = card.querySelector('.mob-field input[name*="unit_price"]');
        var qty = card.querySelector('.mob-field input[name*="qty"]');
        html += '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f5f5f5;">' +
            '<span>#' + (i+1) + ' ' + (name ? name.value || 'Unnamed' : 'Unnamed') + '</span>' +
            '<span style="font-weight:600;">$' + ((parseFloat(qty?qty.value:1)||1) * (parseFloat(price?price.value:0)||0)).toFixed(2) + '</span></div>';
    });
    document.getElementById('mobRevProducts').innerHTML = html || '<div style="color:#94a3b8; font-style:italic;">No products added</div>';
}

function mobCompressImage(file, maxW, maxH, quality) {
    return new Promise(function(resolve) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var w = img.width, h = img.height;
                if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                resolve(canvas.toDataURL('image/jpeg', quality));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

var mobIdCardData = '';
var mobCustomerProfileData = '';
var mobPhotoTarget = '';
var mobPhotoTargetCard = null;
var mobProfileCropper = null;
var mobProfileCropFile = null;
var mobIdCardCropper = null;
var mobIdCardCropFile = null;
function mobRevealCustomerDetails() {
    var fields = document.getElementById('mobCustomerDetailFields');
    if (fields) {
        fields.classList.add('is-visible');
    }
}
function mobHideCustomerDetails() {
    var fields = document.getElementById('mobCustomerDetailFields');
    if (fields) {
        fields.classList.remove('is-visible');
    }
}
function mobOpenPhotoSheet(target) {
    mobPhotoTarget = target;
    mobPhotoTargetCard = null;
    var sheet = document.getElementById('mobPhotoSheet');
    var title = document.getElementById('mobPhotoSheetTitle');
    if (title) {
        title.textContent = target === 'profile' ? 'Add Profile Photo' : 'Add ID Card Photo';
    }
    if (sheet) {
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
    }
}
function mobOpenProductPhotoSheet(button) {
    mobPhotoTarget = 'product';
    mobPhotoTargetCard = button ? button.closest('.mob-product-item') : null;
    var sheet = document.getElementById('mobPhotoSheet');
    var title = document.getElementById('mobPhotoSheetTitle');
    if (title) {
        title.textContent = 'Take or Upload Product';
    }
    if (sheet) {
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
    }
}
function mobClosePhotoSheet() {
    var sheet = document.getElementById('mobPhotoSheet');
    if (sheet) {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
    }
}
function mobChoosePhotoSource(source) {
    var target = mobPhotoTarget;
    mobClosePhotoSheet();
    var inputId = '';
    if (target === 'profile') {
        inputId = source === 'camera' ? 'mobCustomerPhotoCamera' : 'mobCustomerPhotoGallery';
    } else if (target === 'id_card') {
        inputId = source === 'camera' ? 'mobIdCardCamera' : 'mobIdCardGallery';
    } else if (target === 'product' && mobPhotoTargetCard) {
        var selector = source === 'camera' ? 'input[id^="mobProductCamera"]' : 'input[id^="mobProductUpload"]';
        var productInput = mobPhotoTargetCard.querySelector(selector);
        if (productInput) productInput.click();
        return;
    }
    var input = inputId ? document.getElementById(inputId) : null;
    if (input) input.click();
}
function mobSetCustomerInfoCollapsed(collapsed) {
    var card = document.getElementById('mobCustomerInfoCard');
    if (!card) return;
    card.classList.toggle('is-collapsed', !!collapsed);
    var toggle = card.querySelector('.mob-collapse-title');
    if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
}
function mobToggleCustomerInfo() {
    var card = document.getElementById('mobCustomerInfoCard');
    if (!card) return;
    mobSetCustomerInfoCollapsed(!card.classList.contains('is-collapsed'));
}
function mobHandleCustomerProfile(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    mobStartProfileCrop(file);
    input.value = '';
}
function mobApplyCustomerProfileData(dataUri) {
    mobCustomerProfileData = dataUri;
    var preview = document.getElementById('mobCustomerPhotoPreview');
    if (preview) {
        preview.classList.add('has-image');
        preview.innerHTML = '<img src="' + dataUri + '" alt="Customer profile photo preview">' +
            '<button type="button" class="mob-id-remove" onclick="mobRemoveCustomerProfile()"><i class="fa fa-times"></i></button>';
    }
}
function mobShowProfileCropOverlay() {
    var overlay = document.getElementById('mobProfileCropOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
}
function mobHideProfileCropOverlay() {
    var overlay = document.getElementById('mobProfileCropOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
}
function mobSetProfileCropStatus(message, isError) {
    var el = document.getElementById('mobProfileCropStatus');
    if (!el) return;
    el.style.color = isError ? '#dc2626' : '#64748b';
    el.textContent = message || '';
}
function mobStartProfileCrop(file) {
    mobProfileCropper = null;
    mobProfileCropFile = file;
    mobShowProfileCropOverlay();
    mobSetProfileCropStatus('Preparing profile photo...');

    if (!window.FileReader) {
        mobUseOriginalProfilePhoto();
        return;
    }

    var reader = new FileReader();
    var image = new Image();

    reader.onload = function(event) {
        image.onload = function() {
            mobProfileCropper = mobCreateProductCropper(
                document.getElementById('mobProfileCropCanvas'),
                image,
                {x: 0.18, y: 0.08, width: 0.64, height: 0.84}
            );
            mobSetProfileCropStatus('Drag the box or corners to keep the face centered.');
        };

        image.onerror = function() {
            mobSetProfileCropStatus('This browser cannot preview this image. Using original photo.', true);
            mobUseOriginalProfilePhoto();
        };

        image.src = event.target.result;
    };

    reader.onerror = function() {
        mobSetProfileCropStatus('This browser cannot preview this image. Using original photo.', true);
        mobUseOriginalProfilePhoto();
    };

    reader.readAsDataURL(file);
}
function mobResetProfileCrop() {
    if (mobProfileCropper) {
        mobProfileCropper.reset();
        mobSetProfileCropStatus('Crop reset. Drag the box or corners to adjust.');
    }
}
function mobCancelProfileCrop() {
    mobProfileCropper = null;
    mobProfileCropFile = null;
    mobHideProfileCropOverlay();
}
function mobUseOriginalProfilePhoto() {
    if (!mobProfileCropFile) {
        mobCancelProfileCrop();
        return;
    }

    var file = mobProfileCropFile;
    mobCancelProfileCrop();
    mobCompressImage(file, 900, 900, 0.82).then(function(dataUri) {
        mobApplyCustomerProfileData(dataUri);
    });
}
function mobUseCroppedProfilePhoto() {
    if (!mobProfileCropper) {
        mobUseOriginalProfilePhoto();
        return;
    }

    mobSetProfileCropStatus('Cropping profile photo...');
    mobProfileCropper.getDataUrl(function(dataUri) {
        mobCancelProfileCrop();
        mobApplyCustomerProfileData(dataUri);
    });
}
function mobRemoveCustomerProfile() {
    mobCustomerProfileData = '';
    var preview = document.getElementById('mobCustomerPhotoPreview');
    if (preview) {
        preview.classList.remove('has-image');
        preview.innerHTML = '<i class="fa fa-user"></i>' +
            '<button type="button" class="mob-id-remove" onclick="mobRemoveCustomerProfile()"><i class="fa fa-times"></i></button>';
    }
    var camera = document.getElementById('mobCustomerPhotoCamera');
    var gallery = document.getElementById('mobCustomerPhotoGallery');
    if (camera) camera.value = '';
    if (gallery) gallery.value = '';
}
function mobHandleIdCard(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    mobSetOcrStatus('Choose crop area before OCR...');
    mobStartIdCardCrop(file);
    input.value = '';
}

function mobApplyIdCardImageData(dataUri) {
    mobIdCardData = dataUri;
    document.getElementById('mobIdCardImg').src = dataUri;
    document.getElementById('mobIdCardPreview').style.display = 'block';
    document.getElementById('mobCustomerInfoCard').style.display = 'block';
    mobSetCustomerInfoCollapsed(false);
    mobRevealCustomerDetails();
    mobScanIdCard(dataUri);
}

function mobShowIdCardCropOverlay() {
    var overlay = document.getElementById('mobIdCardCropOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
}

function mobHideIdCardCropOverlay() {
    var overlay = document.getElementById('mobIdCardCropOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
}

function mobSetIdCardCropStatus(message, isError) {
    var el = document.getElementById('mobIdCardCropStatus');
    if (!el) return;
    el.style.color = isError ? '#dc2626' : '#64748b';
    el.textContent = message || '';
}

function mobStartIdCardCrop(file) {
    mobIdCardCropper = null;
    mobIdCardCropFile = file;
    mobShowIdCardCropOverlay();
    mobSetIdCardCropStatus('Preparing photo for crop...');

    if (!window.FileReader) {
        mobUseOriginalIdCardPhoto();
        return;
    }

    var reader = new FileReader();
    var image = new Image();

    reader.onload = function(event) {
        image.onload = function() {
            mobIdCardCropper = mobCreateProductCropper(
                document.getElementById('mobIdCardCropCanvas'),
                image
            );
            mobSetIdCardCropStatus('Drag the box or corners to keep only the ID card.');
        };

        image.onerror = function() {
            mobSetIdCardCropStatus('This browser cannot preview this image. Using original photo.', true);
            mobUseOriginalIdCardPhoto();
        };

        image.src = event.target.result;
    };

    reader.onerror = function() {
        mobSetIdCardCropStatus('This browser cannot preview this image. Using original photo.', true);
        mobUseOriginalIdCardPhoto();
    };

    reader.readAsDataURL(file);
}

function mobResetIdCardCrop() {
    if (mobIdCardCropper) {
        mobIdCardCropper.reset();
        mobSetIdCardCropStatus('Crop reset. Drag the box or corners to adjust.');
    }
}

function mobCancelIdCardCrop() {
    mobIdCardCropper = null;
    mobIdCardCropFile = null;
    mobHideIdCardCropOverlay();
}

function mobUseOriginalIdCardPhoto() {
    if (!mobIdCardCropFile) {
        mobCancelIdCardCrop();
        return;
    }

    var file = mobIdCardCropFile;
    mobCancelIdCardCrop();
    mobSetOcrStatus('Preparing ID card photo...');
    mobCompressImage(file, 1600, 1000, 0.76).then(function(dataUri) {
        mobApplyIdCardImageData(dataUri);
    });
}

function mobUseCroppedIdCardPhoto() {
    if (!mobIdCardCropper) {
        mobUseOriginalIdCardPhoto();
        return;
    }

    mobSetIdCardCropStatus('Cropping photo...');
    mobIdCardCropper.getDataUrl(function(dataUri) {
        mobCancelIdCardCrop();
        mobSetOcrStatus('Preparing cropped ID card photo...');
        mobApplyIdCardImageData(dataUri);
    });
}
function mobRemoveIdCard() {
    mobIdCardData = '';
    document.getElementById('mobIdCardImg').src = '';
    document.getElementById('mobIdCardPreview').style.display = 'none';
    document.getElementById('mobIdCardCamera').value = '';
    document.getElementById('mobIdCardGallery').value = '';
    mobSetOcrStatus('');
    mobHideCustomerDetails();
    ['mobIdCardOcrRawText', 'mobIdCardOcrNumber', 'mobIdCardOcrKhmerName', 'mobIdCardOcrEnglishName', 'mobIdCardOcrAddress'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
}

function mobSetOcrStatus(message, isError) {
    var el = document.getElementById('mobIdCardOcrStatus');
    if (!el) return;
    el.style.color = isError ? '#dc2626' : '#64748b';
    el.textContent = message || '';
}

function mobFillIfEmpty(id, value) {
    var el = document.getElementById(id);
    if (el && value && !String(el.value || '').trim()) {
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function mobApplyIdCardFields(fields, rawText) {
    fields = fields || {};
    document.getElementById('mobIdCardOcrRawText').value = rawText || '';
    document.getElementById('mobIdCardOcrNumber').value = fields.id_card_number || '';
    document.getElementById('mobIdCardOcrKhmerName').value = fields.khmer_name || '';
    document.getElementById('mobIdCardOcrEnglishName').value = fields.english_name || '';
    document.getElementById('mobIdCardOcrAddress').value = fields.address || '';
    mobFillIfEmpty('modalCustomerIdCard', fields.id_card_number);
    mobFillIfEmpty('modalCustomerKhmerName', fields.khmer_name);
    mobFillIfEmpty('modalCustomerEnglishName', fields.english_name);
    mobFillIfEmpty('modalCustomerAddress', fields.address);
    document.getElementById('modalCustomerName').value = document.getElementById('modalCustomerKhmerName').value || document.getElementById('modalCustomerEnglishName').value || '';
}

function mobScanIdCard(dataUri) {
    mobSetOcrStatus('Reading ID card...');
    jQuery.ajax({
        url: "{{ route('loan-management.loans.ajax.scan-id-card') }}",
        method: 'POST',
        data: {
            _token: document.querySelector('meta[name="csrf-token"]').content,
            id_card_image: dataUri
        },
        success: function(res) {
            if (res && res.success) {
                var data = res.data || {};
                mobApplyIdCardFields(data.fields || {}, data.raw_text || '');
                mobSetOcrStatus(Object.keys(data.fields || {}).length ? 'ID card text filled automatically.' : 'OCR finished, but no matching fields were found.');
            } else {
                mobSetOcrStatus((res && res.message) || 'OCR unavailable.', true);
            }
        },
        error: function(xhr) {
            mobSetOcrStatus(xhr.responseJSON?.message || 'OCR failed.', true);
        }
    });
}

document.getElementById('modalBtnShowAlternatePhone')?.addEventListener('click', function() {
    var group = document.getElementById('modalAlternatePhoneGroup');
    var input = document.getElementById('modalAlternatePhone');
    if (group) group.style.display = 'block';
    if (input) input.focus();
});

var mobDocFiles = [];
jQuery(document).on('click', '#mobAddDocumentLink', function() {
    jQuery('#mobDocumentLinks').append(
        '<div class="mob-doc-link-row" style="display:flex; gap:6px; margin-bottom:6px;">' +
            '<input type="url" name="document_links[]" class="mob-input" placeholder="Paste document link">' +
            '<button type="button" class="btn btn-default btn-sm mob-remove-document-link" title="Remove link"><i class="fa fa-times"></i></button>' +
        '</div>'
    );
});

jQuery(document).on('click', '.mob-remove-document-link', function() {
    jQuery(this).closest('.mob-doc-link-row').remove();
});

function mobGetFileIcon(name) {
    var ext = (name || '').split('.').pop().toLowerCase();
    var icons = { pdf: 'fa-file-pdf-o', txt: 'fa-file-text-o', csv: 'fa-file-text-o', doc: 'fa-file-word-o', docx: 'fa-file-word-o' };
    return icons[ext] || 'fa-file-o';
}
function mobIsImageFile(file) {
    return file && file.type && file.type.indexOf('image/') === 0;
}
function mobReadTextFile(file) {
    return new Promise(function(resolve) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var text = e.target.result || '';
            var dataUri = 'data:text/plain;base64,' + btoa(unescape(encodeURIComponent(text)));
            resolve(dataUri);
        };
        reader.readAsText(file);
    });
}
function mobSetProductOcrStatus(card, message, isError) {
    var el = card ? card.querySelector('.mob-product-ocr-status') : null;
    if (!el) return;
    el.style.color = isError ? '#dc2626' : '#64748b';
    el.textContent = message || '';
}

function mobSetProductField(card, field, value) {
    if (!value) return;
    var el = card.querySelector('[name*="[' + field + ']"]');
    if (!el) return;
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

function mobApplyProductPhotoFields(card, fields, rawText) {
    fields = fields || {};
    mobSetProductField(card, 'product_name', fields.product_name);
    mobSetProductField(card, 'color', fields.color);
    mobSetProductField(card, 'storage', fields.storage);
    mobSetProductField(card, 'serial_number', fields.serial_number);
    mobSetProductField(card, 'imei', fields.imei);

    var rawInput = card.querySelector('.modal-item-ocr-raw');
    if (rawInput) rawInput.value = rawText || '';
}

function mobScanProductPhoto(card, dataUri) {
    mobSetProductOcrStatus(card, 'Reading product photo...');
    jQuery.ajax({
        url: "{{ route('loan-management.loans.ajax.scan-product-photo') }}",
        method: 'POST',
        data: {
            _token: document.querySelector('meta[name="csrf-token"]').content,
            product_image: dataUri
        },
        success: function(res) {
            if (res && res.success) {
                var data = res.data || {};
                mobApplyProductPhotoFields(card, data.fields || {}, data.raw_text || '');
                mobSetProductOcrStatus(card, Object.keys(data.fields || {}).filter(function(key) {
                    return data.fields[key];
                }).length ? 'Product fields filled automatically.' : 'OCR finished, but no matching fields were found.');
            } else {
                mobSetProductOcrStatus(card, (res && res.message) || 'Product OCR unavailable.', true);
            }
        },
        error: function(xhr) {
            mobSetProductOcrStatus(card, xhr.responseJSON?.message || 'Product OCR failed.', true);
        }
    });
}

var mobProductCropper = null;
var mobProductCropCard = null;
var mobProductCropFile = null;

function mobApplyProductPhotoData(card, dataUri) {
    var hidden = card.querySelector('.modal-item-image');
    var preview = card.querySelector('.mob-product-photo-preview');
    var icon = card.querySelector('.mob-prod-img > i');

    if (hidden) hidden.value = dataUri;
    if (preview) {
        preview.src = dataUri;
        preview.style.display = 'block';
    }
    if (icon) icon.style.display = 'none';

    mobScanProductPhoto(card, dataUri);
}

function mobShowProductCropOverlay() {
    var overlay = document.getElementById('mobProductCropOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
}

function mobHideProductCropOverlay() {
    var overlay = document.getElementById('mobProductCropOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
    }
}

function mobSetProductCropStatus(message, isError) {
    var el = document.getElementById('mobProductCropStatus');
    if (!el) return;
    el.style.color = isError ? '#dc2626' : '#64748b';
    el.textContent = message || '';
}

function mobStartProductCrop(card, file) {
    mobProductCropper = null;
    mobProductCropCard = card;
    mobProductCropFile = file;
    mobShowProductCropOverlay();
    mobSetProductCropStatus('Preparing photo for crop...');

    if (!window.FileReader) {
        mobUseOriginalProductPhoto();
        return;
    }

    var reader = new FileReader();
    var image = new Image();

    reader.onload = function(event) {
        image.onload = function() {
            mobProductCropper = mobCreateProductCropper(
                document.getElementById('mobProductCropCanvas'),
                image
            );
            mobSetProductCropStatus('Drag the box or corners to keep only the product label.');
        };

        image.onerror = function() {
            mobSetProductCropStatus('This browser cannot preview this image. Using original photo.', true);
            mobUseOriginalProductPhoto();
        };

        image.src = event.target.result;
    };

    reader.onerror = function() {
        mobSetProductCropStatus('This browser cannot preview this image. Using original photo.', true);
        mobUseOriginalProductPhoto();
    };

    reader.readAsDataURL(file);
}

function mobResetProductCrop() {
    if (mobProductCropper) {
        mobProductCropper.reset();
        mobSetProductCropStatus('Crop reset. Drag the box or corners to adjust.');
    }
}

function mobCancelProductCrop() {
    mobProductCropper = null;
    mobProductCropCard = null;
    mobProductCropFile = null;
    mobHideProductCropOverlay();
}

function mobUseOriginalProductPhoto() {
    if (!mobProductCropCard || !mobProductCropFile) {
        mobCancelProductCrop();
        return;
    }

    var card = mobProductCropCard;
    var file = mobProductCropFile;
    mobCancelProductCrop();
    mobSetProductOcrStatus(card, 'Preparing product photo...');
    mobCompressImage(file, 1400, 1400, 0.72).then(function(dataUri) {
        mobApplyProductPhotoData(card, dataUri);
    });
}

function mobUseCroppedProductPhoto() {
    if (!mobProductCropper || !mobProductCropCard) {
        mobUseOriginalProductPhoto();
        return;
    }

    var card = mobProductCropCard;
    mobSetProductCropStatus('Cropping photo...');
    mobProductCropper.getDataUrl(function(dataUri) {
        mobCancelProductCrop();
        mobSetProductOcrStatus(card, 'Preparing cropped product photo...');
        mobApplyProductPhotoData(card, dataUri);
    });
}

function mobCreateProductCropper(canvas, image, initialCrop) {
    var context = canvas.getContext('2d');
    var maxWidth = Math.min(760, image.width);
    var scale = maxWidth / image.width;
    var canvasWidth = Math.round(image.width * scale);
    var canvasHeight = Math.round(image.height * scale);
    var dragMode = null;
    var lastPoint = null;
    var handleSize = 16;
    var crop = {};

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    function reset() {
        var preset = initialCrop || {x: 0.08, y: 0.12, width: 0.84, height: 0.72};
        crop = {
            x: Math.round(canvasWidth * preset.x),
            y: Math.round(canvasHeight * preset.y),
            width: Math.round(canvasWidth * preset.width),
            height: Math.round(canvasHeight * preset.height)
        };
        constrainCrop();
        draw();
    }

    function drawHandle(x, y) {
        context.fillStyle = '#2563eb';
        context.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
    }

    function draw() {
        context.clearRect(0, 0, canvasWidth, canvasHeight);
        context.drawImage(image, 0, 0, canvasWidth, canvasHeight);
        context.fillStyle = 'rgba(15, 23, 42, 0.45)';
        context.fillRect(0, 0, canvasWidth, canvasHeight);
        context.drawImage(
            image,
            crop.x / scale,
            crop.y / scale,
            crop.width / scale,
            crop.height / scale,
            crop.x,
            crop.y,
            crop.width,
            crop.height
        );
        context.strokeStyle = '#2563eb';
        context.lineWidth = 3;
        context.strokeRect(crop.x, crop.y, crop.width, crop.height);
        drawHandle(crop.x, crop.y);
        drawHandle(crop.x + crop.width, crop.y);
        drawHandle(crop.x, crop.y + crop.height);
        drawHandle(crop.x + crop.width, crop.y + crop.height);
    }

    function getPoint(event) {
        var source = event.touches && event.touches.length ? event.touches[0] : event;
        var rect = canvas.getBoundingClientRect();

        return {
            x: (source.clientX - rect.left) * (canvas.width / rect.width),
            y: (source.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function getDragMode(point) {
        var handles = {
            nw: {x: crop.x, y: crop.y},
            ne: {x: crop.x + crop.width, y: crop.y},
            sw: {x: crop.x, y: crop.y + crop.height},
            se: {x: crop.x + crop.width, y: crop.y + crop.height}
        };

        for (var mode in handles) {
            if (
                Math.abs(point.x - handles[mode].x) <= handleSize
                && Math.abs(point.y - handles[mode].y) <= handleSize
            ) {
                return mode;
            }
        }

        if (
            point.x >= crop.x
            && point.x <= crop.x + crop.width
            && point.y >= crop.y
            && point.y <= crop.y + crop.height
        ) {
            return 'move';
        }

        return null;
    }

    function constrainCrop() {
        var minSize = 40;

        crop.width = Math.max(minSize, crop.width);
        crop.height = Math.max(minSize, crop.height);
        crop.x = Math.max(0, Math.min(crop.x, canvasWidth - crop.width));
        crop.y = Math.max(0, Math.min(crop.y, canvasHeight - crop.height));

        if (crop.x + crop.width > canvasWidth) {
            crop.width = canvasWidth - crop.x;
        }

        if (crop.y + crop.height > canvasHeight) {
            crop.height = canvasHeight - crop.y;
        }
    }

    function resizeCrop(mode, deltaX, deltaY) {
        if (mode.indexOf('n') !== -1) {
            crop.y += deltaY;
            crop.height -= deltaY;
        }
        if (mode.indexOf('s') !== -1) {
            crop.height += deltaY;
        }
        if (mode.indexOf('w') !== -1) {
            crop.x += deltaX;
            crop.width -= deltaX;
        }
        if (mode.indexOf('e') !== -1) {
            crop.width += deltaX;
        }
    }

    function startDrag(event) {
        var point = getPoint(event);
        dragMode = getDragMode(point);
        lastPoint = point;

        if (dragMode) {
            event.preventDefault();
        }
    }

    function moveDrag(event) {
        if (!dragMode) return;

        var point = getPoint(event);
        var deltaX = point.x - lastPoint.x;
        var deltaY = point.y - lastPoint.y;

        if (dragMode === 'move') {
            crop.x += deltaX;
            crop.y += deltaY;
        } else {
            resizeCrop(dragMode, deltaX, deltaY);
        }

        constrainCrop();
        lastPoint = point;
        draw();
        event.preventDefault();
    }

    function endDrag() {
        dragMode = null;
        lastPoint = null;
    }

    canvas.onmousedown = startDrag;
    canvas.onmousemove = moveDrag;
    canvas.onmouseup = endDrag;
    canvas.onmouseleave = endDrag;
    canvas.ontouchstart = startDrag;
    canvas.ontouchmove = moveDrag;
    canvas.ontouchend = endDrag;

    reset();

    return {
        reset: reset,
        getDataUrl: function(callback) {
            var cropWidth = Math.round(crop.width / scale);
            var cropHeight = Math.round(crop.height / scale);
            var maxOutput = 1600;
            var outputScale = Math.min(1, maxOutput / Math.max(cropWidth, cropHeight));
            var output = document.createElement('canvas');
            var outputContext = output.getContext('2d');

            output.width = Math.max(1, Math.round(cropWidth * outputScale));
            output.height = Math.max(1, Math.round(cropHeight * outputScale));
            outputContext.drawImage(
                image,
                crop.x / scale,
                crop.y / scale,
                crop.width / scale,
                crop.height / scale,
                0,
                0,
                output.width,
                output.height
            );
            callback(output.toDataURL('image/jpeg', 0.88));
        }
    };
}

function mobHandleProductPhoto(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var card = input.closest('.mob-product-item');
    if (!card) return;

    mobSetProductOcrStatus(card, 'Choose crop area before OCR...');
    mobStartProductCrop(card, file);
    input.value = '';
}

function mobHandleDocs(input) {
    var files = Array.from(input.files || []);
    if (!files.length) return;
    var grid = document.getElementById('mobDocGrid');
    var addBtn = grid.querySelector('.mob-doc-add');
    files.forEach(function(file) {
        var thumb = document.createElement('div');
        thumb.className = 'mob-doc-thumb';
        thumb.innerHTML = '<div class="mob-compressing-overlay"><i class="fa fa-spinner fa-spin"></i></div>';
        grid.insertBefore(thumb, addBtn);

        if (mobIsImageFile(file)) {
            mobCompressImage(file, 1200, 800, 0.65).then(function(dataUri) {
                var idx = mobDocFiles.length;
                mobDocFiles.push({ dataUri: dataUri, name: file.name, type: 'image' });
                var sizeKb = Math.round((dataUri.length * 3/4) / 1024);
                thumb.innerHTML = '<img src="' + dataUri + '">' +
                    '<button type="button" class="mob-doc-remove" onclick="mobRemoveDoc(this, ' + idx + ')"><i class="fa fa-times"></i></button>' +
                    '<span class="mob-doc-badge">' + sizeKb + 'KB</span>';
            });
        } else if (file.type === 'text/plain' || file.name.match(/\.(txt|csv|log)$/i)) {
            mobReadTextFile(file).then(function(dataUri) {
                var idx = mobDocFiles.length;
                mobDocFiles.push({ dataUri: dataUri, name: file.name, type: 'text' });
                var sizeKb = Math.round(file.size / 1024);
                thumb.innerHTML = '<div class="mob-doc-icon"><i class="fa fa-file-text-o"></i><span>' + file.name + '</span></div>' +
                    '<button type="button" class="mob-doc-remove" onclick="mobRemoveDoc(this, ' + idx + ')"><i class="fa fa-times"></i></button>' +
                    '<span class="mob-doc-badge">' + sizeKb + 'KB</span>';
            });
        } else {
            var reader = new FileReader();
            reader.onload = function(e) {
                var idx = mobDocFiles.length;
                mobDocFiles.push({ dataUri: e.target.result, name: file.name, type: 'file' });
                var sizeKb = Math.round(file.size / 1024);
                thumb.innerHTML = '<div class="mob-doc-icon"><i class="fa ' + mobGetFileIcon(file.name) + '"></i><span>' + file.name + '</span></div>' +
                    '<button type="button" class="mob-doc-remove" onclick="mobRemoveDoc(this, ' + idx + ')"><i class="fa fa-times"></i></button>' +
                    '<span class="mob-doc-badge">' + sizeKb + 'KB</span>';
            };
            reader.readAsDataURL(file);
        }
    });
    input.value = '';
}
function mobRemoveDoc(btn, idx) {
    mobDocFiles[idx] = null;
    btn.closest('.mob-doc-thumb').remove();
}

document.addEventListener('paste', function(e) {
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    var handled = false;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image/') === 0) {
            var file = items[i].getAsFile();
            if (file) {
                mobCompressImage(file, 1200, 800, 0.65).then(function(dataUri) {
                    var grid = document.getElementById('mobDocGrid');
                    var addBtn = grid.querySelector('.mob-doc-add');
                    var thumb = document.createElement('div');
                    thumb.className = 'mob-doc-thumb';
                    var idx = mobDocFiles.length;
                    mobDocFiles.push({ dataUri: dataUri, name: 'pasted-image-' + Date.now() + '.png', type: 'image' });
                    var sizeKb = Math.round((dataUri.length * 3/4) / 1024);
                    thumb.innerHTML = '<img src="' + dataUri + '">' +
                        '<button type="button" class="mob-doc-remove" onclick="mobRemoveDoc(this, ' + idx + ')"><i class="fa fa-times"></i></button>' +
                        '<span class="mob-doc-badge">' + sizeKb + 'KB</span>';
                    grid.insertBefore(thumb, addBtn);
                });
                handled = true;
            }
        }
    }
    if (handled) e.preventDefault();
});

function mobPreviewSchedule() {
    var $form = jQuery('#standaloneLoanModalForm');
    document.getElementById('modalCustomerName').value = document.getElementById('modalCustomerKhmerName').value || document.getElementById('modalCustomerEnglishName').value || '';
    var urls = { previewSchedule: "{{ route('loan-management.loans.preview-standalone-schedule') }}" };
    jQuery.post(urls.previewSchedule, $form.serialize(), function(res) {
        var rows = res.data || [];
        var $tb = jQuery('#modalScheduleTable tbody');
        var $table = $tb.closest('table');
        var totalP = 0, totalI = 0, totalA = 0, totalB = 0;
        $tb.empty();
        rows.forEach(function(r) {
            totalP += Number(r.principal || 0);
            totalI += Number(r.interest || 0);
            totalA += Number(r.total || 0);
            totalB += Number(r.balance || 0);
            $tb.append('<tr><td>'+r.schedule_no+'</td><td>'+r.due_date+'</td><td class="text-right">$'+Number(r.principal||0).toFixed(2)+'</td><td class="text-right">$'+Number(r.interest||0).toFixed(2)+'</td><td class="text-right">$'+Number(r.total||0).toFixed(2)+'</td><td class="text-right">$'+Number(r.balance||0).toFixed(2)+'</td></tr>');
        });
        $table.find('tfoot th').eq(1).text('$' + totalP.toFixed(2));
        $table.find('tfoot th').eq(2).text('$' + totalI.toFixed(2));
        $table.find('tfoot th').eq(3).text('$' + totalA.toFixed(2));
        $table.find('tfoot th').eq(4).text('$' + totalB.toFixed(2));
        document.getElementById('modalScheduleSection').style.display = 'block';
        var months = parseInt(document.querySelector('input[name="duration_months"]').value) || 1;
        document.getElementById('modalSummaryMonthly').textContent = '$' + (totalA / months).toFixed(2);
    }).fail(function(xhr) {
        if (window.toastr) toastr.error(xhr.responseJSON?.message || 'Failed');
    });
}

function mobSubmit(action) {
    document.querySelector('#standaloneLoanModalForm input[name="action_type"]').value = action;
    document.getElementById('modalCustomerName').value = document.getElementById('modalCustomerKhmerName').value || document.getElementById('modalCustomerEnglishName').value || '';
    var form = document.getElementById('standaloneLoanModalForm');
    if (form.checkValidity && ! form.checkValidity()) {
        form.reportValidity();
        return;
    }
    var fd = new FormData(form);
    if (mobIdCardData) fd.append('id_card_image', mobIdCardData);
    if (mobCustomerProfileData) fd.append('customer_profile_image', mobCustomerProfileData);
    mobDocFiles.forEach(function(d, i) { if (d) fd.append('documents[]', d.dataUri); });
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    var urls = { storeLoan: "{{ route('loan-management.loans.store-standalone') }}", loanViewBase: "{{ url('/loan-management/loans') }}" };
    var $btns = jQuery('#mobBottombar button').prop('disabled', true);
    jQuery.ajax({
        url: urls.storeLoan, method: 'POST', data: fd, processData: false, contentType: false,
        success: function(res) {
            if (window.toastr) toastr.success(res.message || 'Loan created');
            jQuery('#standaloneLoanModal').modal('hide');
            if (res?.data?.loan_id) {
                var loanUrl = urls.loanViewBase + '/' + res.data.loan_id + '/view?_lm_modal=1';
                if (window.jQuery && window.jQuery('.view_modal').length) {
                    window.jQuery('.view_modal').html('<div class="text-center" style="padding:48px;"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Loading loan...</p></div>').modal('show');
                    window.jQuery.ajax({
                        url: loanUrl, dataType: 'html',
                        success: function(html) { window.jQuery('.view_modal').html(html); },
                        error: function() { window.location.href = loanUrl; }
                    });
                } else {
                    window.location.href = loanUrl;
                }
            } else {
                location.reload();
            }
        },
        error: function(xhr) {
            var msg = 'Failed to create loan';
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                var errors = xhr.responseJSON.errors;
                msg = errors[Object.keys(errors)[0]][0] || msg;
            } else {
                msg = xhr.responseJSON?.message || msg;
            }
            if (window.toastr) toastr.error(msg); else alert(msg);
        },
        complete: function() { $btns.prop('disabled', false); }
    });
}
</script>
