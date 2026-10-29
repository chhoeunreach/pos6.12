@extends('loanmanagement::layouts.app')
@section('title', 'Live Chat')
@section('hide_breadcrumb', '1')
@php
    $chatUser = auth()->user();
    $canReply = $chatUser && ($chatUser->can('loan_management.chat.reply') || $chatUser->can('loan_management.chat.view'));
    $canType = $chatUser && $chatUser->can('loan_management.chat.reply');
    $canClose = $chatUser && $chatUser->can('loan_management.chat.close');
@endphp
@section('loan_css')
<link rel="stylesheet" href="{{ asset('css/loan-live-chat.css') }}?v={{ filemtime(public_path('css/loan-live-chat.css')) }}">
@endsection
@section('content_body')
<div class="lc-shell" id="loanLiveChat"
     data-api="{{ route('loan-management.chat-api.index') }}"
     data-live-base="{{ route('loan-management.live-chat') }}" data-customer-base="{{ url('loan-management/customers') }}"
     data-initial-thread="{{ $initialThreadId ?? '' }}" data-initial-customer="{{ $initialCustomerId ?? '' }}"
     data-csrf="{{ csrf_token() }}" data-can-send="{{ $canReply ? '1' : '0' }}"
     data-can-type="{{ $canType ? '1' : '0' }}" data-can-close="{{ $canClose ? '1' : '0' }}">
    <header class="lc-toolbar">
        <div><h1><i class="fa fa-comments" aria-hidden="true"></i> Chats</h1><p>Conversations with customers in the customer app.</p></div>
        <div class="lc-toolbar-actions">
            <nav class="lc-channels" aria-label="Chat channels">
                <a href="{{ route('loan-management.chat.index') }}"><i class="fa fa-telegram" aria-hidden="true"></i> Telegram</a>
                <a href="{{ route('loan-management.live-chat') }}" aria-current="page">Live Chat</a>
            </nav>
            <span class="lc-connection" id="lcConnection" role="status" aria-live="polite" data-state="connecting">Connecting…</span>
            <button type="button" class="lc-icon-button" id="lcRefresh" title="Refresh conversations" aria-label="Refresh conversations"><i class="fa fa-refresh" aria-hidden="true"></i></button>
        </div>
    </header>
    <div class="lc-error" id="lcError" role="alert" hidden></div>
    <div class="lc-workspace">
        <aside class="lc-sidebar" aria-label="Conversations">
            <div class="lc-list-tools">
                <label class="lc-search"><i class="fa fa-search" aria-hidden="true"></i><input type="search" id="lcSearch" placeholder="Search name, phone or message…" aria-label="Search conversations" autocomplete="off"></label>
                <div class="lc-filters"><button type="button" class="active" id="lcFilterAll" aria-pressed="true">All conversations</button><button type="button" id="lcFilterUnread" aria-pressed="false">Unread <span id="lcUnreadCount">0</span></button></div>
            </div>
            <div class="lc-contact-list" id="lcContacts"><p class="lc-placeholder">Loading conversations…</p></div>
        </aside>
        <section class="lc-conversation" aria-label="Active conversation">
            <div class="lc-empty" id="lcEmpty"><span><i class="fa fa-comments-o" aria-hidden="true"></i></span><h2>Start a conversation</h2><p>Select a chat or search for a customer to send a message.</p><small>Messages appear here automatically while this page is open.</small></div>
            <div class="lc-thread" id="lcThread" hidden>
                <header class="lc-thread-header">
                    <button type="button" class="lc-icon-button lc-back" id="lcBack" aria-label="Back to conversations"><i class="fa fa-arrow-left" aria-hidden="true"></i></button>
                    <span class="lc-avatar" id="lcAvatar" aria-hidden="true"></span>
                    <div class="lc-thread-identity"><h2 id="lcName"></h2><p id="lcPresence"></p></div>
                    <a class="lc-icon-button" id="lcCustomer" title="Customer details" aria-label="View customer details" hidden><i class="fa fa-user" aria-hidden="true"></i></a>
                    @if($canClose)<button type="button" class="lc-secondary" id="lcToggleClosed">Close chat</button>@endif
                </header>
                <div class="lc-history"><button type="button" id="lcOlder" class="lc-secondary" hidden>Load earlier messages</button></div>
                <div class="lc-messages" id="lcMessages" role="log" aria-live="polite" aria-relevant="additions" aria-label="Messages"></div>
                <button type="button" class="lc-new-messages" id="lcNewMessages" hidden>New messages <i class="fa fa-arrow-down" aria-hidden="true"></i></button>
                <p class="lc-typing" id="lcTyping" aria-live="polite"></p>
                <form class="lc-composer" id="lcComposer">
                    <div class="lc-attachment" id="lcAttachment" hidden><span id="lcAttachmentName"></span><button type="button" id="lcRemoveAttachment" aria-label="Remove attachment">&times;</button></div>
                    <div class="lc-composer-row">
                        @if($canReply)
                        <label class="lc-icon-button" title="Attach image or file"><i class="fa fa-paperclip" aria-hidden="true"></i><input type="file" id="lcFile" accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" aria-label="Attach image or file" hidden></label>
                        @endif
                        <textarea id="lcDraft" rows="2" maxlength="10000" placeholder="Write a message…" aria-label="Message" {{ $canReply ? '' : 'disabled' }}></textarea>
                        <button type="submit" class="lc-send" id="lcSend" {{ $canReply ? '' : 'disabled' }}><i class="fa fa-paper-plane" aria-hidden="true"></i><span>Send</span></button>
                    </div>
                    <div class="lc-compose-footer"><span id="lcSendStatus" role="status" aria-live="polite"></span><small>{{ $canReply ? 'Enter to send · Shift + Enter for a new line' : 'You do not have permission to send messages.' }}</small></div>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
@section('loan_js')
<script src="{{ asset('js/loan-live-chat.js') }}?v={{ filemtime(public_path('js/loan-live-chat.js')) }}"></script>
@endsection
