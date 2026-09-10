@extends('loanmanagement::layouts.app')
@section('title', 'Telegram Chats')
@section('hide_breadcrumb', '1')

@php
    $isEmbedded = request()->boolean('_lm_embed');
    $chatLocationOptions = collect();
    $chatLocationText = 'All locations';
    $chatDefaultLocationId = null;
    try {
        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $chatUser = auth()->user();
            $chatBankDetails = $chatUser && !empty($chatUser->bank_details) ? json_decode($chatUser->bank_details, true) : [];
            $chatBankBranch = trim((string) ($chatBankDetails['branch_id'] ?? $chatBankDetails['branch'] ?? ''));
            $chatPermitted = $chatUser ? $chatUser->permitted_locations() : [];
            $chatLocationQuery = \Illuminate\Support\Facades\DB::connection('mysql_loan')->table('loan_business_locations');
            if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'deleted_at')) {
                $chatLocationQuery->whereNull('deleted_at');
            }
            if ($chatBankBranch !== '') {
                $chatLocationQuery->where(function ($q) use ($chatBankBranch) {
                    if (is_numeric($chatBankBranch)) {
                        $q->where('id', (int) $chatBankBranch);
                        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                            $q->orWhere('main_location_id', (int) $chatBankBranch);
                        }
                    } else {
                        $q->where('name', $chatBankBranch);
                        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'location_code')) {
                            $q->orWhere('location_code', $chatBankBranch);
                        }
                    }
                });
            } elseif ($chatPermitted !== 'all' && !($chatUser && ($chatUser->can('access_all_locations') || $chatUser->can('loan_management.chat.admin')))) {
                $chatMainLocationIds = array_values(array_filter((array) $chatPermitted));
                if (!empty($chatMainLocationIds) && \Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                    $chatLocationQuery->where(function ($q) use ($chatMainLocationIds) {
                        $q->whereIn('main_location_id', $chatMainLocationIds)->orWhereIn('id', $chatMainLocationIds);
                    });
                } elseif (!empty($chatMainLocationIds)) {
                    $chatLocationQuery->whereIn('id', $chatMainLocationIds);
                } else {
                    $chatLocationQuery->whereRaw('1 = 0');
                }
            }
            $chatLocationOptions = $chatLocationQuery->orderBy('name')->get(['id', 'name']);
            $chatLocationText = $chatLocationOptions->count() === 1
                ? (string) ($chatLocationOptions->first()->name ?? 'My location')
                : ($chatLocationOptions->count() > 1 ? 'Multiple locations' : 'No location');
            $chatDefaultLocationId = $chatLocationOptions->count() === 1 ? (int) ($chatLocationOptions->first()->id ?? 0) : null;
        }
    } catch (\Throwable $e) {
        $chatLocationOptions = collect();
    }
@endphp

