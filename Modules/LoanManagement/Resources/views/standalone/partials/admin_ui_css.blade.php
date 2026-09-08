<style>
    .pos-admin-page {
        color: #1f2937;
    }
    .pos-page-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }
    .pos-page-title h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    .pos-page-title p {
        margin: 5px 0 0;
        color: #6b7280;
        font-size: 13px;
    }
    .pos-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }
    .pos-stat-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .pos-stat {
        min-height: 78px;
        padding: 14px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
    }
    .pos-stat span {
        display: block;
        color: #6b7280;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .pos-stat strong {
        display: block;
        margin-top: 8px;
        color: #111827;
        font-size: 24px;
        line-height: 1;
    }
    .pos-panel {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .pos-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #edf1f5;
        background: #f8fafc;
    }
    .pos-panel-head h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #111827;
    }
    .pos-panel-body {
        padding: 16px;
    }
    .pos-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr)) auto auto;
        gap: 10px;
        align-items: end;
        margin-bottom: 14px;
    }
    .pos-filter-grid-users {
        grid-template-columns: repeat(5, minmax(140px, 1fr)) auto auto;
    }
    .pos-filter-grid .form-control,
    .pos-form-grid .form-control {
        height: 40px;
        border-color: #d8e0ea;
        border-radius: 6px;
        box-shadow: none;
    }
    .pos-filter-grid .form-control:focus,
    .pos-form-grid .form-control:focus {
        border-color: var(--lm-primary, #2563eb);
        box-shadow: 0 0 0 3px rgba(var(--lm-primary-rgb, 37, 99, 235), .12);
    }
    .pos-data-table {
        margin-bottom: 0;
    }
    .pos-data-table > thead > tr > th {
        border-bottom: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #374151;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .pos-data-table > tbody > tr > td {
        vertical-align: middle;
        color: #374151;
    }
    .pos-avatar {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #eaf2ff;
        color: var(--lm-primary, #2563eb);
        font-weight: 800;
        text-transform: uppercase;
    }
    .pos-user-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pos-user-cell strong {
        display: block;
        color: #111827;
    }
    .pos-user-cell span,
    .pos-muted {
        color: #6b7280;
        font-size: 12px;
    }
    .pos-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .pos-badge-success {
        background: #dcfce7;
        color: #166534;
    }
    .pos-badge-muted {
        background: #f1f5f9;
        color: #475569;
    }
    .pos-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 16px;
    }
    .pos-form-grid .form-group {
        margin-bottom: 0;
    }
    .pos-form-grid label,
    .pos-form-check label {
        color: #374151;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .pos-form-full {
        grid-column: 1 / -1;
    }
    .pos-page-foot {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 14px 16px;
        border-top: 1px solid #edf1f5;
        background: #f8fafc;
    }
    @media (max-width: 991px) {
        .pos-page-head {
            align-items: flex-start;
            flex-direction: column;
        }
        .pos-action-row {
            justify-content: flex-start;
        }
        .pos-filter-grid,
        .pos-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
