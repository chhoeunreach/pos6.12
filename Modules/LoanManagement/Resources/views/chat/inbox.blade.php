@extends('loanmanagement::layouts.app')
@section('title', 'Telegram Chats')

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
    /* Hide floating Telegram fab button on the dedicated Chats page */
    #lmTgFab {
        display: none !important;
    }

    /* Overall Shell */
    .tg-mobile-wrapper {
        position: relative;
        width: 100%;
        height: calc(100dvh - 120px);
        min-height: 580px;
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        display: flex;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        font-family: "Khmer OS Battambang", "Noto Sans Khmer", "SF Pro Display", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    }

    /* Sidebar / Chat List View (Screen 1) */
    .tg-pane-list {
        width: 380px;
        flex: 0 0 380px;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e5e7eb;
        background: #ffffff;
        position: relative;
        z-index: 10;
        height: 100%;
    }

    /* Conversation View (Screen 2) */
    .tg-pane-chat {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        background: #87ab8c;
        position: relative;
        min-width: 0;
        height: 100%;
    }

    /* Top Telegram App Header */
    .tg-top-bar {
        padding: 10px 16px 8px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #f1f3f5;
        flex: 0 0 auto;
    }
    .tg-top-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tg-app-avatar {
        width: 38px;
        height: 38px;
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
    .tg-app-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .tg-app-title {
        font-size: 21px;
        font-weight: 700;
        color: #2481cc;
        letter-spacing: -0.2px;
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
        color: #707579;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .tg-icon-btn:hover, .tg-icon-btn:active {
        background: #f0f2f5;
        color: #222;
    }

    /* Search Bar */
    .tg-search-wrap {
        padding: 6px 14px 10px;
        background: #ffffff;
        flex: 0 0 auto;
    }
    .tg-search-box {
        position: relative;
        display: flex;
        align-items: center;
        background: #f0f2f5;
        border-radius: 20px;
        padding: 0 14px;
        height: 38px;
        border: 1.5px solid transparent;
        transition: all 0.2s ease;
    }
    .tg-search-box:focus-within {
        background: #fff;
        border-color: #2481cc;
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
        color: #111827;
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
        padding: 4px 14px 10px;
        background: #fff;
        overflow-x: auto;
        white-space: nowrap;
        border-bottom: 1px solid #f1f3f5;
        scrollbar-width: none;
        flex: 0 0 auto;
    }
    .tg-filter-pills::-webkit-scrollbar {
        display: none;
    }
    .tg-pill {
        border: 1px solid #e0e4e8;
        background: #ffffff;
        color: #555b61;
        border-radius: 18px;
        padding: 5px 12px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.18s ease;
        flex: 0 0 auto;
    }
    .tg-pill:hover {
        background: #f7f9fa;
        color: #222;
    }
    .tg-pill.active {
        background: #e7f2fb;
        border-color: #2481cc;
        color: #2481cc;
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
        background: #2481cc;
        color: #fff;
    }

    /* Chat List Items */
    .tg-chat-list {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 4px 0 70px;
        min-height: 0;
    }
    .tg-chat-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        cursor: pointer;
        position: relative;
        background: #fff;
        transition: background 0.15s ease;
        border-bottom: 1px solid #f8f9fa;
        user-select: none;
    }
    .tg-chat-item:hover {
        background: #f4f6f8;
    }
    .tg-chat-item.active {
        background: #ebf4fb;
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
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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
        color: #707579;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .tg-item-preview i {
        font-size: 12px;
        color: #2481cc;
    }
    .tg-item-badge {
        background: #2481cc;
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
        color: #2481cc;
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
    .tg-fab-cam {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #ffffff;
        color: #555b61;
        box-shadow: 0 4px 14px rgba(0,0,0,0.18);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .tg-fab-cam:active {
        transform: scale(0.92);
    }
    .tg-fab-compose {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2ea5e8, #1d74b8);
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
        height: 60px;
        background: #ffffff;
        border-bottom: 1px solid rgba(0,0,0,0.08);
        padding: 8px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex: 0 0 60px;
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
        color: #333;
        display: none; /* Shown on mobile */
        align-items: center;
        justify-content: center;
        font-size: 19px;
        cursor: pointer;
        flex: 0 0 36px;
    }
    .tg-header-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 16px;
        font-weight: 700;
        flex: 0 0 42px;
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
        font-size: 15.5px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-header-status {
        font-size: 12px;
        color: #707579;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-header-status.online {
        color: #22c55e;
        font-weight: 600;
    }
    .tg-header-right {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 0 0 auto;
    }

    /* Telegram Doodle Wallpaper Chat Body */
    .tg-chat-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 16px 14px 20px;
        position: relative;
        min-height: 0;
        /* Authentic Telegram sage green doodle pattern */
        background-color: #88ad8d;
        background-image: radial-gradient(#6e9874 1.2px, transparent 1.2px), radial-gradient(#6e9874 1.2px, #88ad8d 1.2px);
        background-size: 24px 24px;
        background-position: 0 0, 12px 12px;
    }
    .tg-chat-body::before {
        content: "";
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='120' height='120' viewBox='0 0 120 120' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M15 15h6v6h-6zm40 10c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm35-8l5 9h-10zm-65 48c0 4.4 3.6 8 8 8s8-3.6 8-8-3.6-8-8-8-8 3.6-8 8zm65 15h12v4H90zm-45 15l-6-8h12zm60-35c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4z'/%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }

    /* Date Separator */
    .tg-date-divider {
        text-align: center;
        margin: 14px 0 10px;
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
        max-width: 82%;
        min-width: 80px;
        padding: 8px 12px 6px;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
        font-size: 14px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    /* Outgoing Bubble (Telegram green) */
    .tg-msg-row.own .tg-bubble {
        background: #e1ffc7;
        color: #000;
        border-bottom-right-radius: 4px;
    }
    /* Incoming Bubble (White) */
    .tg-msg-row:not(.own) .tg-bubble {
        background: #ffffff;
        color: #0f172a;
        border-bottom-left-radius: 4px;
    }

    /* Sender Name for group / customer */
    .tg-msg-sender {
        font-size: 12px;
        font-weight: 700;
        color: #168acd;
        margin-bottom: 3px;
    }

    /* Quoted Message */
    .tg-quote-box {
        border-left: 3px solid #e53935;
        background: rgba(229, 57, 53, 0.07);
        padding: 4px 8px;
        border-radius: 4px 8px 8px 4px;
        margin-bottom: 6px;
        font-size: 12px;
    }
    .tg-quote-author {
        font-weight: 700;
        color: #e53935;
        margin-bottom: 1px;
    }
    .tg-quote-text {
        color: #555;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Message Meta Info (Time & Double Check) */
    .tg-msg-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 2px;
        font-size: 11px;
        color: #687987;
        float: right;
        margin-left: 8px;
    }
    .tg-msg-meta i.fa-check, .tg-msg-meta .tg-ticks {
        color: #4fae63;
        font-size: 11px;
    }

    /* Voice Message Audio Player Bubble */
    .tg-voice-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 4px 0;
        min-width: 210px;
    }
    .tg-voice-play-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: none;
        background: #4fae63;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        flex: 0 0 42px;
        transition: transform 0.15s ease, background 0.15s ease;
    }
    .tg-msg-row.own .tg-voice-play-btn {
        background: #4fae63;
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
    .tg-voice-bar.played {
        background: #2e7d32;
    }
    .tg-msg-row:not(.own) .tg-voice-bar {
        background: #cfd8dc;
    }
    .tg-msg-row:not(.own) .tg-voice-bar.played {
        background: #1976d2;
    }
    .tg-voice-timing {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 11px;
        color: #64748b;
        font-variant-numeric: tabular-nums;
    }

    /* Reaction Badge */
    .tg-reaction-badge {
        position: absolute;
        bottom: -9px;
        left: 8px;
        background: #ffffff;
        border: 1px solid #e0e4e8;
        border-radius: 14px;
        padding: 1px 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        z-index: 3;
    }
    .tg-reaction-avatar {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #1d74b8;
        color: #fff;
        font-size: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    /* Image Attachment */
    .tg-image-wrap {
        margin: 4px 0;
        border-radius: 12px;
        overflow: hidden;
        max-width: 260px;
        cursor: pointer;
    }
    .tg-image-wrap img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 12px;
    }

    /* File / Invoice Card */
    .tg-file-card {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.04);
        border-radius: 10px;
        padding: 8px 10px;
        margin: 4px 0;
        text-decoration: none !important;
        color: inherit;
    }
    .tg-file-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #2481cc;
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
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .tg-file-size {
        font-size: 11px;
        color: #64748b;
    }

    /* Bottom Telegram Composer Bar */
    .tg-composer-bar {
        background: #ffffff;
        border-top: 1px solid #eef0f2;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
        z-index: 30;
    }
    .tg-composer-input-wrap {
        flex: 1 1 auto;
        position: relative;
        display: flex;
        align-items: center;
        background: #f0f2f5;
        border-radius: 22px;
        padding: 0 12px;
        min-height: 44px;
    }
    .tg-composer-input {
        flex: 1 1 auto;
        border: none;
        background: transparent;
        outline: none;
        font-size: 14.5px;
        color: #111827;
        padding: 8px 0;
        max-height: 100px;
        resize: none;
    }
    .tg-composer-input::placeholder {
        color: #8c9398;
    }
    .tg-composer-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: #707579;
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
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: #2481cc;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        cursor: pointer;
        flex: 0 0 44px;
        box-shadow: 0 4px 12px rgba(36, 129, 204, 0.35);
        transition: transform 0.15s ease, background 0.15s ease;
    }
    .tg-send-action-btn:active {
        transform: scale(0.92);
    }
    .tg-send-action-btn.recording {
        background: #dc2626;
        animation: tgPulse 1.2s infinite;
    }
    @keyframes tgPulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.5); }
        70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
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
        z-index: 50;
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
        z-index: 50;
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
        z-index: 60;
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
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.2px;
    }

    /* ==========================================================================
       MOBILE RESPONSIVE ADAPTATION (Matches Screenshots 1 & 2)
       ========================================================================== */
    @media (max-width: 991px) {
        /* Hide bulky desktop headers/breadcrumbs on mobile */
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
        }

        /* Full mobile screen wrapper */
        .tg-mobile-wrapper {
            height: calc(100dvh - 56px);
            min-height: 100dvh;
            border-radius: 0;
            border: none;
            box-shadow: none;
        }

        /* Single Pane State Machine */
        .tg-pane-list {
            width: 100% !important;
            flex: 1 1 auto !important;
            border-right: none;
        }
        .tg-pane-chat {
            width: 100% !important;
            flex: 1 1 auto !important;
            display: none;
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

        /* Hide bottom navigation bar while in full conversation view */
        body.tg-viewing-chat #loanMobileNav {
            display: none !important;
        }
        body.tg-viewing-chat .tg-mobile-wrapper {
            height: 100dvh !important;
        }

        /* Safe area composer spacing */
        .tg-composer-bar {
            padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
        }

        .tg-bubble {
            max-width: 88%;
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
            <button type="button" class="tg-pill" data-filter="personal">
                <span>Personal</span>
            </button>
            <button type="button" class="tg-pill" data-filter="invoices">
                <span>វិក្កយបត្រ</span>
                <span class="tg-pill-badge" id="tgBadgeInvoices">0</span>
            </button>
            <button type="button" class="tg-pill" data-filter="installments">
                <span>រំលស់</span>
                <span class="tg-pill-badge" id="tgBadgeInstallments">0</span>
            </button>
            @foreach($chatLocationOptions as $loc)
                <button type="button" class="tg-pill" data-filter="location" data-location-id="{{ $loc->id }}">
                    <span>{{ $loc->name }}</span>
                </button>
            @endforeach
        </div>

        <!-- Chats List -->
        <div class="tg-chat-list" id="tgChatList">
            <div class="tg-empty-chats">
                <i class="fa fa-circle-o-notch fa-spin"></i>
                <div>Loading Telegram chats...</div>
            </div>
        </div>

        <!-- Floating Action Buttons (Camera & Pencil/Compose) -->
        <div class="tg-fab-stack">
            <button type="button" class="tg-fab-cam" id="tgFabCamera" title="Camera" aria-label="Take Photo">
                <i class="fa fa-camera"></i>
            </button>
            <button type="button" class="tg-fab-compose" id="tgFabCompose" title="New Chat" aria-label="New Chat">
                <i class="fa fa-pencil"></i>
            </button>
        </div>

        <!-- Top Menu Dropdown -->
        <div class="tg-dropdown-menu" id="tgListDropdown">
            <a class="tg-dropdown-item" id="tgActionRefresh" href="javascript:void(0)"><i class="fa fa-refresh"></i> Refresh Chats</a>
            <a class="tg-dropdown-item" id="tgActionNewCustomer" href="{{ route('loan-management.customers.create') }}"><i class="fa fa-user-plus"></i> New Customer</a>
            <a class="tg-dropdown-item" id="tgActionSettings" href="{{ route('loan-management.settings.telegram.index') }}"><i class="fa fa-cog"></i> Telegram Settings</a>
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

        <!-- Telegram Bottom Message Composer Bar -->
        <form class="tg-composer-bar" id="tgComposerForm" style="display:none">
            <button type="button" class="tg-composer-btn" id="tgEmojiBtn" title="Emoji" aria-label="Insert Emoji">
                <i class="fa fa-smile-o"></i>
            </button>

            <!-- Regular Text Input Container -->
            <div class="tg-composer-input-wrap">
                <input type="text" class="tg-composer-input" id="tgMessageInput" placeholder="Message" autocomplete="off">
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

            <!-- Paperclip Attachment Button -->
            <button type="button" class="tg-composer-btn" id="tgAttachBtn" title="Attach file" aria-label="Attach">
                <i class="fa fa-paperclip"></i>
            </button>

            <!-- Send or Microphone Action Button -->
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
            <a class="tg-dropdown-item" id="tgMenuSendInvoice" href="javascript:void(0)"><i class="fa fa-file-text-o"></i> Send Invoice</a>
            <a class="tg-dropdown-item" id="tgMenuQuickPay" href="javascript:void(0)"><i class="fa fa-money"></i> Quick Pay</a>
            <a class="tg-dropdown-item" id="tgMenuViewCustomer" href="javascript:void(0)" target="_blank"><i class="fa fa-user"></i> View Profile</a>
            <a class="tg-dropdown-item" id="tgMenuRefreshChat" href="javascript:void(0)"><i class="fa fa-refresh"></i> Refresh Thread</a>
        </div>
    </main>
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
    var pollMs = {{ (int) config("loanmanagement.chat_polling_seconds", 5) * 1000 }};
    var initialThreadId = @json($initialThreadId ?? null);
    var initialCustomerId = @json($initialCustomerId ?? null);

    var contacts = [];
    var activeContact = null;
    var activeThreadId = null;
    var currentFilter = 'all';
    var pollTimer = null;
    var isFetchingList = false;
    var isFetchingThread = false;

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
    // LOAD CONTACTS / CHAT LIST
    // -------------------------------------------------------------
    function loadChatList(silent){
        if (isFetchingList && !silent) return;
        isFetchingList = true;

        var params = {
            search: $('#tgSearchInput').val().trim()
        };

        $.ajax({
            url: apiBaseUrl,
            data: params,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                contacts = resp && resp.data ? (Array.isArray(resp.data) ? resp.data : (resp.data.data || [])) : [];
                renderChatList();
                updatePillBadges();
            },
            complete: function(){
                isFetchingList = false;
            }
        });
    }

    function updatePillBadges(){
        $('#tgBadgeAll').text(contacts.length);
        var invoiceCount = contacts.filter(function(c){ return !!c.invoice_no; }).length;
        $('#tgBadgeInvoices').text(invoiceCount);
        var installmentCount = contacts.filter(function(c){ return !!c.loan_id || !!c.installment_no; }).length;
        $('#tgBadgeInstallments').text(installmentCount);
    }

    function filterContacts(){
        var q = ($('#tgSearchInput').val() || '').toLowerCase().trim();
        return contacts.filter(function(c){
            if (currentFilter === 'personal') {
                if (!c.telegram_linked) return false;
            } else if (currentFilter === 'invoices') {
                if (!c.invoice_no) return false;
            } else if (currentFilter === 'installments') {
                if (!c.loan_id && !c.installment_no) return false;
            } else if (currentFilter === 'location') {
                var locId = $('#tgFilterPills .tg-pill.active').data('location-id');
                if (locId && String(c.location_id) !== String(locId)) return false;
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

        if (!filtered.length) {
            $list.html('<div class="tg-empty-chats"><i class="fa fa-telegram"></i><div>No chats found</div></div>');
            return;
        }

        var html = '';
        filtered.forEach(function(c){
            var isActive = (activeThreadId && String(c.id) === String(activeThreadId)) || (activeContact && String(c.customer_id) === String(activeContact.customer_id));
            var color = getAvatarColor(c.display_name || c.customer_name);
            var initials = getInitials(c.display_name || c.customer_name);
            var avatarHtml = c.avatar_url
                ? '<img src="' + esc(c.avatar_url) + '" alt="">'
                : initials;

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

            html += '<div class="tg-chat-item ' + (isActive ? 'active' : '') + '" data-thread-id="' + (c.id || '') + '" data-customer-id="' + (c.customer_id || '') + '">' +
                '<div class="tg-avatar-wrap">' +
                    '<div class="tg-avatar" style="background:' + color + '">' + avatarHtml + '</div>' +
                    (c.telegram_linked ? '<span class="tg-avatar-dot" title="Linked to Telegram"></span>' : '') +
                '</div>' +
                '<div class="tg-item-body">' +
                    '<div class="tg-item-row-top">' +
                        '<div class="tg-item-name">' + esc(c.display_name || c.customer_name || 'Customer') + '</div>' +
                        '<div class="tg-item-time">' + timeHtml + '</div>' +
                    '</div>' +
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

        // Find contact profile
        activeContact = contacts.find(function(c){
            return (threadId && String(c.id) === String(threadId)) || (customerId && String(c.customer_id) === String(customerId));
        }) || { id: threadId, customer_id: customerId };

        renderHeader(activeContact);
        $('#tgDesktopPlaceholder').hide();
        $('#tgChatHeader').show();
        $('#tgComposerForm').show();

        // Load messages
        if (threadId) {
            loadThreadMessages(threadId, true);
        } else if (customerId) {
            // Create or find thread
            $.ajax({
                url: apiBaseUrl,
                method: 'POST',
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
        var name = c.display_name || c.customer_name || 'Customer';
        var phone = c.customer_phone || '';
        var color = getAvatarColor(name);
        var initials = getInitials(name);

        $('#tgHeaderName').text(name);
        $('#tgHeaderAvatar').css('background', color).html(
            c.avatar_url ? '<img src="' + esc(c.avatar_url) + '" alt="">' : initials
        );

        if (c.telegram_linked) {
            $('#tgHeaderStatus').text('online').addClass('online');
        } else {
            $('#tgHeaderStatus').text(phone ? phone + (c.location_name ? ' · ' + c.location_name : '') : 'last seen recently').removeClass('online');
        }

        if (phone) {
            $('#tgHeaderCallBtn').attr('href', 'tel:' + phone.replace(/[^0-9+]/g, '')).show();
        } else {
            $('#tgHeaderCallBtn').hide();
        }

        if (c.customer_id) {
            $('#tgMenuViewCustomer').attr('href', '{{ url("loan-management/customers") }}/' + c.customer_id);
        }
    }

    function loadThreadMessages(threadId, markAsRead){
        if (isFetchingThread) return;
        isFetchingThread = true;

        $.ajax({
            url: apiBaseUrl + '/' + threadId,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp){
                var threadData = resp && resp.data ? resp.data : null;
                if (!threadData) return;
                activeContact = threadData;
                renderHeader(threadData);
                renderMessages(threadData.messages || []);

                if (markAsRead) {
                    $.post(apiBaseUrl + '/' + threadId + '/read', { _token: csrf });
                }
            },
            complete: function(){
                isFetchingThread = false;
            }
        });
    }

    function renderMessages(messages){
        var $body = $('#tgChatMessages');
        var prevScrollHeight = $body[0].scrollHeight;

        if (!messages.length) {
            $body.html('<div class="tg-date-divider"><span>No messages yet</span></div>');
            return;
        }

        var html = '';
        var lastDate = '';

        messages.forEach(function(m){
            // Date Divider
            var msgDate = m.created_at ? m.created_at.split(' ')[0] : '';
            if (msgDate && msgDate !== lastDate) {
                lastDate = msgDate;
                html += '<div class="tg-date-divider"><span>' + formatDateOrTime(m.created_at) + '</span></div>';
            }

            var isOwn = m.is_own || m.sender_type === 'staff' || m.sender_type === 'admin';
            var timeFormatted = formatTime(m.created_at);
            var ticks = isOwn ? '<span class="tg-ticks"><i class="fa fa-check"></i><i class="fa fa-check" style="margin-left:-4px"></i></span>' : '';

            var bubbleContent = '';

            // 1. Text Message
            if (m.message_type === 'text' || (!m.message_type && m.message)) {
                bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
            }

            // 2. Voice Audio Message
            if (m.message_type === 'audio' && m.file && m.file.url) {
                var dur = m.audio_duration_seconds ? formatDuration(m.audio_duration_seconds) : '00:20';
                bubbleContent += '<div class="tg-voice-card" data-audio-url="' + esc(m.file.url) + '" data-msg-id="' + m.id + '">' +
                    '<button type="button" class="tg-voice-play-btn js-voice-play" aria-label="Play"><i class="fa fa-play"></i></button>' +
                    '<div class="tg-voice-wave-wrap">' +
                        '<div class="tg-voice-waveform js-voice-waveform">' + generateWaveformBars() + '</div>' +
                        '<div class="tg-voice-timing"><span class="js-voice-timer">' + dur + '</span></div>' +
                    '</div>' +
                '</div>';
                if (m.message) {
                    bubbleContent += '<div class="tg-msg-text" style="margin-top:4px">' + esc(m.message) + '</div>';
                }
            }

            // 3. Image Message
            if (m.message_type === 'image' && m.file && m.file.url) {
                bubbleContent += '<div class="tg-image-wrap js-view-image" data-full-url="' + esc(m.file.url) + '">' +
                    '<img src="' + esc(m.file.url) + '" alt="Image">' +
                '</div>';
                if (m.message) {
                    bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
                }
            }

            // 4. Document / File Message
            if (m.message_type === 'file' && m.file && m.file.url) {
                bubbleContent += '<a href="' + esc(m.file.url) + '" target="_blank" download class="tg-file-card">' +
                    '<div class="tg-file-icon"><i class="fa fa-file-text"></i></div>' +
                    '<div class="tg-file-details">' +
                        '<div class="tg-file-name">' + esc(m.file.name || 'Invoice / Document') + '</div>' +
                        '<div class="tg-file-size">Download file</div>' +
                    '</div>' +
                '</a>';
                if (m.message) {
                    bubbleContent += '<div class="tg-msg-text">' + esc(m.message) + '</div>';
                }
            }

            // 5. Location Message
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

            // Quoted message support
            var quoteHtml = '';
            if (m.quote_text) {
                quoteHtml = '<div class="tg-quote-box">' +
                    '<div class="tg-quote-author">' + esc(m.quote_author || 'Reply') + '</div>' +
                    '<div class="tg-quote-text">' + esc(m.quote_text) + '</div>' +
                '</div>';
            }

            html += '<div class="tg-msg-row ' + (isOwn ? 'own' : '') + '">' +
                '<div class="tg-bubble">' +
                    (!isOwn && m.sender_name ? '<div class="tg-msg-sender">' + esc(m.sender_name) + '</div>' : '') +
                    quoteHtml +
                    bubbleContent +
                    '<div class="tg-msg-meta"><span>' + timeFormatted + '</span> ' + ticks + '</div>' +
                '</div>' +
            '</div>';
        });

        $body.html(html);

        // Scroll to bottom if user was near bottom or on initial load
        $body.scrollTop($body[0].scrollHeight);
    }

    function generateWaveformBars(){
        var heights = [6, 12, 18, 10, 16, 22, 14, 8, 18, 24, 12, 20, 16, 10, 22, 18, 14, 8, 16, 20, 12, 16, 22, 14, 8, 12];
        return heights.map(function(h){
            return '<div class="tg-voice-bar" style="height:' + h + 'px"></div>';
        }).join('');
    }

    // -------------------------------------------------------------
    // AUDIO VOICE PLAYBACK
    // -------------------------------------------------------------
    $(document).on('click', '.js-voice-play', function(){
        var $card = $(this).closest('.tg-voice-card');
        var audioUrl = $card.data('audio-url');
        var msgId = $card.data('msg-id');

        if (currentAudio && currentAudioMsgId === msgId) {
            if (!currentAudio.paused) {
                currentAudio.pause();
                $(this).html('<i class="fa fa-play"></i>');
                return;
            } else {
                currentAudio.play();
                $(this).html('<i class="fa fa-pause"></i>');
                return;
            }
        }

        // Stop any currently playing audio
        if (currentAudio) {
            currentAudio.pause();
            $('.js-voice-play').html('<i class="fa fa-play"></i>');
            $('.tg-voice-bar').removeClass('played');
        }

        var audio = new Audio(audioUrl);
        var $btn = $(this);
        var $bars = $card.find('.tg-voice-bar');
        var $timer = $card.find('.js-voice-timer');

        currentAudio = audio;
        currentAudioMsgId = msgId;

        $btn.html('<i class="fa fa-pause"></i>');
        audio.play();

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
    // SEND TEXT MESSAGE
    // -------------------------------------------------------------
    $('#tgComposerForm').on('submit', function(e){
        e.preventDefault();
        if (!activeThreadId) return;

        // If recording voice, stop & send voice
        if (isRecording) {
            finishVoiceRecording();
            return;
        }

        var text = $('#tgMessageInput').val().trim();
        if (!text) return;

        $('#tgMessageInput').val('');
        toggleSendActionIcon();

        $.ajax({
            url: apiBaseUrl + '/' + activeThreadId + '/messages',
            method: 'POST',
            data: {
                _token: csrf,
                message_type: 'text',
                message: text
            },
            success: function(){
                loadThreadMessages(activeThreadId, false);
                loadChatList(true);
            }
        });
    });

    // Dynamic Send Button Icon (Microphone vs Send Arrow)
    function toggleSendActionIcon(){
        var hasText = $('#tgMessageInput').val().trim().length > 0;
        if (hasText) {
            $('#tgActionSendIcon').removeClass('fa-microphone').addClass('fa-paper-plane');
            $('#tgActionSendBtn').attr('title', 'Send Message');
        } else {
            $('#tgActionSendIcon').removeClass('fa-paper-plane').addClass('fa-microphone');
            $('#tgActionSendBtn').attr('title', 'Record Voice');
        }
    }
    $('#tgMessageInput').on('input', toggleSendActionIcon);

    // -------------------------------------------------------------
    // VOICE RECORDING (MediaRecorder)
    // -------------------------------------------------------------
    $('#tgActionSendBtn').on('click', function(e){
        var hasText = $('#tgMessageInput').val().trim().length > 0;
        if (!hasText && !isRecording) {
            e.preventDefault();
            startVoiceRecording();
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
    $('#tgChatList').on('click', '.tg-chat-item', function(){
        var threadId = $(this).data('thread-id');
        var customerId = $(this).data('customer-id');
        $('.tg-chat-item').removeClass('active');
        $(this).addClass('active');
        openConversation(threadId, customerId);
    });

    $('#tgBackToListBtn').on('click', function(){
        $('#tgAppWrapper').removeClass('in-conversation');
        $('body').removeClass('tg-viewing-chat');
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

    $('#tgFilterPills').on('click', '.tg-pill', function(){
        $('#tgFilterPills .tg-pill').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
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
    loadChatList(false);

    if (initialThreadId) {
        openConversation(initialThreadId, null);
    } else if (initialCustomerId) {
        openConversation(null, initialCustomerId);
    }

    // Polling
    pollTimer = setInterval(function(){
        if (activeThreadId) {
            loadThreadMessages(activeThreadId, false);
        }
        loadChatList(true);
    }, pollMs);

    $(window).on('beforeunload', function(){
        if (pollTimer) clearInterval(pollTimer);
        resetVoiceRecorder();
    });

})(jQuery);
</script>
@endsection