@section('loan_css')
<style>
    :root {
        --tg-primary: #2481cc;
        --tg-primary-dark: #1b6cae;
        --tg-primary-soft: #e8f3fc;
        --tg-text: #111827;
        --tg-muted: #707579;
        --tg-border: #e5e7eb;
        --tg-panel: #ffffff;
        --tg-list-hover: #f4f7fa;
        --tg-shadow-soft: 0 12px 32px rgba(15, 23, 42, 0.08);
    }

    /* Clean layout on dedicated chat view */
    .lm-footer {
        display: none !important;
    }
    .lm-breadcrumb-wrap {
        display: none !important;
    }
    #lmTgFab {
        display: none !important;
    }
    .lm-content {
        padding: 0 !important;
        margin: 0 !important;
    }
    .container-fluid.lm-workspace {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Overall Shell */
    .tg-mobile-wrapper {
        position: relative;
        width: 100%;
        height: calc(100dvh - 72px) !important;
        max-height: calc(100dvh - 72px) !important;
        min-height: 520px;
        background: var(--tg-panel);
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--tg-border);
        display: flex;
        box-shadow: var(--tg-shadow-soft);
        font-family: "Khmer OS Battambang", "Noto Sans Khmer", "SF Pro Display", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    }

    /* Sidebar / Chat List View (Screen 1) */
    .tg-pane-list {
        width: 392px;
        flex: 0 0 392px;
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--tg-border);
        background: var(--tg-panel);
        position: relative;
        z-index: 10;
        height: 100% !important;
        max-height: 100% !important;
        overflow: hidden !important;
    }

    /* Conversation View (Screen 2) */
    .tg-pane-chat {
        flex: 1 1 auto;
        display: flex !important;
        flex-direction: column !important;
        background: #8dae90;
        position: relative;
        min-width: 0;
        height: 100% !important;
        max-height: 100% !important;
        overflow: hidden !important;
    }

    /* Top Telegram App Header */
    .tg-top-bar {
        padding: 12px 16px 10px;
        background: var(--tg-panel);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #eef2f6;
        flex: 0 0 auto;
    }
    .tg-top-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tg-app-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #42a5f5, #1976d2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 19px;
        box-shadow: 0 2px 6px rgba(25, 118, 210, 0.35);
        overflow: hidden;
    }
    .tg-app-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--tg-primary);
        letter-spacing: 0;
        margin: 0;
    }
    .tg-top-actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .tg-icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--tg-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .tg-icon-btn:hover, .tg-icon-btn:active {
        background: #f0f2f5;
        color: var(--tg-text);
    }

    /* Search Bar */
    .tg-search-wrap {
        padding: 8px 14px 10px;
        background: var(--tg-panel);
        flex: 0 0 auto;
    }
    .tg-search-box {
        position: relative;
        display: flex;
        align-items: center;
        background: #f3f6f9;
        border-radius: 12px;
        padding: 0 14px;
        height: 40px;
        border: 1.5px solid transparent;
        transition: all 0.2s ease;
    }
    .tg-search-box:focus-within {
        background: #fff;
        border-color: var(--tg-primary);
        box-shadow: 0 0 0 3px rgba(36, 129, 204, 0.12);
    }
    .tg-search-box i.fa-search {
        color: #949a9e;
        font-size: 14px;
        margin-right: 10px;
    }
    .tg-search-input {
        flex: 1;
        border: none;
        background: transparent;
        outline: none;
        font-size: 14px;
        color: var(--tg-text);
    }
    .tg-search-input::placeholder {
        color: #8c9398;
    }
    .tg-search-clear {
        display: none;
        border: none;
        background: #cfd4d9;
        color: #fff;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
        padding: 0;
    }

    /* Filter Pills Bar */
    .tg-filter-pills {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 14px 12px;
        background: var(--tg-panel);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
        border-bottom: 1px solid #eef2f6;
        scrollbar-width: none;
        flex: 0 0 auto;
    }
    .tg-filter-pills::-webkit-scrollbar {
        display: none;
    }
    .tg-pill {
        border: 1px solid #e0e7ef;
        background: var(--tg-panel);
        color: #555b61;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.18s ease;
        flex: 0 0 auto;
        user-select: none;
    }
    .tg-pill:hover {
        background: #f7f9fa;
        color: #222;
    }
    .tg-pill.active {
        background: var(--tg-primary-soft);
        border-color: var(--tg-primary);
        color: var(--tg-primary);
        font-weight: 700;
    }
    .tg-pill-badge {
        background: #cfd8dc;
        color: #455a64;
        font-size: 10.5px;
        padding: 1px 6px;
        border-radius: 10px;
        font-weight: 700;
    }
    .tg-pill.active .tg-pill-badge {
        background: var(--tg-primary);
        color: #fff;
    }
    .tg-pill-manage {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: var(--tg-primary) !important;
        font-weight: 600;
    }
    .tg-pill-manage:hover {
        background: #e0f2fe !important;
        border-color: #0284c7 !important;
    }

    /* Chat List Items */
    .tg-chat-list {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 6px 8px 86px;
        min-height: 0;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .tg-chat-list::-webkit-scrollbar,
    .tg-chat-body::-webkit-scrollbar {
        width: 7px;
    }
    .tg-chat-list::-webkit-scrollbar-thumb,
    .tg-chat-body::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.32);
        border-radius: 999px;
    }
    .tg-chat-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 10px;
        cursor: pointer;
        position: relative;
        background: transparent;
        transition: background 0.15s ease, box-shadow 0.15s ease;
        border-bottom: 0;
        border-radius: 10px;
        user-select: none;
    }
    .tg-chat-item:hover {
        background: var(--tg-list-hover);
    }
    .tg-chat-item.active {
        background: var(--tg-primary-soft);
        box-shadow: inset 3px 0 0 var(--tg-primary);
    }
    .tg-avatar-wrap {
        position: relative;
        width: 50px;
        height: 50px;
        flex: 0 0 50px;
    }
    .tg-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        overflow: hidden;
        text-transform: uppercase;
    }
    .tg-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    .tg-avatar-dot {
        position: absolute;
        bottom: 0px;
        right: 0px;
        width: 13px;
        height: 13px;
        border-radius: 50%;
        background: #22c55e;
        border: 2.5px solid #fff;
    }
    .tg-item-body {
        flex: 1 1 auto;
        min-width: 0;
    }
    .tg-item-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 3px;
        gap: 8px;
    }
    .tg-item-name {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--tg-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-item-customer-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 3px;
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.25;
    }
    .tg-item-customer-meta span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tg-item-customer-meta i {
        color: var(--tg-primary);
        font-size: 10.5px;
        flex: 0 0 auto;
    }
    .tg-item-folder-tag {
        display: inline-flex;
        align-items: center;
        max-width: 92px;
        padding: 2px 6px;
        margin-left: 4px;
        border-radius: 999px;
        background: #e0f2fe;
        color: #0369a1;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tg-item-time {
        font-size: 12px;
        color: #8c9398;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .tg-item-row-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .tg-item-preview {
        font-size: 13px;
        color: var(--tg-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .tg-item-preview i {
        font-size: 12px;
        color: var(--tg-primary);
    }
    .tg-item-badge {
        background: var(--tg-primary);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .tg-check-icon {
        color: var(--tg-primary);
        font-size: 13px;
    }
    .tg-empty-chats {
        padding: 50px 20px;
        text-align: center;
        color: #8c9398;
    }
    .tg-empty-chats i {
        font-size: 42px;
        color: #b0bec5;
        margin-bottom: 12px;
        display: block;
    }

    /* Floating Action Buttons */
    .tg-fabs-wrap,
    .tg-fab-stack {
        position: absolute;
        right: 16px;
        bottom: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        z-index: 20;
    }
    .tg-fab,
    .tg-fab-cam,
    .tg-fab-camera {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--tg-panel);
        color: #475569;
        box-shadow: 0 4px 14px rgba(0,0,0,0.18);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .tg-fab:active,
    .tg-fab-cam:active,
    .tg-fab-camera:active {
        transform: scale(0.92);
    }
    .tg-fab-compose {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2ea5e8, var(--tg-primary-dark));
        color: #fff;
        box-shadow: 0 6px 20px rgba(36, 129, 204, 0.45);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .tg-fab-compose:active {
        transform: scale(0.92);
    }

    /* Conversation Pane Header */
    .tg-chat-header {
        min-height: 62px;
        height: auto;
        background: var(--tg-panel);
        border-bottom: 1px solid rgba(0,0,0,0.06);
        padding: 9px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex: 0 0 auto;
        z-index: 30;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .tg-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1 1 auto;
    }
    .tg-back-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--tg-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        cursor: pointer;
        flex: 0 0 36px;
        transition: background 0.15s ease;
    }
    .tg-back-btn:hover {
        background: rgba(0,0,0,0.05);
    }
    .tg-header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        flex: 0 0 40px;
        overflow: hidden;
        text-transform: uppercase;
    }
    .tg-header-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .tg-header-info {
        min-width: 0;
        flex: 1 1 auto;
    }
    .tg-header-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--tg-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.25;
    }
    .tg-header-status {
        font-size: 12px;
        color: var(--tg-muted);
        white-space: normal;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.35;
        max-height: 35px;
    }
    .tg-header-status .tg-header-meta-part {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 8px;
        max-width: 180px;
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tg-header-status i {
        color: var(--tg-primary);
        font-size: 10.5px;
    }
    .tg-header-status.online {
        color: #22c55e;
        font-weight: 600;
    }
    .tg-header-right {
        display: flex;
        align-items: center;
        gap: 2px;
        flex: 0 0 auto;
    }

    /* Telegram Doodle Wallpaper Chat Body */
    .tg-chat-body {
        flex: 1 1 0;
        height: 0;
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 18px max(18px, calc((100% - 920px) / 2)) 22px;
        position: relative;
        background-color: #8dae90;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round' opacity='0.16'%3E%3Cpath d='M25 20c-4 0-7 3-7 7v10c0 4 3 7 7 7h12l8 6v-6h2c4 0 7-3 7-7V27c0-4-3-7-7-7H25z'/%3E%3Cpath d='M32 30h14M32 36h8'/%3E%3Ccircle cx='110' cy='30' r='10'/%3E%3Cpath d='M106 28l3 3 6-6'/%3E%3Cpath d='M75 55c0-6 5-10 11-10s11 4 11 10c0 8-11 14-11 14s-11-6-11-14z'/%3E%3Cpath d='M22 85l6-6 6 6-6 6z'/%3E%3Cpath d='M125 75c-3-5-9-7-14-4s-7 9-4 14c3 5 9 7 14 4s7-9 4-14z'/%3E%3Cpath d='M120 78l4 6'/%3E%3Ccircle cx='35' cy='125' r='12'/%3E%3Cpath d='M31 123a2 2 0 1 0 4 0a2 2 0 1 0-4 0'/%3E%3Cpath d='M39 123a2 2 0 1 0 4 0a2 2 0 1 0-4 0'/%3E%3Cpath d='M31 129c2 2 6 2 8 0'/%3E%3Cpath d='M80 110l10 5-5 10-10-5z'/%3E%3Cpath d='M115 125c0-4 4-8 9-8s9 4 9 8v10h-18v-10z'/%3E%3Cpath d='M124 117v18'/%3E%3Cpath d='M65 25l4 4-4 4'/%3E%3Ccircle cx='70' cy='85' r='3'/%3E%3Ccircle cx='140' cy='45' r='2'/%3E%3Ccircle cx='15' cy='60' r='2'/%3E%3Ccircle cx='95' cy='140' r='2.5'/%3E%3Ccircle cx='55' cy='145' r='1.5'/%3E%3C/g%3E%3C/svg%3E");
        background-repeat: repeat;
        background-size: 160px 160px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.35) transparent;
    }

    /* Date Separator */
    .tg-date-divider {
        text-align: center;
        margin: 16px 0 12px;
        position: relative;
        z-index: 2;
    }
    .tg-date-divider span {
        background: rgba(0, 0, 0, 0.22);
        color: #ffffff;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 14px;
        border-radius: 14px;
        backdrop-filter: blur(4px);
    }
    .tg-load-more-wrap {
        text-align: center;
        margin: 10px 0 14px;
    }
    .tg-load-more-btn {
        border: 1px solid #c7d8ea;
        background: #fff;
        color: var(--tg-primary);
        border-radius: 18px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .tg-load-more-btn:disabled {
        opacity: .6;
        cursor: default;
    }

    /* Message Bubbles */
    .tg-msg-row {
        display: flex;
        margin-bottom: 8px;
        position: relative;
        z-index: 2;
    }
    .tg-msg-row.own {
        justify-content: flex-end;
    }
    .tg-bubble {
        max-width: min(76%, 680px);
        min-width: 80px;
        padding: 8px 12px 6px;
        border-radius: 15px;
        position: relative;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
        font-size: 14.5px;
        line-height: 1.48;
        overflow-wrap: anywhere;
    }
    .tg-msg-text {
        white-space: pre-wrap;
    }
    .tg-msg-row.own .tg-bubble {
        background: #dcf8c6;
        color: #000000;
        border-bottom-right-radius: 4px;
    }
    .tg-msg-row:not(.own) .tg-bubble {
        background: var(--tg-panel);
        color: var(--tg-text);
        border-bottom-left-radius: 4px;
    }

    .tg-msg-sender {
        font-size: 12px;
        font-weight: 700;
        color: var(--tg-primary);
        margin-bottom: 3px;
    }

    .tg-quote-box {
        border-left: 3px solid #e53935;
        background: rgba(229, 57, 53, 0.08);
        padding: 4px 8px;
        border-radius: 4px 8px 8px 4px;
        margin-bottom: 5px;
        font-size: 12.5px;
    }
    .tg-quote-author {
        font-weight: 700;
        color: #e53935;
        font-size: 12px;
        margin-bottom: 1px;
    }
    .tg-quote-text {
        color: #555555;
        font-size: 11.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .tg-msg-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 3px;
        margin-top: 2px;
        font-size: 10.5px;
        color: #687987;
        float: right;
        margin-left: 8px;
        user-select: none;
    }
    .tg-msg-meta i.fa-check, .tg-msg-meta .tg-ticks {
        color: #4fae63;
        font-size: 11px;
    }
    .tg-msg-meta .tg-ticks i:last-child {
        margin-left: -5px;
    }

    /* Voice Message Audio Player Bubble */
    .tg-voice-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 2px 0;
        min-width: min(230px, 64vw);
    }
    .tg-voice-play-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        flex: 0 0 44px;
        transition: transform 0.15s ease, opacity 0.15s ease;
    }
    .tg-voice-play-btn:active {
        transform: scale(0.92);
    }
    .tg-msg-row.own .tg-voice-play-btn {
        background: #4fae63 !important;
    }
    .tg-msg-row:not(.own) .tg-voice-play-btn {
        background: var(--tg-primary) !important;
    }
    .tg-voice-wave-wrap {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .tg-voice-waveform {
        display: flex;
        align-items: center;
        gap: 2px;
        height: 24px;
        cursor: pointer;
    }
    .tg-voice-bar {
        width: 3px;
        border-radius: 2px;
        background: #a3c4a8;
        transition: background 0.15s ease;
    }
    .tg-msg-row.own .tg-voice-bar {
        background: #9cd19f;
    }
    .tg-msg-row.own .tg-voice-bar.played {
        background: #2e7d32;
    }
    .tg-msg-row:not(.own) .tg-voice-bar {
        background: #cfd8dc;
    }
    .tg-msg-row:not(.own) .tg-voice-bar.played {
        background: #2481cc;
    }
    .tg-voice-timing {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 11px;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }

    .tg-reaction-badge {
        position: absolute;
        bottom: -9px;
        left: 8px;
        background: #ffffff;
        border-radius: 12px;
        padding: 2px 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 1.5px 4px rgba(0,0,0,0.16);
        z-index: 5;
        font-size: 13px;
        border: 1px solid rgba(0,0,0,0.06);
    }
    .tg-reaction-avatar {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 700;
        color: #fff;
    }
    .tg-reaction-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .tg-image-wrap {
        margin: 4px 0;
        border-radius: 12px;
        overflow: hidden;
        max-width: min(320px, 70vw);
        cursor: pointer;
    }
    .tg-image-wrap img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 12px;
    }

    .tg-file-card {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.04);
        border-radius: 12px;
        padding: 9px 10px;
        margin: 4px 0;
        text-decoration: none !important;
        color: inherit;
    }
    .tg-file-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--tg-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex: 0 0 36px;
    }
    .tg-file-details {
        min-width: 0;
        flex: 1;
    }
    .tg-file-name {
        font-weight: 700;
        font-size: 12.5px;
        color: var(--tg-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-file-size {
        font-size: 11px;
        color: #64748b;
    }

    /* Bottom Telegram Composer Bar - Fixed at the Bottom with Floating Capsule */
    .tg-composer-bar {
        background: transparent !important;
        border-top: none !important;
        padding: 8px 14px 12px !important;
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        z-index: 50;
        box-shadow: none !important;
    }
    .tg-composer-input-wrap {
        flex: 1 1 auto;
        position: relative;
        display: flex;
        align-items: center;
        background: var(--tg-panel);
        border-radius: 14px;
        padding: 0 8px 0 10px;
        min-height: 48px;
        border: none;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.16);
        transition: box-shadow 0.15s ease;
    }
    .tg-composer-input-wrap:focus-within {
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .tg-composer-inner-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--tg-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        flex: 0 0 36px;
        padding: 0;
        transition: color 0.15s ease, transform 0.1s ease;
    }
    .tg-composer-inner-btn:hover {
        color: var(--tg-primary);
    }
    .tg-composer-inner-btn:active {
        transform: scale(0.92);
    }
    .tg-composer-input {
        flex: 1 1 auto;
        border: none;
        background: transparent;
        outline: none;
        font-size: 16px;
        color: var(--tg-text);
        padding: 8px 6px;
        min-width: 0;
    }
    .tg-composer-input::placeholder {
        color: var(--tg-muted);
        font-size: 15px;
    }
    .tg-composer-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--tg-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        cursor: pointer;
        flex: 0 0 38px;
        transition: color 0.15s ease, background 0.15s ease;
    }
    .tg-composer-btn:hover {
        background: rgba(0,0,0,0.05);
        color: #222;
    }
    .tg-send-action-btn {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        border: none;
        background: var(--tg-primary);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        flex: 0 0 46px;
        box-shadow: 0 2px 8px rgba(36, 129, 204, 0.45);
        transition: transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.15s ease;
    }
    .tg-send-action-btn:hover {
        background: var(--tg-primary-dark);
    }
    .tg-send-action-btn:active {
        transform: scale(0.90);
    }
    .tg-send-action-btn.is-send-ready {
        background: #2481cc;
    }
    .tg-send-action-btn.is-send-ready i {
        margin-left: 2px;
    }
    .tg-send-action-btn.recording {
        background: #dc2626;
        animation: tgPulse 1.2s infinite;
    }
    .tg-send-action-btn.recording {
        background: #dc2626;
        animation: tgPulse 1.2s infinite;
    }

    /* Live Voice Recording Overlay in Composer */
    .tg-voice-recording-panel {
        display: none;
        flex: 1 1 auto;
        align-items: center;
        gap: 10px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 22px;
        padding: 0 14px;
        height: 44px;
    }
    .tg-composer-bar.is-recording .tg-composer-input-wrap {
        display: none;
    }
    .tg-composer-bar.is-recording .tg-voice-recording-panel {
        display: flex;
    }
    .tg-rec-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #dc2626;
        animation: tgDotBlink 1s infinite;
    }
    @keyframes tgDotBlink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.2; }
    }
    .tg-rec-timer {
        font-size: 14px;
        font-weight: 700;
        color: #15803d;
        min-width: 46px;
    }
    .tg-rec-wave {
        flex: 1;
        height: 18px;
        display: flex;
        align-items: center;
        gap: 3px;
        overflow: hidden;
    }
    .tg-rec-wave span {
        display: block;
        width: 3px;
        border-radius: 2px;
        background: #22c55e;
        height: 6px;
        animation: tgWaveAnim 0.8s ease-in-out infinite alternate;
    }
    .tg-rec-wave span:nth-child(2n) { animation-delay: 0.15s; height: 14px; }
    .tg-rec-wave span:nth-child(3n) { animation-delay: 0.3s; height: 18px; }
    @keyframes tgWaveAnim {
        from { transform: scaleY(0.4); }
        to { transform: scaleY(1); }
    }
    .tg-rec-cancel-btn {
        border: none;
        background: transparent;
        color: #dc2626;
        font-size: 16px;
        cursor: pointer;
        padding: 4px 8px;
    }

    /* Attachment Action Sheet Popup */
    .tg-attach-sheet {
        display: none;
        position: absolute;
        bottom: 64px;
        right: 54px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.18);
        border: 1px solid #e2e8f0;
        padding: 8px;
        z-index: 150;
        width: 190px;
    }
    .tg-attach-sheet.open {
        display: block;
    }
    .tg-attach-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 8px;
        color: #334155;
        font-size: 13.5px;
        cursor: pointer;
        transition: background 0.15s ease;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
    }
    .tg-attach-item:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .tg-attach-item i {
        font-size: 16px;
        width: 20px;
        text-align: center;
    }
    .tg-attach-item.invoice i { color: #f59e0b; }
    .tg-attach-item.photo i { color: #0ea5e9; }
    .tg-attach-item.file i { color: #8b5cf6; }
    .tg-attach-item.location i { color: #ef4444; }

    /* Emoji Picker Bar Popup */
    .tg-emoji-panel {
        display: none;
        position: absolute;
        bottom: 64px;
        left: 14px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border: 1px solid #e2e8f0;
        padding: 10px;
        z-index: 150;
        max-width: 290px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .tg-emoji-panel.hidden {
        display: none !important;
    }
    .tg-emoji-item {
        font-size: 20px;
        cursor: pointer;
        padding: 3px;
        border-radius: 6px;
        transition: transform 0.15s ease;
    }
    .tg-emoji-item:hover {
        transform: scale(1.25);
        background: #f1f5f9;
    }

    /* Kebab Dropdown Menu */
    .tg-dropdown-menu {
        display: none;
        position: absolute;
        top: 54px;
        right: 14px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border: 1px solid #e2e8f0;
        padding: 6px;
        z-index: 150;
        min-width: 175px;
    }
    .tg-dropdown-menu.open {
        display: block;
    }
    .tg-dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        border-radius: 6px;
        text-decoration: none !important;
    }
    .tg-dropdown-item:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .tg-dropdown-item i {
        font-size: 14px;
        color: #64748b;
        width: 18px;
        text-align: center;
    }

    /* Full-Screen Image Viewer Modal */
    .tg-viewer-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.88);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .tg-viewer-modal.open {
        display: flex;
    }
    .tg-viewer-modal img {
        max-width: 95vw;
        max-height: 85vh;
        object-fit: contain;
        border-radius: 8px;
    }
    .tg-viewer-close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        font-size: 20px;
        cursor: pointer;
    }

    /* Desktop Placeholder when no chat is open */
    .tg-chat-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #ffffff;
        text-align: center;
        padding: 30px;
    }
    .tg-placeholder-badge {
        background: rgba(0,0,0,0.22);
        backdrop-filter: blur(6px);
        padding: 10px 22px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    }

    /* ==========================================================================
       CHAT FOLDERS MODALS & CONTROLS
       ========================================================================== */
    .tg-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 1000000 !important;
        align-items: center;
        justify-content: center;
        padding: 16px;
        backdrop-filter: blur(3px);
    }
    .tg-modal-overlay.open {
        display: flex;
    }
    .tg-modal-card {
        background: #ffffff;
        border-radius: 16px;
        width: 100%;
        max-width: 480px;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        animation: tgModalIn 0.18s ease-out;
    }
    @keyframes tgModalIn {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .tg-modal-head {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: #fff;
    }
    .tg-modal-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tg-modal-close {
        background: transparent;
        border: none;
        font-size: 22px;
        color: #94a3b8;
        cursor: pointer;
        line-height: 1;
        padding: 0 4px;
    }
    .tg-modal-close:hover {
        color: #0f172a;
    }
    .tg-modal-body {
        padding: 16px 18px;
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
    }
    .tg-modal-foot {
        padding: 12px 18px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        background: #f8fafc;
    }
    .tg-btn-primary {
        background: #2481cc;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 18px;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .tg-btn-primary:hover {
        background: #1b6cae;
    }
    .tg-btn-secondary {
        background: #e2e8f0;
        color: #334155;
        border: none;
        border-radius: 10px;
        padding: 9px 16px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
    }
    .tg-btn-secondary:hover {
        background: #cbd5e1;
    }
    .tg-folder-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 8px;
        gap: 10px;
    }
    .tg-folder-item-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }
    .tg-folder-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex: 0 0 36px;
    }
    .tg-folder-item-name {
        font-weight: 700;
        font-size: 14px;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-folder-item-count {
        font-size: 12px;
        color: #64748b;
    }
    .tg-folder-item-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
    }
    .tg-btn-edit,
    .tg-btn-danger {
        border: 0;
        border-radius: 8px;
        min-height: 32px;
        padding: 6px 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease;
    }
    .tg-btn-edit {
        background: #e8f3fc;
        color: var(--tg-primary);
    }
    .tg-btn-edit:hover {
        background: #dbeafe;
    }
    .tg-btn-danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    .tg-btn-danger:hover {
        background: #fecaca;
    }
    .tg-btn-edit:active,
    .tg-btn-danger:active {
        transform: scale(0.96);
    }
    .tg-cust-select-list {
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        margin-top: 8px;
    }
    .tg-cust-select-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-bottom: 1px solid #f1f3f5;
        cursor: pointer;
        user-select: none;
    }
    .tg-cust-select-item:hover {
        background: #f8fafc;
    }
    .tg-cust-select-item input[type="checkbox"] {
        width: 17px;
        height: 17px;
        accent-color: #2481cc;
        cursor: pointer;
    }
    .tg-cust-mini-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 32px;
        text-transform: uppercase;
    }
    .tg-cust-name {
        font-weight: 700;
        font-size: 13px;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-cust-sub {
        font-size: 11.5px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ==========================================================================
       MOBILE RESPONSIVE ADAPTATION
       ========================================================================== */
    @media (max-width: 991px) {
        .content-header,
        .lm-breadcrumb-wrap,
        .content > .row {
            display: none !important;
        }
        .content {
            padding: 0 !important;
            margin: 0 !important;
        }
        .container-fluid.lm-workspace {
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Full mobile screen wrapper */
        .tg-mobile-wrapper {
            height: calc(100vh - 56px) !important;
            height: calc(100dvh - 56px) !important;
            min-height: calc(100vh - 56px) !important;
            border-radius: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* Single Pane State Machine */
        .tg-pane-list {
            width: 100% !important;
            flex: 1 1 auto !important;
            border-right: none;
            padding-bottom: 60px;
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
        }
        .tg-fabs-wrap {
            bottom: 74px !important;
        }
        .tg-pane-chat {
            width: 100% !important;
            flex: 1 1 auto !important;
            display: none !important;
            height: 100% !important;
            flex-direction: column !important;
        }

        /* When Conversation view is active on mobile */
        .tg-mobile-wrapper.in-conversation .tg-pane-list {
            display: none !important;
        }
        .tg-mobile-wrapper.in-conversation .tg-pane-chat {
            display: flex !important;
        }

        /* Show Back Button in Chat Header */
        .tg-back-btn {
            display: inline-flex !important;
        }

        /* Hide headers/nav while viewing full chat conversation */
        body.tg-viewing-chat #loanMobileNav {
            display: none !important;
        }
        body.tg-viewing-chat #loanManagementHeader {
            display: none !important;
        }
        body.tg-viewing-chat .tg-mobile-wrapper {
            height: 100vh !important;
            height: 100dvh !important;
            min-height: 100vh !important;
        }

        /* Fixed / Sticky Composer Bar at the Bottom */
        .tg-composer-bar {
            position: sticky !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            z-index: 100 !important;
            background: #87ab8c !important;
            padding: 6px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
            border-top: none !important;
            box-shadow: none !important;
        }

        .tg-bubble {
            max-width: 88%;
        }
    }

    .tg-pill-action {
        padding: 5px 12px;
        border-radius: 18px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        background: #f1f5f9;
        color: #2481cc;
        border: 1px dashed #93c5fd;
        transition: all 0.15s ease;
        flex: 0 0 auto;
    }
    .tg-pill-action:hover {
        background: #e0f2fe;
        border-color: #38bdf8;
    }
    .tg-item-quick-folder {
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 14px;
        cursor: pointer;
        padding: 2px 6px;
        border-radius: 6px;
        transition: all 0.15s ease;
        margin-left: 6px;
    }
    .tg-item-quick-folder:hover {
        color: var(--tg-primary);
        background: #e0f2fe;
    }

    @media (min-width: 992px) {
        .lm-content {
            background: #f6f8fb;
        }
        .tg-back-btn {
            display: none;
        }
        .tg-mobile-wrapper {
            margin: 12px;
            width: calc(100% - 24px);
        }
    }
</style>
@endsection

@section('content_body')
<div class="tg-mobile-wrapper" id="tgAppWrapper">
    <!-- ================================================================== -->
    <!-- SCREEN 1: TELEGRAM CHATS LIST (Screenshot 1)                        -->
    <!-- ================================================================== -->
    <aside class="tg-pane-list" id="tgPaneList">
        <!-- Top App Bar -->
        <div class="tg-top-bar">
            <div class="tg-top-left">
                <div class="tg-app-avatar" title="Telegram">
                    <i class="fa fa-telegram"></i>
                </div>
                <h1 class="tg-app-title">Telegram</h1>
            </div>
            <div class="tg-top-actions">
                <button type="button" class="tg-icon-btn" id="tgListMenuBtn" aria-label="Menu" title="Menu">
                    <i class="fa fa-ellipsis-v"></i>
                </button>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="tg-search-wrap">
            <div class="tg-search-box">
                <i class="fa fa-search"></i>
                <input type="text" class="tg-search-input" id="tgSearchInput" placeholder="Search Chats" autocomplete="off">
                <button type="button" class="tg-search-clear" id="tgSearchClear" aria-label="Clear">&times;</button>
            </div>
        </div>

        <!-- Filter Category Pills (Scrollable) -->
        <div class="tg-filter-pills" id="tgFilterPills">
            <button type="button" class="tg-pill active" data-filter="all">
                <span>All</span>
                <span class="tg-pill-badge" id="tgBadgeAll">0</span>
            </button>
            <button type="button" class="tg-pill" data-filter="unread">
                <span>Unread Chat</span>
                <span class="tg-pill-badge" id="tgBadgeUnread">0</span>
            </button>
            <!-- Dynamic Folder Pills -->
            <div id="tgDynamicFolderPills" style="display:contents"></div>
            @foreach($chatLocationOptions as $loc)
                <button type="button" class="tg-pill" data-filter="loc_{{ $loc->id }}" data-location-id="{{ $loc->id }}">
                    <span>{{ $loc->name }}</span>
                </button>
            @endforeach
            <!-- Add New Folder Button -->
            <button type="button" class="tg-pill-action" id="tgBtnDirectNewFolder" title="Create New Folder">
                <i class="fa fa-plus"></i> <span>New Folder</span>
            </button>
            <!-- Manage / Add Folders Button -->
            <button type="button" class="tg-pill-action" id="tgBtnManageFolders" title="Edit / Add Folders">
                <i class="fa fa-folder-open-o"></i> <span>Edit</span>
            </button>
        </div>

        <!-- Chats List -->
        <div class="tg-chat-list" id="tgChatList">
            <div class="tg-empty-chats">
                <i class="fa fa-circle-o-notch fa-spin"></i>
                <div>Loading Telegram chats...</div>
            </div>
        </div>

        <!-- Bottom Floating Action Buttons (Telegram Style) -->
        <div class="tg-fabs-wrap">
            <button type="button" class="tg-fab tg-fab-camera" id="tgFabCamera" title="Take photo / media" aria-label="Media">
                <i class="fa fa-camera"></i>
            </button>
            <button type="button" class="tg-fab tg-fab-compose" id="tgFabCompose" title="New message" aria-label="Compose">
                <i class="fa fa-pencil"></i>
            </button>
        </div>

        <!-- Compose Customer Dropdown Menu -->
        <div class="tg-dropdown-menu" id="tgComposeDropdown">
            <a class="tg-dropdown-item" id="tgActionRefreshAll" href="javascript:void(0)"><i class="fa fa-refresh"></i> Refresh All Chats</a>
            <a class="tg-dropdown-item" id="tgActionManageFoldersDropdown" href="javascript:void(0)"><i class="fa fa-folder-open-o"></i> Manage Chat Folders</a>
            <a class="tg-dropdown-item" id="tgActionNewFolderDropdown" href="javascript:void(0)"><i class="fa fa-plus-circle"></i> Create New Folder</a>
            <a class="tg-dropdown-item" href="{{ route('loan-management.settings.telegram') }}"><i class="fa fa-cog"></i> Telegram Settings</a>
        </div>
    </aside>

    <!-- ================================================================== -->
    <!-- SCREEN 2: TELEGRAM CONVERSATION VIEW (Screenshot 2)                -->
    <!-- ================================================================== -->
    <main class="tg-pane-chat" id="tgPaneChat">
        <!-- Chat Header -->
        <div class="tg-chat-header" id="tgChatHeader" style="display:none">
            <div class="tg-header-left">
                <button type="button" class="tg-back-btn" id="tgBackToListBtn" title="Back" aria-label="Back to chats">
                    <i class="fa fa-arrow-left"></i>
                </button>
                <div class="tg-header-avatar" id="tgHeaderAvatar"></div>
                <div class="tg-header-info">
                    <div class="tg-header-name" id="tgHeaderName">Customer Name</div>
                    <div class="tg-header-status" id="tgHeaderStatus">last seen recently</div>
                </div>
            </div>
            <div class="tg-header-right">
                <a href="tel:" class="tg-icon-btn" id="tgHeaderCallBtn" title="Call Customer" style="display:none">
                    <i class="fa fa-phone"></i>
                </a>
                <button type="button" class="tg-icon-btn" id="tgHeaderFolderBtn" title="Add / Move Customer to Folder" aria-label="Add to Folder">
                    <i class="fa fa-folder-open-o"></i>
                </button>
                <button type="button" class="tg-icon-btn" id="tgChatMenuBtn" title="More options" aria-label="More options">
                    <i class="fa fa-ellipsis-v"></i>
                </button>
            </div>
        </div>

        <!-- Chat Conversation Body (Telegram Doodle Pattern Wallpaper) -->
        <div class="tg-chat-body" id="tgChatMessages">
            <div class="tg-chat-placeholder" id="tgDesktopPlaceholder">
                <div class="tg-placeholder-badge">Select a chat to start messaging</div>
            </div>
        </div>

        <!-- Telegram Bottom Message Composer Bar (Strictly Fixed at the Bottom) -->
        <form class="tg-composer-bar" id="tgComposerForm" style="display:none" onsubmit="return false;">
            <!-- Regular Text Input Container (Capsule with Emoji, Text & Paperclip inside) -->
            <div class="tg-composer-input-wrap">
                <button type="button" class="tg-composer-inner-btn" id="tgEmojiBtn" title="Emoji" aria-label="Insert Emoji">
                    <i class="fa fa-smile-o"></i>
                </button>
                <input type="text" class="tg-composer-input" id="tgMessageInput" placeholder="Message" autocomplete="off">
                <button type="button" class="tg-composer-inner-btn" id="tgAttachBtn" title="Attach file" aria-label="Attach">
                    <i class="fa fa-paperclip"></i>
                </button>
            </div>

            <!-- Voice Recording Panel -->
            <div class="tg-voice-recording-panel" id="tgVoicePanel">
                <div class="tg-rec-dot"></div>
                <div class="tg-rec-timer" id="tgVoiceTimer">00:00</div>
                <div class="tg-rec-wave">
                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                </div>
                <button type="button" class="tg-rec-cancel-btn" id="tgVoiceCancelBtn" title="Cancel"><i class="fa fa-trash"></i></button>
            </div>

            <!-- Send or Microphone Action Button (Telegram Round Blue Button) -->
            <button type="submit" class="tg-send-action-btn" id="tgActionSendBtn" title="Record Voice">
                <i class="fa fa-microphone" id="tgActionSendIcon"></i>
            </button>
        </form>

        <!-- Hidden File Inputs -->
        <input type="file" id="tgFileInputImage" accept="image/*" style="display:none">
        <input type="file" id="tgFileInputDoc" style="display:none">

        <!-- Attachment Menu Sheet -->
        <div class="tg-attach-sheet" id="tgAttachSheet">
            <button type="button" class="tg-attach-item invoice" id="tgAttachInvoice">
                <i class="fa fa-file-text-o"></i> Send Invoice
            </button>
            <button type="button" class="tg-attach-item photo" id="tgAttachPhoto">
                <i class="fa fa-picture-o"></i> Photo / Camera
            </button>
            <button type="button" class="tg-attach-item file" id="tgAttachFile">
                <i class="fa fa-file-o"></i> Document
            </button>
            <button type="button" class="tg-attach-item location" id="tgAttachLocation">
                <i class="fa fa-map-marker"></i> Location
            </button>
        </div>

        <!-- Quick Emoji Picker Panel -->
        <div class="tg-emoji-panel hidden" id="tgEmojiPanel">
            <span class="tg-emoji-item">❤️</span>
            <span class="tg-emoji-item">👍</span>
            <span class="tg-emoji-item">😂</span>
            <span class="tg-emoji-item">🙏</span>
            <span class="tg-emoji-item">👏</span>
            <span class="tg-emoji-item">🔥</span>
            <span class="tg-emoji-item">💰</span>
            <span class="tg-emoji-item">📦</span>
            <span class="tg-emoji-item">📞</span>
            <span class="tg-emoji-item">🤝</span>
            <span class="tg-emoji-item">🏍️</span>
            <span class="tg-emoji-item">🚗</span>
            <span class="tg-emoji-item">📍</span>
            <span class="tg-emoji-item">✅</span>
            <span class="tg-emoji-item">❌</span>
            <span class="tg-emoji-item">⏳</span>
        </div>

        <!-- Chat Header Kebab Dropdown -->
        <div class="tg-dropdown-menu" id="tgChatDropdown">
            <a class="tg-dropdown-item" id="tgMenuAddToFolder" href="javascript:void(0)"><i class="fa fa-folder-open-o"></i> Add to Folder...</a>
            <a class="tg-dropdown-item" id="tgMenuSendInvoice" href="javascript:void(0)"><i class="fa fa-file-text-o"></i> Send Invoice</a>
            <a class="tg-dropdown-item" id="tgMenuQuickPay" href="javascript:void(0)"><i class="fa fa-money"></i> Quick Pay</a>
            <a class="tg-dropdown-item" id="tgMenuViewCustomer" href="javascript:void(0)" target="_blank"><i class="fa fa-user"></i> View Profile</a>
            <a class="tg-dropdown-item" id="tgMenuMarkUnread" href="javascript:void(0)"><i class="fa fa-envelope-o"></i> Mark as unread</a>
            <a class="tg-dropdown-item" id="tgMenuRefreshChat" href="javascript:void(0)"><i class="fa fa-refresh"></i> Refresh Thread</a>
        </div>
    </main>
</div>

<!-- ================================================================== -->
<!-- MODAL 1: MANAGE CHAT FOLDERS (List, Edit, Delete)                   -->
<!-- ================================================================== -->
<div class="tg-modal-overlay" id="tgFoldersModal">
    <div class="tg-modal-card">
        <div class="tg-modal-head">
            <h3 class="tg-modal-title"><i class="fa fa-folder-open" style="color:#2481cc"></i> Chat Folders</h3>
            <button type="button" class="tg-modal-close" data-close-modal="#tgFoldersModal">&times;</button>
        </div>
        <div class="tg-modal-body">
            <div style="font-size:12.5px;color:#64748b;margin-bottom:14px">
                Create and organize chat folders to group customers, invoices, and installments.
            </div>
            <div id="tgFoldersListContainer">
                <div style="text-align:center;padding:20px;color:#94a3b8"><i class="fa fa-circle-o-notch fa-spin"></i> Loading folders...</div>
            </div>
        </div>
        <div class="tg-modal-foot">
            <button type="button" class="tg-btn-secondary" data-close-modal="#tgFoldersModal">Close</button>
            <button type="button" class="tg-btn-primary" id="tgBtnCreateFolderOpen"><i class="fa fa-plus"></i> New Folder</button>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- MODAL 2: CREATE / EDIT FOLDER FORM (Name + Customer Selection)     -->
<!-- ================================================================== -->
<div class="tg-modal-overlay" id="tgFolderEditModal">
    <div class="tg-modal-card">
        <div class="tg-modal-head">
            <h3 class="tg-modal-title"><i class="fa fa-folder" style="color:#2481cc"></i> <span id="tgFolderModalTitle">Create Folder</span></h3>
            <button type="button" class="tg-modal-close" data-close-modal="#tgFolderEditModal">&times;</button>
        </div>
        <div class="tg-modal-body">
            <input type="hidden" id="tgEditFolderId" value="">
            <div style="margin-bottom:14px">
                <label style="font-size:12.5px;font-weight:700;color:#334155;margin-bottom:4px;display:block">Folder Name</label>
                <input type="text" id="tgFolderInputName" placeholder="e.g. VIP, Special, Bad Debt, Branch..." style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;outline:none;font-size:14px" required>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                <label style="font-size:12.5px;font-weight:700;color:#334155;margin:0">Add Customers</label>
                <div>
                    <button type="button" id="tgBtnCustSelectAll" style="border:none;background:none;color:#2481cc;font-size:12px;font-weight:700;cursor:pointer">Select All</button>
                    <span style="color:#cbd5e1;margin:0 4px">·</span>
                    <button type="button" id="tgBtnCustClearAll" style="border:none;background:none;color:#64748b;font-size:12px;font-weight:600;cursor:pointer">Clear</button>
                </div>
            </div>
            <input type="text" id="tgFolderCustSearch" placeholder="Search customer name or phone..." style="width:100%;height:34px;border:1px solid #e2e8f0;border-radius:8px;padding:0 10px;outline:none;font-size:12.5px;background:#f8fafc">

            <div class="tg-cust-select-list" id="tgCustSelectList">
                <!-- Populated dynamically with customer checkboxes -->
            </div>
        </div>
        <div class="tg-modal-foot">
            <button type="button" class="tg-btn-secondary" data-close-modal="#tgFolderEditModal">Cancel</button>
            <button type="button" class="tg-btn-primary" id="tgBtnSaveFolder">Save Folder</button>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!-- MODAL 3: ADD CURRENT CUSTOMER TO FOLDERS                            -->
<!-- ================================================================== -->
<div class="tg-modal-overlay" id="tgCustomerFoldersModal">
    <div class="tg-modal-card" style="max-width:380px">
        <div class="tg-modal-head">
            <h3 class="tg-modal-title"><i class="fa fa-folder-open-o" style="color:#2481cc"></i> Add to Folder</h3>
            <button type="button" class="tg-modal-close" data-close-modal="#tgCustomerFoldersModal">&times;</button>
        </div>
        <div class="tg-modal-body">
            <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:12px;padding:8px 12px;background:#f1f5f9;border-radius:8px" id="tgCustomerFolderNameDisplay">
                Customer Name
            </div>
            <div style="font-size:12px;color:#64748b;margin-bottom:8px">Select folders for this chat:</div>
            <div id="tgCustomerFolderCheckboxes">
                <!-- Checkboxes populated dynamically -->
            </div>
        </div>
        <div class="tg-modal-foot">
            <button type="button" class="tg-btn-secondary" data-close-modal="#tgCustomerFoldersModal">Cancel</button>
            <button type="button" class="tg-btn-primary" id="tgBtnSaveCustomerFolders">Save</button>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="tg-viewer-modal" id="tgViewerModal">
    <button type="button" class="tg-viewer-close" id="tgViewerClose">&times;</button>
    <img src="" alt="Full view" id="tgViewerImage">
</div>
@endsection

@section('loan_js')
<script>
(function($){
    var csrf = '{{ csrf_token() }}';
    var apiBaseUrl = '{{ url("loan-management/telegram-chat-api/chats") }}';
    var apiFolderBaseUrl = '{{ url("loan-management/telegram-chat-api/folders") }}';
    var pollMs = {{ (int) config("loanmanagement.chat_polling_seconds", 5) * 1000 }};
    var initialThreadId = @json($initialThreadId ?? null);
    var initialCustomerId = @json($initialCustomerId ?? null);

    var contacts = [];
    var folders = [];
    var activeContact = null;
    var activeThreadId = null;
    var activeThreadMarkedUnread = false;
    var currentFilter = 'all';
    var pollTimer = null;
    var isFetchingList = false;
    var isFetchingThread = false;
    var pendingListXhr = null;
    var pendingThreadXhr = null;
    var queuedListLoadOptions = null;
    var chatListSnapshotVersion = '';
    var contactListRenderSignature = '';
    var renderedMessageIds = {};
    var notificationSoundUrl = '{{ asset("audio/success.mp3") }}';
    var notificationAudio = null;
    var notificationAudioUnlocked = false;
    var chatListUnreadInitialized = false;
    var chatListUnreadCounts = {};
    var threadMessageSeenInitialized = false;
    var threadMessageSeen = {};
    var messagePageSize = 25;
    var threadMessages = [];
    var hasMoreOlderMessages = false;
    var loadingOlderMessages = false;
    var autoLoadOlderThreshold = 120;
    var autoLoadOlderTimer = null;

    // Audio Voice Player State
    var currentAudio = null;
    var currentAudioMsgId = null;

    // MediaRecorder State for Voice Recording
    var mediaRecorder = null;
    var voiceStream = null;
    var voiceChunks = [];
    var voiceTimer = null;
    var voiceSeconds = 0;
    var isRecording = false;

    // Palette for avatar backgrounds (authentic Telegram colors)
    var avatarColors = ['#e56c55', '#f68b36', '#54b73b', '#2ca5e0', '#4ba3e3', '#916dd5', '#d665aa', '#3ea3b8'];

    function getAvatarColor(name){
        if (!name) return avatarColors[0];
        var hash = 0;
        for (var i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        var index = Math.abs(hash) % avatarColors.length;
        return avatarColors[index];
    }

    function getInitials(name){
        if (!name) return 'TG';
        var parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    }

    function esc(s){
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function ensureNotificationAudio(){
        if (!notificationAudio) {
            notificationAudio = new Audio(notificationSoundUrl);
            notificationAudio.preload = 'auto';
        }
        return notificationAudio;
    }

    function unlockNotificationAudio(){
        if (notificationAudioUnlocked) return;
        var audio = ensureNotificationAudio();
        audio.muted = true;
        var playPromise = audio.play();
        if (playPromise && playPromise.then) {
            playPromise.then(function(){
                audio.pause();
                audio.currentTime = 0;
                audio.muted = false;
                notificationAudioUnlocked = true;
            }).catch(function(){
                audio.muted = false;
            });
        } else {
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            notificationAudioUnlocked = true;
        }
    }

    function playChatNotificationSound(){
        var audio = ensureNotificationAudio();
        audio.muted = false;
        audio.currentTime = 0;
        var playPromise = audio.play();
        if (playPromise && playPromise.catch) {
            playPromise.catch(function(){});
        }
    }

    function contactSoundKey(contact){
        return String((contact && (contact.id || contact.thread_id || contact.customer_id)) || '');
    }

    function rememberUnreadCounts(rows){
        (rows || []).forEach(function(c){
            var key = contactSoundKey(c);
            if (key) chatListUnreadCounts[key] = Number(c.unread_count || 0);
        });
    }

    function shouldPlayForUnreadIncrease(rows){
        if (!chatListUnreadInitialized) {
            rememberUnreadCounts(rows);
            chatListUnreadInitialized = true;
            return false;
        }

        var shouldPlay = false;
        (rows || []).forEach(function(c){
            var key = contactSoundKey(c);
            if (!key) return;
            var current = Number(c.unread_count || 0);
            var previous = Number(chatListUnreadCounts[key] || 0);
            if (current > previous) {
                shouldPlay = true;
            }
            chatListUnreadCounts[key] = current;
        });

        return shouldPlay;
    }

    function isIncomingMessage(message){
        return !(message && (message.is_own || message.sender_type === 'staff' || message.sender_type === 'admin'));
    }

    function messageSoundKey(message){
        if (!message) return '';
        return String(message.id || [message.sender_type, message.sender_id, message.created_at, message.message_type, message.message].join('|'));
    }

    function shouldPlayForNewIncomingMessages(messages){
        if (!threadMessageSeenInitialized) {
            threadMessageSeen = {};
            (messages || []).forEach(function(m){
                var key = messageSoundKey(m);
                if (key) threadMessageSeen[key] = true;
            });
            threadMessageSeenInitialized = true;
            return false;
        }

        var shouldPlay = false;
        (messages || []).forEach(function(m){
            var key = messageSoundKey(m);
            if (!key) return;
            if (!threadMessageSeen[key] && isIncomingMessage(m)) {
                shouldPlay = true;
            }
            threadMessageSeen[key] = true;
        });

        return shouldPlay;
    }

    function sortMessagesById(messages){
        return (messages || []).sort(function(a, b){
            return Number(a.id || 0) - Number(b.id || 0);
        });
    }

    function contactListSignature(rows){
        return (rows || []).map(function(c){
            return [
                c.id || '',
                c.customer_id || '',
                c.last_message_at || '',
                c.last_message_type || '',
                c.last_message || '',
                Number(c.unread_count || 0)
            ].join('|');
        }).join('~');
    }

    function mergeThreadMessages(existing, incoming){
        var byId = {};
        (existing || []).concat(incoming || []).forEach(function(m){
            if (!m) return;
            byId[String(m.id || messageSoundKey(m))] = m;
        });

        return sortMessagesById(Object.keys(byId).map(function(key){ return byId[key]; }));
    }

    function oldestLoadedMessageId(){
        return threadMessages.length ? Number(threadMessages[0].id || 0) : 0;
    }

    function newestLoadedMessageId(){
        return threadMessages.length ? Number(threadMessages[threadMessages.length - 1].id || 0) : 0;
    }

    function loadOlderMessages(){
        var oldestId = oldestLoadedMessageId();
        if (activeThreadId && oldestId && hasMoreOlderMessages && !loadingOlderMessages && !isFetchingThread) {
            loadThreadMessages(activeThreadId, false, {beforeId: oldestId});
        }
    }

    function scheduleAutoLoadOlderMessages(){
        if (autoLoadOlderTimer) return;
        autoLoadOlderTimer = window.setTimeout(function(){
            autoLoadOlderTimer = null;
            if ($('#tgChatMessages').scrollTop() <= autoLoadOlderThreshold) {
                loadOlderMessages();
            }
        }, 120);
    }

    function formatTime(dateStr){
        if (!dateStr) return '';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        var hours = d.getHours();
        var mins = d.getMinutes();
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        mins = mins < 10 ? '0' + mins : mins;
        return hours + ':' + mins + ' ' + ampm;
    }

    function formatDateOrTime(dateStr){
        if (!dateStr) return '';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        var now = new Date();
        var isToday = d.toDateString() === now.toDateString();
        if (isToday) return formatTime(dateStr);

        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        if (d.getFullYear() === now.getFullYear()) {
            return monthNames[d.getMonth()] + ' ' + d.getDate();
        }
        var day = d.getDate() < 10 ? '0' + d.getDate() : d.getDate();
        var m = (d.getMonth() + 1) < 10 ? '0' + (d.getMonth() + 1) : (d.getMonth() + 1);
        return day + '.' + m + '.' + String(d.getFullYear()).slice(-2);
    }

    function formatDuration(sec){
        sec = Math.max(0, parseInt(sec, 10) || 0);
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }

    // -------------------------------------------------------------
    // FOLDERS SERVICE CALLS & UI
    // -------------------------------------------------------------
    function loadFolders(){
        $.ajax({
            url: apiFolderBaseUrl,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                folders = resp && resp.data ? (Array.isArray(resp.data) ? resp.data : []) : [];
                contactListRenderSignature = '';
                renderFolderPills();
                updatePillBadges();
            }
        });
    }

    function renderFolderPills(){
        var $container = $('#tgDynamicFolderPills');
        var html = '';

        folders.forEach(function(f){
            var fid = String(f.id);
            var isActive = (currentFilter === fid);
            var count = countMatchingFolder(f);
            html += '<button type="button" class="tg-pill ' + (isActive ? 'active' : '') + '" data-filter="' + esc(fid) + '">' +
                '<span>' + esc(f.name) + '</span>' +
                '<span class="tg-pill-badge">' + count + '</span>' +
            '</button>';
        });

        $container.html(html);
    }

    function countMatchingFolder(folder){
        var cids = (folder.customer_ids || []).map(Number);
        return contacts.filter(function(c){
            var cid = Number(c.customer_id);
            if (folder.id === 'personal') {
                return !!c.telegram_linked || cids.indexOf(cid) >= 0;
            } else if (folder.id === 'invoices') {
                return !!c.invoice_no || cids.indexOf(cid) >= 0;
            } else if (folder.id === 'installments') {
                return !!c.loan_id || !!c.installment_no || cids.indexOf(cid) >= 0;
            } else {
                return cids.indexOf(cid) >= 0;
            }
        }).length;
    }

    var folderEditingCustomerIds = [];

    // Open Folders Manager Modal
    function openFoldersModal(){
        var $list = $('#tgFoldersListContainer');
        var html = '';

        if (!folders.length) {
            html = '<div style="text-align:center;padding:20px;color:#94a3b8">No folders yet. Click New Folder to create one.</div>';
        } else {
            folders.forEach(function(f){
                var isSystem = (f.type === 'system');
                var count = (f.customer_ids || []).length;
                var countLabel = isSystem ? countMatchingFolder(f) + ' chats (auto/custom)' : count + ' customer(s)';

                html += '<div class="tg-folder-list-item" data-folder-id="' + esc(f.id) + '">' +
                    '<div class="tg-folder-item-left">' +
                        '<div class="tg-folder-item-icon"><i class="fa fa-folder"></i></div>' +
                        '<div style="min-width:0">' +
                            '<div class="tg-folder-item-name">' + esc(f.name) + (isSystem ? ' <span style="font-size:10px;background:#e2e8f0;color:#475569;padding:1px 6px;border-radius:6px;font-weight:600">Default</span>' : '') + '</div>' +
                            '<div class="tg-folder-item-count">' + countLabel + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="tg-folder-item-actions">' +
                        '<button type="button" class="tg-btn-edit js-edit-folder" data-folder-id="' + esc(f.id) + '" title="Edit Folder"><i class="fa fa-pencil"></i> Edit</button>' +
                        '<button type="button" class="tg-btn-danger js-delete-folder" data-folder-id="' + esc(f.id) + '" title="Delete Folder"><i class="fa fa-trash"></i></button>' +
                    '</div>' +
                '</div>';
            });
        }

        $list.html(html);
        $('#tgFoldersModal').addClass('open');
    }

    // Open Create or Edit Folder Modal
    function openFolderEditModal(folderId){
        var isEdit = !!folderId;
        var folder = isEdit ? folders.find(function(f){ return String(f.id) === String(folderId); }) : null;

        $('#tgEditFolderId').val(isEdit ? folder.id : '');
        $('#tgFolderModalTitle').text(isEdit ? 'Edit Folder' : 'Create Folder');
        $('#tgFolderInputName').val(isEdit ? folder.name : '');
        $('#tgFolderCustSearch').val('');

        folderEditingCustomerIds = isEdit ? (folder.customer_ids || []).map(Number) : [];

        renderCustomerChecklist();
        $('#tgFoldersModal').removeClass('open');
        $('#tgFolderEditModal').addClass('open');
        $('#tgFolderInputName').focus();
    }

    function renderCustomerChecklist(){
        var $list = $('#tgCustSelectList');
        var q = ($('#tgFolderCustSearch').val() || '').toLowerCase().trim();

        if (!contacts.length) {
            $list.html('<div style="text-align:center;padding:20px;color:#94a3b8">No customers loaded.</div>');
            return;
        }

        var html = '';
        contacts.forEach(function(c){
            var cid = Number(c.customer_id);
            var name = c.display_name || c.customer_name || 'Customer';
            var phone = c.customer_phone || '';
            var sub = c.display_subtitle || phone;

            if (q) {
                var hay = (name + ' ' + phone + ' ' + sub).toLowerCase();
                if (hay.indexOf(q) === -1) return;
            }

            var isChecked = folderEditingCustomerIds.indexOf(cid) >= 0;
            var color = getAvatarColor(name);
            var initials = getInitials(name);

            html += '<label class="tg-cust-select-item">' +
                '<input type="checkbox" class="js-folder-cust-cb" value="' + cid + '" ' + (isChecked ? 'checked' : '') + '>' +
                '<div class="tg-cust-mini-avatar" style="background:' + color + '">' + initials + '</div>' +
                '<div class="tg-cust-info">' +
                    '<div class="tg-cust-name">' + esc(name) + '</div>' +
                    '<div class="tg-cust-sub">' + esc(sub) + '</div>' +
                '</div>' +
            '</label>';
        });

        if (!html) {
            html = '<div style="text-align:center;padding:16px;color:#94a3b8">No matching customers</div>';
        }

        $list.html(html);
    }

    // Persistent customer checkbox tracking in Folder Edit modal
    $(document).on('change', '.js-folder-cust-cb', function(){
        var cid = Number($(this).val());
        if ($(this).is(':checked')) {
            if (folderEditingCustomerIds.indexOf(cid) === -1) {
                folderEditingCustomerIds.push(cid);
            }
        } else {
            folderEditingCustomerIds = folderEditingCustomerIds.filter(function(id){ return id !== cid; });
        }
    });

    $('#tgBtnCustSelectAll').on('click', function(){
        $('.js-folder-cust-cb').each(function(){
            var cid = Number($(this).val());
            $(this).prop('checked', true);
            if (folderEditingCustomerIds.indexOf(cid) === -1) {
                folderEditingCustomerIds.push(cid);
            }
        });
    });

    $('#tgBtnCustClearAll').on('click', function(){
        $('.js-folder-cust-cb').each(function(){
            var cid = Number($(this).val());
            $(this).prop('checked', false);
            folderEditingCustomerIds = folderEditingCustomerIds.filter(function(id){ return id !== cid; });
        });
    });

    $('#tgFolderCustSearch').on('input', function(){
        renderCustomerChecklist();
    });

    // Save Folder
    $('#tgBtnSaveFolder').on('click', function(){
        var folderId = $('#tgEditFolderId').val();
        var name = $('#tgFolderInputName').val().trim();
        if (!name) {
            alert('Please enter a folder name.');
            $('#tgFolderInputName').focus();
            return;
        }

        var customerIds = folderEditingCustomerIds;
        var isEdit = !!folderId;
        var url = isEdit ? (apiFolderBaseUrl + '/' + folderId) : apiFolderBaseUrl;
        var method = isEdit ? 'PUT' : 'POST';

        var $btn = $(this).prop('disabled', true).text('Saving...');

        $.ajax({
            url: url,
            method: method,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            data: {
                _token: csrf,
                name: name,
                customer_ids: customerIds
            },
            success: function(resp){
                $('#tgFolderEditModal').removeClass('open');
                loadFolders();
                loadChatList(true);
            },
            error: function(err){
                alert('Cannot save folder. ' + (err.responseJSON && err.responseJSON.message ? err.responseJSON.message : ''));
            },
            complete: function(){
                $btn.prop('disabled', false).text('Save Folder');
            }
        });
    });

    // Delete Folder
    $(document).on('click', '.js-delete-folder', function(){
        var folderId = $(this).data('folder-id');
        if (!confirm('Are you sure you want to delete this folder? (Customers will not be deleted).')) {
            return;
        }

        $.ajax({
            url: apiFolderBaseUrl + '/' + folderId,
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            data: { _token: csrf },
            success: function(){
                if (currentFilter === String(folderId)) {
                    currentFilter = 'all';
                    $('#tgFilterPills .tg-pill').removeClass('active');
                    $('#tgFilterPills .tg-pill[data-filter="all"]').addClass('active');
                }
                loadFolders();
                openFoldersModal();
                loadChatList(true);
            }
        });
    });

    // Edit button click in folder list
    $(document).on('click', '.js-edit-folder', function(){
        var folderId = $(this).data('folder-id');
        openFolderEditModal(folderId);
    });

    // Open "Add customer to folder" modal from active chat dropdown or header
    $('#tgMenuAddToFolder, #tgHeaderFolderBtn').on('click', function(){
        $('#tgChatDropdown').removeClass('open');
        if (!activeContact || !activeContact.customer_id) {
            alert('Select a customer chat first.');
            return;
        }

        var cid = Number(activeContact.customer_id);
        var cname = activeContact.display_name || activeContact.customer_name || 'Customer';

        openCustomerFoldersModal(cid, cname);
    });

    // Quick Folder button from chat list item
    $(document).on('click', '.js-quick-folder-btn', function(e){
        e.stopPropagation();
        var cid = Number($(this).data('customer-id'));
        var cname = $(this).data('customer-name') || 'Customer';
        if (!cid) return;

        openCustomerFoldersModal(cid, cname);
    });

    function openCustomerFoldersModal(customerId, customerName){
        $('#tgCustomerFolderNameDisplay').text(customerName);
        $('#tgCustomerFoldersModal').data('target-cid', customerId);

        $.ajax({
            url: '{{ url("loan-management/telegram-chat-api/customers") }}/' + customerId + '/folders',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                var activeFids = (resp && resp.data && Array.isArray(resp.data.folder_ids)) ? resp.data.folder_ids.map(String) : [];
                renderCustomerFolderCheckboxes(activeFids);
                $('#tgCustomerFoldersModal').addClass('open');
            }
        });
    }

    function renderCustomerFolderCheckboxes(activeFids){
        var $box = $('#tgCustomerFolderCheckboxes').empty();
        if (!folders.length) {
            $box.html('<div style="text-align:center;padding:12px;color:#94a3b8">No folders available. Create a folder first.</div>');
            return;
        }

        var html = '';
        folders.forEach(function(f){
            var isChecked = activeFids.indexOf(String(f.id)) >= 0;
            html += '<label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;background:#f8fafc;margin-bottom:6px;cursor:pointer">' +
                '<input type="checkbox" class="js-cust-folder-cb" value="' + esc(f.id) + '" ' + (isChecked ? 'checked' : '') + ' style="width:17px;height:17px;accent-color:#2481cc">' +
                '<span style="font-weight:700;font-size:13.5px;color:#0f172a;flex:1"><i class="fa fa-folder-o" style="color:#2481cc;margin-right:6px"></i>' + esc(f.name) + '</span>' +
            '</label>';
        });

        $box.html(html);
    }

    // Save Customer Folders
    $('#tgBtnSaveCustomerFolders').on('click', function(){
        var cid = $('#tgCustomerFoldersModal').data('target-cid') || (activeContact ? Number(activeContact.customer_id) : null);
        if (!cid) return;

        var selectedFids = [];
        $('.js-cust-folder-cb:checked').each(function(){
            selectedFids.push($(this).val());
        });

        var $btn = $(this).prop('disabled', true).text('Saving...');

        $.ajax({
            url: '{{ url("loan-management/telegram-chat-api/customers") }}/' + cid + '/folders',
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            data: {
                _token: csrf,
                folder_ids: selectedFids
            },
            success: function(){
                $('#tgCustomerFoldersModal').removeClass('open');
                loadFolders();
                loadChatList(true);
            },
            complete: function(){
                $btn.prop('disabled', false).text('Save');
            }
        });
    });

    // Customer search in Folder Edit modal
    $('#tgFolderCustSearch').on('input', function(){
        var currentSelected = [];
        $('.js-folder-cust-cb:checked').each(function(){
            currentSelected.push(Number($(this).val()));
        });
        renderCustomerChecklist(currentSelected);
    });

    $('#tgBtnCustSelectAll').on('click', function(){
        $('.js-folder-cust-cb').prop('checked', true);
    });
    $('#tgBtnCustClearAll').on('click', function(){
        $('.js-folder-cust-cb').prop('checked', false);
    });

    // Close Modals
    $(document).on('click', '[data-close-modal]', function(){
        var target = $(this).data('close-modal');
        $(target).removeClass('open');
    });
    $('.tg-modal-overlay').on('click', function(e){
        if (e.target === this) $(this).removeClass('open');
    });

    $('#tgBtnManageFolders, #tgActionManageFoldersDropdown').on('click', function(){
        $('#tgListDropdown, #tgComposeDropdown').removeClass('open');
        openFoldersModal();
    });
    $('#tgBtnCreateFolderOpen, #tgBtnDirectNewFolder, #tgActionNewFolderDropdown').on('click', function(){
        $('#tgListDropdown, #tgComposeDropdown').removeClass('open');
        openFolderEditModal(null);
    });
    $('#tgHeaderFolderBtn').on('click', function(){
        $('#tgMenuAddToFolder').trigger('click');
    });

    // -------------------------------------------------------------
    // LOAD CONTACTS / CHAT LIST
    // -------------------------------------------------------------
    function loadChatList(silent, options){
        options = options || {};
        if (isFetchingList && !silent) return;
        if (pendingListXhr && pendingListXhr.readyState !== 4) return;
        isFetchingList = true;
        silent = !!silent;

        var params = {
            search: $('#tgSearchInput').val().trim()
        };

        if (options.snapshot) {
            params.snapshot = 1;
        }

        pendingListXhr = $.ajax({
            url: apiBaseUrl,
            data: params,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                if (options.snapshot) {
                    var snapshot = resp && resp.data ? resp.data : {};
                    if (snapshot.version && snapshot.version === chatListSnapshotVersion) {
                        return;
                    }
                    chatListSnapshotVersion = snapshot.version || '';
                    queuedListLoadOptions = {suppressSound: !!options.suppressSound};
                    return;
                }

                contacts = resp && resp.data ? (Array.isArray(resp.data) ? resp.data : (resp.data.data || [])) : [];
                var signature = contactListSignature(contacts);
                if (!options.suppressSound && shouldPlayForUnreadIncrease(contacts)) {
                    playChatNotificationSound();
                }
                if (signature === contactListRenderSignature) {
                    return;
                }
                contactListRenderSignature = signature;
                renderChatList();
                updatePillBadges();
            },
            complete: function(){
                isFetchingList = false;
                pendingListXhr = null;
                if (queuedListLoadOptions) {
                    var nextOptions = queuedListLoadOptions;
                    queuedListLoadOptions = null;
                    window.setTimeout(function(){
                        loadChatList(true, nextOptions);
                    }, 0);
                }
            }
        });
    }

    function updatePillBadges(){
        $('#tgBadgeAll').text(contacts.length);
        $('#tgBadgeUnread').text(contacts.filter(function(c){
            return Number(c.unread_count || 0) > 0;
        }).length);
        renderFolderPills();
    }

    function filterContacts(){
        var q = ($('#tgSearchInput').val() || '').toLowerCase().trim();

        return contacts.filter(function(c){
            var cid = Number(c.customer_id);

            // Folder / Tab filtering
            if (currentFilter && currentFilter !== 'all') {
                if (currentFilter === 'unread') {
                    if (Number(c.unread_count || 0) <= 0) return false;
                } else if (currentFilter.startsWith('loc_')) {
                    var locId = currentFilter.replace('loc_', '');
                    if (String(c.location_id) !== String(locId)) return false;
                } else {
                    var folder = folders.find(function(f){ return String(f.id) === String(currentFilter); });
                    if (folder) {
                        var cids = (folder.customer_ids || []).map(Number);
                        if (folder.id === 'personal') {
                            if (!c.telegram_linked && cids.indexOf(cid) === -1) return false;
                        } else if (folder.id === 'invoices') {
                            if (!c.invoice_no && cids.indexOf(cid) === -1) return false;
                        } else if (folder.id === 'installments') {
                            if (!c.loan_id && !c.installment_no && cids.indexOf(cid) === -1) return false;
                        } else {
                            if (cids.indexOf(cid) === -1) return false;
                        }
                    }
                }
            }

            if (q) {
                var hay = [c.display_name, c.customer_name, c.customer_phone, c.invoice_no, c.loan_number, c.last_message].join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    function renderChatList(){
        var $list = $('#tgChatList');
        var filtered = filterContacts();
        var foldersByCustomer = {};

        folders.forEach(function(f){
            (f.customer_ids || []).forEach(function(customerId){
                var key = String(Number(customerId));
                if (!foldersByCustomer[key]) foldersByCustomer[key] = [];
                foldersByCustomer[key].push(f);
            });
        });

        if (!filtered.length) {
            $list.html('<div class="tg-empty-chats"><i class="fa fa-telegram"></i><div>No chats in this folder</div></div>');
            return;
        }

        var html = '';
        filtered.forEach(function(c){
            var isActive = (activeThreadId && String(c.id) === String(activeThreadId)) || (activeContact && String(c.customer_id) === String(activeContact.customer_id));
            var color = getAvatarColor(c.display_name || c.customer_name);
            var initials = getInitials(c.display_name || c.customer_name);
            var phone = c.customer_phone || '';
            var invoiceNo = c.invoice_no || c.loan_number || '';
            var avatarHtml = c.avatar_url
                ? '<img src="' + esc(c.avatar_url) + '" alt="">'
                : initials;
            var customerMetaHtml = '';

            if (phone) {
                customerMetaHtml += '<span title="Phone"><i class="fa fa-phone"></i>' + esc(phone) + '</span>';
            }
            if (invoiceNo) {
                customerMetaHtml += '<span title="Invoice"><i class="fa fa-file-text-o"></i>' + esc(invoiceNo) + '</span>';
            }
            if (customerMetaHtml) {
                customerMetaHtml = '<div class="tg-item-customer-meta">' + customerMetaHtml + '</div>';
            }

            // Last message snippet with icon
            var snippetIcon = '';
            var snippetText = c.last_message || 'No messages yet';
            if (c.last_message_type === 'image') {
                snippetIcon = '<i class="fa fa-camera"></i> ';
                snippetText = 'Photo';
            } else if (c.last_message_type === 'audio') {
                snippetIcon = '<i class="fa fa-microphone"></i> ';
                snippetText = 'Voice message';
            } else if (c.last_message_type === 'file') {
                snippetIcon = '<i class="fa fa-paperclip"></i> ';
                snippetText = 'Document';
            } else if (c.last_message_type === 'location') {
                snippetIcon = '<i class="fa fa-map-marker"></i> ';
                snippetText = 'Location';
            }

            // Right column: Time + Badge or Checkmark
            var timeHtml = formatDateOrTime(c.last_message_at);
            var badgeHtml = '';
            if (c.unread_count > 0) {
                badgeHtml = '<span class="tg-item-badge">' + c.unread_count + '</span>';
            } else if (c.last_message) {
                badgeHtml = '<span class="tg-check-icon"><i class="fa fa-check"></i><i class="fa fa-check" style="margin-left:-4px"></i></span>';
            }

            // Folders customer belongs to
            var cid = Number(c.customer_id);
            var custFolders = foldersByCustomer[String(cid)] || [];
            var folderTagsHtml = '';
            if (custFolders.length > 0) {
                custFolders.forEach(function(cf){
                    folderTagsHtml += '<span class="tg-item-folder-tag">' + esc(cf.name) + '</span>';
                });
            }

            var quickFolderBtn = '<button type="button" class="tg-item-quick-folder js-quick-folder-btn" data-customer-id="' + cid + '" data-customer-name="' + esc(c.display_name || c.customer_name) + '" title="Assign to folder"><i class="fa fa-folder-o"></i></button>';

            html += '<div class="tg-chat-item ' + (isActive ? 'active' : '') + '" data-thread-id="' + (c.id || '') + '" data-customer-id="' + (c.customer_id || '') + '">' +
                '<div class="tg-avatar-wrap">' +
                    '<div class="tg-avatar" style="background:' + color + '">' + avatarHtml + '</div>' +
                    (c.telegram_linked ? '<span class="tg-avatar-dot" title="Linked to Telegram"></span>' : '') +
                '</div>' +
                '<div class="tg-item-body">' +
                    '<div class="tg-item-row-top">' +
                        '<div class="tg-item-name" style="display:flex;align-items:center;gap:3px;flex-wrap:wrap">' + esc(c.display_name || c.customer_name || 'Customer') + folderTagsHtml + '</div>' +
                        '<div style="display:flex;align-items:center;gap:2px">' +
                            quickFolderBtn +
                            '<div class="tg-item-time">' + timeHtml + '</div>' +
                        '</div>' +
                    '</div>' +
                    customerMetaHtml +
                    '<div class="tg-item-row-bottom">' +
                        '<div class="tg-item-preview">' + snippetIcon + '<span>' + esc(snippetText) + '</span></div>' +
                        badgeHtml +
                    '</div>' +
                '</div>' +
            '</div>';
        });

        $list.html(html);
    }

    // -------------------------------------------------------------
    // OPEN CONVERSATION (SCREEN 2)
    // -------------------------------------------------------------
    function openConversation(threadId, customerId){
        // Mobile layout state
        $('#tgAppWrapper').addClass('in-conversation');
        $('body').addClass('tg-viewing-chat');

        activeThreadId = threadId;
        activeThreadMarkedUnread = false;
        if (pendingThreadXhr && pendingThreadXhr.readyState !== 4) {
            pendingThreadXhr.abort();
        }
        threadMessageSeenInitialized = false;
        threadMessageSeen = {};
        threadMessages = [];
        renderedMessageIds = {};
        hasMoreOlderMessages = false;

        // Find contact profile
        activeContact = contacts.find(function(c){
            return (threadId && String(c.id) === String(threadId)) || (customerId && String(c.customer_id) === String(customerId));
        }) || { id: threadId, customer_id: customerId };

        renderHeader(activeContact);
        $('#tgDesktopPlaceholder').hide();
        $('#tgChatHeader').css('display', 'flex');
        $('#tgComposerForm').css('display', 'flex');

        // Load messages
        if (threadId) {
            loadThreadMessages(threadId, true);
        } else if (customerId) {
            // Create or find thread
            $.ajax({
                url: apiBaseUrl,
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                data: { _token: csrf, customer_id: customerId },
                success: function(resp){
                    if (resp && resp.data && resp.data.id) {
                        activeThreadId = resp.data.id;
                        activeContact = resp.data;
                        renderHeader(activeContact);
                        loadThreadMessages(resp.data.id, true);
                        loadChatList(true);
                    }
                }
            });
        }
    }

    function renderHeader(c){
        if (!c) return;
        var name = c.display_name || c.customer_name || 'Customer';
        var phone = c.customer_phone || '';
        var invoiceNo = c.invoice_no || c.loan_number || '';
        var color = getAvatarColor(name);
        var initials = getInitials(name);

        $('#tgHeaderName').text(name);
        $('#tgHeaderAvatar').css('background', color).html(
            c.avatar_url ? '<img src="' + esc(c.avatar_url) + '" alt="">' : initials
        );

        var headerMeta = '';
        if (phone) {
            headerMeta += '<span class="tg-header-meta-part"><i class="fa fa-phone"></i>' + esc(phone) + '</span>';
        }
        if (invoiceNo) {
            headerMeta += '<span class="tg-header-meta-part"><i class="fa fa-file-text-o"></i>' + esc(invoiceNo) + '</span>';
        }
        if (c.location_name) {
            headerMeta += '<span class="tg-header-meta-part"><i class="fa fa-map-marker"></i>' + esc(c.location_name) + '</span>';
        }
        if (!headerMeta) {
            headerMeta = c.telegram_linked ? 'online' : 'last seen recently';
        }

        $('#tgHeaderStatus').html(headerMeta).toggleClass('online', !!c.telegram_linked && !phone && !invoiceNo);

        if (phone) {
            $('#tgHeaderCallBtn').attr('href', 'tel:' + phone.replace(/[^0-9+]/g, '')).show();
        } else {
            $('#tgHeaderCallBtn').hide();
        }

        if (c.customer_id) {
            $('#tgMenuViewCustomer').attr('href', '{{ url("loan-management/customers") }}/' + c.customer_id);
        }
    }

    function loadThreadMessages(threadId, markAsRead, options){
        if (!threadId) return;
        options = options || {};
        if (isFetchingThread) return;
        if (options.beforeId && loadingOlderMessages) return;
        if (options.beforeId) loadingOlderMessages = true;
        isFetchingThread = true;
        var $body = $('#tgChatMessages');
        var previousScrollHeight = $body[0] ? $body[0].scrollHeight : 0;
        var previousScrollTop = $body.scrollTop();
        var params = ['message_limit=' + encodeURIComponent(messagePageSize)];
        if (options.beforeId) {
            params.push('before_message_id=' + encodeURIComponent(options.beforeId));
        } else if (options.afterId) {
            params.push('after_message_id=' + encodeURIComponent(options.afterId));
        }
        if (activeThreadMarkedUnread && !options.beforeId) {
            params.push('skip_read=1');
        }

        pendingThreadXhr = $.ajax({
            url: apiBaseUrl + '/' + threadId + '?' + params.join('&'),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                if (String(threadId) !== String(activeThreadId)) return;
                var threadData = resp && resp.data ? resp.data : null;
                if (!threadData) return;
                activeContact = threadData;
                renderHeader(threadData);
                var incomingMessages = threadData.messages || [];
                var pagination = threadData.message_pagination || {};

                if (options.beforeId) {
                    var previousFirstDate = threadMessages.length ? messageDateKey(threadMessages[0]) : '';
                    threadMessages = mergeThreadMessages(incomingMessages, threadMessages);
                    hasMoreOlderMessages = !!pagination.has_more_older;
                    prependMessages(incomingMessages, {previousFirstDate: previousFirstDate, previousScrollHeight: previousScrollHeight, previousScrollTop: previousScrollTop});
                    return;
                }

                if (options.afterId) {
                    if (incomingMessages.length) {
                        var previousLastDate = threadMessages.length ? messageDateKey(threadMessages[threadMessages.length - 1]) : '';
                        if (shouldPlayForNewIncomingMessages(incomingMessages)) {
                            playChatNotificationSound();
                        }
                        threadMessages = mergeThreadMessages(threadMessages, incomingMessages);
                        appendMessages(incomingMessages, {previousLastDate: previousLastDate});
                    }
                    return;
                }

                threadMessages = sortMessagesById(incomingMessages);
                renderedMessageIds = {};
                hasMoreOlderMessages = !!pagination.has_more_older;
                if (shouldPlayForNewIncomingMessages(threadMessages)) {
                    playChatNotificationSound();
                }
                renderMessages(threadMessages);
            },
            error: function(xhr){
                $('#tgChatMessages').html(
                    '<div class="tg-date-divider"><span>Cannot load conversation' +
                    (xhr && xhr.status ? ' (' + xhr.status + ')' : '') +
                    '</span></div>'
                );
            },
            complete: function(){
                isFetchingThread = false;
                loadingOlderMessages = false;
                pendingThreadXhr = null;
            }
        });
    }

    function chatFileUrlFromMessage(m){
        var file = m && m.file ? m.file : {};
        var url = file.url || file.preview_url || m.file_url || '';
        var fileId = file.id || file.file_id || m.file_id || '';

        if (!url && fileId) {
            url = '{{ url("loan-management/chat-files") }}/' + fileId;
        }

        return url;
    }

    function messageDateKey(m){
        return m && m.created_at ? String(m.created_at).split(' ')[0] : '';
    }

    function messageHtml(m){
        var idKey = String(m.id || messageSoundKey(m));
        if (idKey && renderedMessageIds[idKey]) return '';
        if (idKey) renderedMessageIds[idKey] = true;

        var isOwn = m.is_own || m.sender_type === 'staff' || m.sender_type === 'admin';
        var timeFormatted = formatTime(m.created_at);
        var ticks = isOwn ? '<span class="tg-ticks"><i class="fa fa-check"></i><i class="fa fa-check"></i></span>' : '';
        var bubbleContent = '';

        if (m.message_type === 'text' || (!m.message_type && m.message)) {
            bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
        }

        if (m.message_type === 'audio') {
            var audioUrl = chatFileUrlFromMessage(m);
            var dur = m.audio_duration_seconds ? formatDuration(m.audio_duration_seconds) : '00:20';
            bubbleContent += '<div class="tg-voice-card" data-audio-url="' + esc(audioUrl) + '" data-msg-id="' + m.id + '">' +
                '<button type="button" class="tg-voice-play-btn js-voice-play" aria-label="Play" ' + (!audioUrl ? 'disabled title="Voice file unavailable"' : '') + '><i class="fa fa-play"></i></button>' +
                '<div class="tg-voice-wave-wrap">' +
                    '<div class="tg-voice-waveform js-voice-waveform">' + generateWaveformBars() + '</div>' +
                    '<div class="tg-voice-timing"><span class="js-voice-timer">' + (audioUrl ? dur : 'Unavailable') + '</span></div>' +
                '</div>' +
            '</div>';
            if (m.message) {
                bubbleContent += '<div class="tg-msg-text" style="margin-top:4px">' + esc(m.message) + '</div>';
            }
        }

        if (m.message_type === 'image') {
            var imgUrl = chatFileUrlFromMessage(m);
            if (imgUrl) {
                bubbleContent += '<div class="tg-image-wrap js-view-image" data-full-url="' + esc(imgUrl) + '">' +
                    '<img src="' + esc(imgUrl) + '" alt="Image" loading="lazy">' +
                '</div>';
            }
            if (m.message) {
                bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
            }
        }

        if (m.message_type === 'file') {
            var docUrl = chatFileUrlFromMessage(m);
            if (docUrl) {
                bubbleContent += '<a href="' + esc(docUrl) + '" target="_blank" download class="tg-file-card">' +
                    '<div class="tg-file-icon"><i class="fa fa-file-text"></i></div>' +
                    '<div class="tg-file-details">' +
                        '<div class="tg-file-name">' + esc(m.file && m.file.name ? m.file.name : 'Invoice / Document') + '</div>' +
                        '<div class="tg-file-size">Download file</div>' +
                    '</div>' +
                '</a>';
            }
            if (m.message) {
                bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
            }
        }

        if (m.message_type === 'location' && m.latitude && m.longitude) {
            var mapUrl = 'https://maps.google.com/?q=' + m.latitude + ',' + m.longitude;
            bubbleContent += '<a href="' + esc(mapUrl) + '" target="_blank" class="tg-file-card">' +
                '<div class="tg-file-icon" style="background:#ef4444"><i class="fa fa-map-marker"></i></div>' +
                '<div class="tg-file-details">' +
                    '<div class="tg-file-name">Location Pin</div>' +
                    '<div class="tg-file-size">Tap to open in Google Maps</div>' +
                '</div>' +
            '</a>';
        }

        var quoteHtml = '';
        if (m.quote_text) {
            quoteHtml = '<div class="tg-quote-box">' +
                '<div class="tg-quote-author">' + esc(m.quote_author || 'Reply') + '</div>' +
                '<div class="tg-quote-text">' + esc(m.quote_text) + '</div>' +
            '</div>';
        }

        var reactionHtml = '';
        if (m.reaction) {
            var reactionAvatar = isOwn ? (activeContact && activeContact.avatar_url ? '<span class="tg-reaction-avatar"><img src="' + esc(activeContact.avatar_url) + '" alt=""></span>' : '') : '';
            reactionHtml = '<div class="tg-reaction-badge">' +
                '<span class="tg-reaction-emoji">' + esc(m.reaction) + '</span>' +
                reactionAvatar +
            '</div>';
        }

        return '<div class="tg-msg-row ' + (isOwn ? 'own' : '') + '" data-message-id="' + esc(idKey) + '">' +
            '<div class="tg-bubble">' +
                (!isOwn && m.sender_name ? '<div class="tg-msg-sender">' + esc(m.sender_name) + '</div>' : '') +
                quoteHtml +
                bubbleContent +
                '<div class="tg-msg-meta"><span>' + timeFormatted + '</span> ' + ticks + '</div>' +
                reactionHtml +
            '</div>' +
        '</div>';
    }

    function messagesHtml(messages, options){
        options = options || {};
        var html = '';
        var lastDate = options.previousDate || '';

        sortMessagesById(messages || []).forEach(function(m){
            var msgDate = messageDateKey(m);
            if (msgDate && msgDate !== lastDate) {
                lastDate = msgDate;
                html += '<div class="tg-date-divider"><span>' + formatDateOrTime(m.created_at) + '</span></div>';
            }
            html += messageHtml(m);
        });

        return html;
    }

    function loadMoreHtml(){
        if (!hasMoreOlderMessages) return '';
        return '<div class="tg-load-more-wrap"><button type="button" class="tg-load-more-btn" id="tgLoadOlderMessages" ' + (loadingOlderMessages ? 'disabled' : '') + '>' + (loadingOlderMessages ? 'Loading...' : 'Load more') + '</button></div>';
    }

    function renderMessages(messages, options){
        options = options || {};
        var $body = $('#tgChatMessages');

        if (!messages || !messages.length) {
            $body.html('<div class="tg-date-divider"><span>No messages yet</span></div>');
            return;
        }

        renderedMessageIds = {};
        $body.html(loadMoreHtml() + messagesHtml(messages));
        if (options.preserveScroll) {
            $body.scrollTop(($body[0].scrollHeight - (options.previousScrollHeight || 0)) + (options.previousScrollTop || 0));
        } else if (!options.keepPosition) {
            $body.scrollTop($body[0].scrollHeight);
        }
    }

    function appendMessages(messages, options){
        options = options || {};
        var $body = $('#tgChatMessages');
        var html = messagesHtml(messages, {previousDate: options.previousLastDate || ''});
        if (!html) return;
        var wasNearBottom = !$body[0] || ($body[0].scrollHeight - $body.scrollTop() - $body.outerHeight()) < 160;
        $body.append(html);
        if (wasNearBottom) {
            $body.scrollTop($body[0].scrollHeight);
        }
    }

    function prependMessages(messages, options){
        options = options || {};
        var $body = $('#tgChatMessages');
        var html = messagesHtml(messages);
        if (!html) return;
        $body.find('.tg-load-more-wrap').remove();
        $body.prepend(loadMoreHtml() + html);
        $body.scrollTop(($body[0].scrollHeight - (options.previousScrollHeight || 0)) + (options.previousScrollTop || 0));
    }

    $(document).on('click', '#tgLoadOlderMessages', function(){
        loadOlderMessages();
    });

    $('#tgChatMessages').on('scroll', function(){
        if (hasMoreOlderMessages && !loadingOlderMessages && !isFetchingThread && $(this).scrollTop() <= autoLoadOlderThreshold) {
            scheduleAutoLoadOlderMessages();
        }
    });

    function generateWaveformBars(){
        var heights = [6, 12, 18, 10, 16, 22, 14, 8, 18, 24, 12, 20, 16, 10, 22, 18, 14, 8, 16, 20, 12, 16, 22, 14, 8, 12];
        return heights.map(function(h){
            return '<div class="tg-voice-bar" style="height:' + h + 'px"></div>';
        }).join('');
    }

    $(document).one('pointerdown keydown', unlockNotificationAudio);

    // -------------------------------------------------------------
    // AUDIO VOICE PLAYBACK
    // -------------------------------------------------------------
    $(document).on('click', '.js-voice-play, .js-voice-waveform', function(){
        var $card = $(this).closest('.tg-voice-card');
        var $btn = $card.find('.js-voice-play').first();
        var audioUrl = $card.data('audio-url');
        var msgId = $card.data('msg-id');

        if (!audioUrl || $btn.prop('disabled')) {
            $card.find('.js-voice-timer').text('Unavailable');
            return;
        }

        if (currentAudio && currentAudioMsgId === msgId) {
            if (!currentAudio.paused) {
                currentAudio.pause();
                $btn.html('<i class="fa fa-play"></i>');
                return;
            } else {
                var resumePlayback = currentAudio.play();
                if (resumePlayback && resumePlayback.catch) {
                    resumePlayback.catch(function(){ $card.find('.js-voice-timer').text('Cannot play'); });
                }
                $btn.html('<i class="fa fa-pause"></i>');
                return;
            }
        }

        if (currentAudio) {
            currentAudio.pause();
            $('.js-voice-play').html('<i class="fa fa-play"></i>');
            $('.tg-voice-bar').removeClass('played');
        }

        var audio = new Audio(audioUrl);
        var $bars = $card.find('.tg-voice-bar');
        var $timer = $card.find('.js-voice-timer');

        audio.preload = 'metadata';
        currentAudio = audio;
        currentAudioMsgId = msgId;

        $btn.html('<i class="fa fa-pause"></i>');
        var playback = audio.play();
        if (playback && playback.catch) {
            playback.catch(function(){
                $btn.html('<i class="fa fa-play"></i>');
                $timer.text('Cannot play');
                currentAudio = null;
                currentAudioMsgId = null;
            });
        }

        audio.onerror = function(){
            $btn.html('<i class="fa fa-play"></i>');
            $bars.removeClass('played');
            $timer.text('Cannot play');
            currentAudio = null;
            currentAudioMsgId = null;
        };

        audio.ontimeupdate = function(){
            if (!audio.duration) return;
            var progress = audio.currentTime / audio.duration;
            var playedCount = Math.floor(progress * $bars.length);
            $bars.each(function(idx){
                $(this).toggleClass('played', idx <= playedCount);
            });
            $timer.text(formatDuration(Math.floor(audio.currentTime)));
        };

        audio.onended = function(){
            $btn.html('<i class="fa fa-play"></i>');
            $bars.removeClass('played');
            $timer.text(formatDuration(Math.floor(audio.duration || 0)));
            currentAudio = null;
            currentAudioMsgId = null;
        };
    });

    // -------------------------------------------------------------
    // SEND TEXT MESSAGE (Super Fast & Optimistic UI)
    // -------------------------------------------------------------
    function sendTextMessage(){
        if (isRecording) {
            finishVoiceRecording();
            return;
        }

        var $input = $('#tgMessageInput');
        var text = $input.val().trim();
        if (!text) return;

        // Clear input immediately and keep focus for seamless rapid texting
        $input.val('').focus();
        toggleSendActionIcon();

        // 1. Instant Optimistic Render
        var tempId = 'opt_' + Date.now();
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        var timeStr = hh + ':' + mm;

        var optHtml = '<div class="tg-msg-row own" id="' + tempId + '">' +
            '<div class="tg-bubble">' +
                '<div class="tg-msg-text">' + esc(text) + '</div>' +
                '<div class="tg-msg-meta"><span>' + timeStr + '</span> <span class="tg-ticks js-opt-status"><i class="fa fa-clock-o" style="color:#8c9398"></i></span></div>' +
            '</div>' +
        '</div>';

        var $body = $('#tgChatMessages');
        $body.append(optHtml);
        $body.stop().animate({ scrollTop: $body[0].scrollHeight }, 150);

        // 2. Ensure Thread Exists & Send via API
        var sendAction = function(threadId){
            activeThreadMarkedUnread = false;
            $.ajax({
                url: apiBaseUrl + '/' + threadId + '/messages',
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                data: {
                    _token: csrf,
                    message_type: 'text',
                    message: text
                },
                success: function(resp){
                    $('#' + tempId).find('.js-opt-status').html('<i class="fa fa-check"></i><i class="fa fa-check" style="margin-left:-4px"></i>');
                    loadChatList(true);
                },
                error: function(){
                    $('#' + tempId).find('.js-opt-status').html('<i class="fa fa-exclamation-circle" style="color:#ef4444" title="Failed to send. Click to retry."></i>');
                }
            });
        };

        if (activeThreadId) {
            sendAction(activeThreadId);
        } else if (activeContact && activeContact.customer_id) {
            $.ajax({
                url: apiBaseUrl,
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                data: { _token: csrf, customer_id: activeContact.customer_id },
                success: function(resp){
                    if (resp && resp.data && resp.data.id) {
                        activeThreadId = resp.data.id;
                        activeContact = resp.data;
                        renderHeader(activeContact);
                        sendAction(activeThreadId);
                    }
                }
            });
        }
    }

    $('#tgComposerForm').on('submit', function(e){
        e.preventDefault();
        sendTextMessage();
    });

    // Enter key sends message immediately
    $('#tgMessageInput').on('keydown', function(e){
        if (e.key === 'Enter' || e.keyCode === 13) {
            if (!e.shiftKey) {
                e.preventDefault();
                sendTextMessage();
            }
        }
    });

    // Mobile viewport & keyboard scroll
    $('#tgMessageInput').on('focus', function(){
        setTimeout(function(){
            var $body = $('#tgChatMessages');
            if ($body.length) $body.scrollTop($body[0].scrollHeight);
        }, 250);
    });

    function toggleSendActionIcon(){
        var hasText = $('#tgMessageInput').val().trim().length > 0;
        if (hasText) {
            $('#tgActionSendIcon').removeClass('fa-microphone').addClass('fa-paper-plane');
            $('#tgActionSendBtn').attr('title', 'Send Message').addClass('is-send-ready');
        } else {
            $('#tgActionSendIcon').removeClass('fa-paper-plane').addClass('fa-microphone');
            $('#tgActionSendBtn').attr('title', 'Record Voice').removeClass('is-send-ready');
        }
    }
    $('#tgMessageInput').on('input propertychange', toggleSendActionIcon);

    // -------------------------------------------------------------
    // VOICE RECORDING (MediaRecorder)
    // -------------------------------------------------------------
    $('#tgActionSendBtn').on('click', function(e){
        e.preventDefault();
        var hasText = $('#tgMessageInput').val().trim().length > 0;
        if (hasText) {
            sendTextMessage();
        } else if (!isRecording) {
            startVoiceRecording();
        } else {
            finishVoiceRecording();
        }
    });

    function startVoiceRecording(){
        if (!activeThreadId) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            alert('Voice recording is not supported in this browser.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream){
            voiceStream = stream;
            voiceChunks = [];
            voiceSeconds = 0;
            isRecording = true;

            var options = {};
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                options = { mimeType: 'audio/webm;codecs=opus' };
            } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                options = { mimeType: 'audio/ogg;codecs=opus' };
            }

            mediaRecorder = new MediaRecorder(stream, options);

            mediaRecorder.ondataavailable = function(e){
                if (e.data && e.data.size > 0) voiceChunks.push(e.data);
            };

            mediaRecorder.onstop = function(){
                if (voiceSeconds >= 1 && voiceChunks.length) {
                    var mime = mediaRecorder.mimeType || 'audio/webm';
                    var blob = new Blob(voiceChunks, { type: mime });
                    sendVoiceBlob(blob, voiceSeconds);
                }
                resetVoiceRecorder();
            };

            mediaRecorder.start(500);
            $('#tgComposerForm').addClass('is-recording');
            $('#tgActionSendBtn').addClass('recording');
            $('#tgVoiceTimer').text('00:00');

            voiceTimer = setInterval(function(){
                voiceSeconds++;
                $('#tgVoiceTimer').text(formatDuration(voiceSeconds));
            }, 1000);
        }).catch(function(err){
            alert('Microphone permission required to record voice notes.');
        });
    }

    function finishVoiceRecording(){
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
    }

    function cancelVoiceRecording(){
        if (voiceTimer) clearInterval(voiceTimer);
        voiceChunks = [];
        voiceSeconds = 0;
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
        resetVoiceRecorder();
    }
    $('#tgVoiceCancelBtn').on('click', cancelVoiceRecording);

    function resetVoiceRecorder(){
        if (voiceTimer) clearInterval(voiceTimer);
        voiceTimer = null;
        if (voiceStream) {
            voiceStream.getTracks().forEach(function(t){ t.stop(); });
            voiceStream = null;
        }
        mediaRecorder = null;
        isRecording = false;
        $('#tgComposerForm').removeClass('is-recording');
        $('#tgActionSendBtn').removeClass('recording');
        toggleSendActionIcon();
    }

    function sendVoiceBlob(blob, durationSec){
        activeThreadMarkedUnread = false;
        var ext = blob.type.indexOf('ogg') >= 0 ? 'ogg' : 'webm';
        var file = new File([blob], 'voice-' + Date.now() + '.' + ext, { type: blob.type });
        var formData = new FormData();
        formData.append('_token', csrf);
        formData.append('message_type', 'audio');
        formData.append('file', file);
        formData.append('duration_seconds', durationSec);

        $.ajax({
            url: apiBaseUrl + '/' + activeThreadId + '/messages',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(){
                loadThreadMessages(activeThreadId, false);
                loadChatList(true);
            }
        });
    }

    // -------------------------------------------------------------
    // ATTACHMENTS (Invoice, Photo, Document, Location)
    // -------------------------------------------------------------
    $('#tgAttachBtn').on('click', function(e){
        e.stopPropagation();
        $('#tgAttachSheet').toggleClass('open');
        $('#tgEmojiPanel').addClass('hidden');
    });
    $(document).on('click', function(e){
        if (!$(e.target).closest('#tgAttachSheet, #tgAttachBtn').length) {
            $('#tgAttachSheet').removeClass('open');
        }
        if (!$(e.target).closest('#tgEmojiPanel, #tgEmojiBtn').length) {
            $('#tgEmojiPanel').addClass('hidden');
        }
        if (!$(e.target).closest('#tgChatDropdown, #tgChatMenuBtn').length) {
            $('#tgChatDropdown').removeClass('open');
        }
        if (!$(e.target).closest('#tgListDropdown, #tgListMenuBtn').length) {
            $('#tgListDropdown').removeClass('open');
        }
    });

    // Send Invoice
    $('#tgAttachInvoice, #tgMenuSendInvoice').on('click', function(){
        $('#tgAttachSheet').removeClass('open');
        $('#tgChatDropdown').removeClass('open');
        if (!activeThreadId) return;

        var invoiceNum = activeContact ? (activeContact.invoice_no || activeContact.loan_number || '') : '';
        if (!confirm('Send installment invoice snapshot' + (invoiceNum ? ' (' + invoiceNum + ')' : '') + ' to customer?')) {
            return;
        }

        activeThreadMarkedUnread = false;
        $.ajax({
            url: apiBaseUrl + '/' + activeThreadId + '/invoice-image',
            method: 'POST',
            data: { _token: csrf },
            success: function(){
                loadThreadMessages(activeThreadId, false);
                loadChatList(true);
            },
            error: function(err){
                alert('Cannot generate invoice image. ' + (err.responseJSON && err.responseJSON.message ? err.responseJSON.message : ''));
            }
        });
    });

    // Quick Pay action
    $('#tgMenuQuickPay').on('click', function(){
        $('#tgChatDropdown').removeClass('open');
        if (!activeContact) return;
        var loanId = activeContact.loan_id;
        if (loanId && typeof window.openLoanQuickPayModal === 'function') {
            window.openLoanQuickPayModal(loanId);
        } else {
            alert('Quick Pay is available for customers with an active installment loan.');
        }
    });

    // Photo / Camera
    $('#tgAttachPhoto, #tgFabCamera').on('click', function(){
        $('#tgAttachSheet').removeClass('open');
        $('#tgFileInputImage').click();
    });
    $('#tgFileInputImage').on('change', function(){
        var file = this.files[0];
        if (!file || !activeThreadId) return;
        sendFileMessage('image', file);
    });

    // Document / File
    $('#tgAttachFile').on('click', function(){
        $('#tgAttachSheet').removeClass('open');
        $('#tgFileInputDoc').click();
    });
    $('#tgFileInputDoc').on('change', function(){
        var file = this.files[0];
        if (!file || !activeThreadId) return;
        sendFileMessage('file', file);
    });

    function sendFileMessage(type, file){
        activeThreadMarkedUnread = false;
        var formData = new FormData();
        formData.append('_token', csrf);
        formData.append('message_type', type);
        formData.append('file', file);
        formData.append('message', $('#tgMessageInput').val().trim());
        $('#tgMessageInput').val('');
        toggleSendActionIcon();

        $.ajax({
            url: apiBaseUrl + '/' + activeThreadId + '/messages',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(){
                loadThreadMessages(activeThreadId, false);
                loadChatList(true);
            }
        });
    }

    // Location
    $('#tgAttachLocation').on('click', function(){
        $('#tgAttachSheet').removeClass('open');
        if (!navigator.geolocation || !activeThreadId) return;

        navigator.geolocation.getCurrentPosition(function(pos){
            activeThreadMarkedUnread = false;
            $.ajax({
                url: apiBaseUrl + '/' + activeThreadId + '/messages',
                method: 'POST',
                data: {
                    _token: csrf,
                    message_type: 'location',
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude
                },
                success: function(){
                    loadThreadMessages(activeThreadId, false);
                    loadChatList(true);
                }
            });
        }, function(){
            alert('Cannot access GPS location.');
        });
    });

    // -------------------------------------------------------------
    // EMOJI PICKER
    // -------------------------------------------------------------
    $('#tgEmojiBtn').on('click', function(e){
        e.stopPropagation();
        $('#tgEmojiPanel').toggleClass('hidden');
        $('#tgAttachSheet').removeClass('open');
    });
    $(document).on('click', '.tg-emoji-item', function(){
        var emoji = $(this).text();
        var $input = $('#tgMessageInput');
        $input.val($input.val() + emoji).focus();
        toggleSendActionIcon();
    });

    // -------------------------------------------------------------
    // NAVIGATION (LIST <-> CHAT VIEW)
    // -------------------------------------------------------------
    $('#tgChatList').on('click', '.tg-chat-item', function(e){
        if ($(e.target).closest('.js-quick-folder-btn').length) return;
        var threadId = $(this).data('thread-id');
        var customerId = $(this).data('customer-id');
        $('.tg-chat-item').removeClass('active');
        $(this).addClass('active');
        openConversation(threadId || null, customerId || null);
    });

    $('#tgBackToListBtn').on('click', function(){
        if (pendingThreadXhr && pendingThreadXhr.readyState !== 4) {
            pendingThreadXhr.abort();
        }
        isFetchingThread = false;
        activeThreadId = null;
        activeContact = null;
        $('#tgAppWrapper').removeClass('in-conversation');
        $('body').removeClass('tg-viewing-chat');
        $('#tgChatHeader').hide();
        $('#tgComposerForm').hide();
        $('#tgDesktopPlaceholder').show();
        $('.tg-chat-item').removeClass('active');
        loadChatList(true);
    });

    // Search & Filter Actions
    $('#tgSearchInput').on('input', function(){
        var val = $(this).val();
        $('#tgSearchClear').toggle(val.length > 0);
        renderChatList();
    });
    $('#tgSearchClear').on('click', function(){
        $('#tgSearchInput').val('').trigger('input');
    });

    $('#tgFilterPills').on('click', '.tg-pill[data-filter]', function(){
        var filterVal = $(this).attr('data-filter');
        if (!filterVal) return;
        $('#tgFilterPills .tg-pill[data-filter]').removeClass('active');
        $(this).addClass('active');
        currentFilter = String(filterVal);
        renderChatList();
    });

    $('#tgFabCompose').on('click', function(){
        $('#tgSearchInput').focus();
    });

    // Menus
    $('#tgListMenuBtn').on('click', function(e){
        e.stopPropagation();
        $('#tgListDropdown').toggleClass('open');
    });
    $('#tgChatMenuBtn').on('click', function(e){
        e.stopPropagation();
        $('#tgChatDropdown').toggleClass('open');
    });
    $('#tgActionRefresh').on('click', function(){
        $('#tgListDropdown').removeClass('open');
        loadChatList(false);
    });
    $('#tgMenuRefreshChat').on('click', function(){
        $('#tgChatDropdown').removeClass('open');
        if (activeThreadId) loadThreadMessages(activeThreadId, false);
    });
    $('#tgMenuMarkUnread').on('click', function(){
        $('#tgChatDropdown').removeClass('open');
        if (!activeThreadId) return;
        $.ajax({
            url: apiBaseUrl + '/' + activeThreadId + '/unread',
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            data: { _token: csrf },
            success: function(resp){
                activeThreadMarkedUnread = true;
                if (resp && resp.data) {
                    activeContact = resp.data;
                }
                loadChatList(true, {suppressSound: true});
            },
            error: function(xhr){
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Cannot mark chat unread';
                alert(msg);
            }
        });
    });

    // Image Viewer Modal
    $(document).on('click', '.js-view-image', function(){
        var url = $(this).data('full-url');
        $('#tgViewerImage').attr('src', url);
        $('#tgViewerModal').addClass('open');
    });
    $('#tgViewerClose, #tgViewerModal').on('click', function(e){
        if (e.target === this) $('#tgViewerModal').removeClass('open');
    });

    // -------------------------------------------------------------
    // INITIALIZATION & REAL-TIME POLLING
    // -------------------------------------------------------------
    loadFolders();
    loadChatList(false);

    if (initialThreadId) {
        openConversation(initialThreadId, null);
    } else if (initialCustomerId) {
        openConversation(null, initialCustomerId);
    }

    function pollChatUpdates(){
        if (document.hidden) {
            loadChatList(true, {snapshot: true});
            return;
        }
        if (activeThreadId) {
            loadThreadMessages(activeThreadId, false, {afterId: newestLoadedMessageId()});
        }
        loadChatList(true, {snapshot: true});
    }

    // Polling
    pollTimer = setInterval(pollChatUpdates, pollMs);

    $(window).on('beforeunload', function(){
        if (pollTimer) clearInterval(pollTimer);
        resetVoiceRecorder();
    });

})(jQuery);
</script>
@endsection
