{{-- Global Telegram-style chat widget: sticky floating button + a two-pane panel (contact
     sidebar + conversation), available on every Installment Management module page. Backed entirely by
     its own tables/service/controller (TelegramChatService, LoanTelegramChatController,
     loan_telegram_chat_threads/messages) - fully independent from the staff's own internal Live
     Chat tool (chat/inbox.blade.php, LoanChatService, loan_chat_threads/messages), which this
     widget never reads from or writes to. Contacts shown are scoped to the staff member's
     permitted business location(s) by the backend. --}}
@php
    $tgBound = isset($customerRow) && $customerRow;
    $tgBoundId = $tgBound ? (int) $customerRow->id : null;
    $tgBoundName = $tgBound ? (trim((string) ($customerRow->khmer_name ?? '')) ?: trim((string) ($customerRow->name ?? ''))) : '';
    $tgBoundLinked = $tgBound ? !empty($customerRow->telegram_chat_id) : false;
    $tgPollMs = (int) config('loanmanagement.chat_polling_seconds', 5) * 1000;
    $tgInvoiceMessageTemplate = \Modules\LoanManagement\Services\BusinessSettingsService::invoiceMessageTemplate();
    $tgInvoiceServerImageEnabled = ($tgRendererBinary = env('WKHTMLTOIMAGE_BINARY')) && is_file($tgRendererBinary);
    $tgUserLocationOptions = collect();
    $tgUserLocationText = 'All locations';
    $tgDefaultLocationId = null;
    try {
        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $tgUser = auth()->user();
            $tgBankDetails = $tgUser && !empty($tgUser->bank_details) ? json_decode($tgUser->bank_details, true) : [];
            $tgBankBranch = trim((string) ($tgBankDetails['branch_id'] ?? $tgBankDetails['branch'] ?? ''));
            $tgPermitted = $tgUser ? $tgUser->permitted_locations() : [];
            $tgLocationQuery = \Illuminate\Support\Facades\DB::connection('mysql_loan')->table('loan_business_locations');
            if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'deleted_at')) {
                $tgLocationQuery->whereNull('deleted_at');
            }
            if ($tgBankBranch !== '') {
                $tgLocationQuery->where(function ($q) use ($tgBankBranch) {
                    if (is_numeric($tgBankBranch)) {
                        $q->where('id', (int) $tgBankBranch);
                        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                            $q->orWhere('main_location_id', (int) $tgBankBranch);
                        }
                    } else {
                        $q->where('name', $tgBankBranch);
                        if (\Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'location_code')) {
                            $q->orWhere('location_code', $tgBankBranch);
                        }
                    }
                });
            } elseif ($tgPermitted !== 'all' && !($tgUser && ($tgUser->can('access_all_locations') || $tgUser->can('loan_management.chat.admin')))) {
                $tgMainLocationIds = array_values(array_filter((array) $tgPermitted));
                if (!empty($tgMainLocationIds) && \Illuminate\Support\Facades\Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                    $tgLocationQuery->where(function ($q) use ($tgMainLocationIds) {
                        $q->whereIn('main_location_id', $tgMainLocationIds)->orWhereIn('id', $tgMainLocationIds);
                    });
                } elseif (!empty($tgMainLocationIds)) {
                    $tgLocationQuery->whereIn('id', $tgMainLocationIds);
                } else {
                    $tgLocationQuery->whereRaw('1 = 0');
                }
            }
            $tgUserLocationOptions = $tgLocationQuery->orderBy('name')->get(['id', 'name']);
            $tgUserLocationText = $tgUserLocationOptions->count() === 1
                ? (string) ($tgUserLocationOptions->first()->name ?? 'My location')
                : ($tgUserLocationOptions->count() > 1 ? 'Multiple locations' : 'No location');
            $tgDefaultLocationId = $tgUserLocationOptions->count() === 1 ? (int) ($tgUserLocationOptions->first()->id ?? 0) : null;
        }
    } catch (\Throwable $e) {
        $tgUserLocationOptions = collect();
    }
@endphp
<style>
    #lmTgFab{position:fixed;right:22px;bottom:22px;top:auto;width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#6dc9f7,#2894e0);color:#fff;border:0;box-shadow:0 6px 20px rgba(41,148,224,.45);font-size:24px;cursor:pointer;z-index:1030;display:flex;align-items:center;justify-content:center;transform:scale(1);transition:transform .18s cubic-bezier(.34, 1.56, .64, 1), box-shadow .18s ease}
    #lmTgFab:hover{transform:scale(1.08);box-shadow:0 8px 24px rgba(41,148,224,.6)}
    #lmTgFab:active{transform:scale(.94)}
    #lmTgFab .lm-tg-fab-icon{width:28px;height:28px;display:block;fill:currentColor;color:#fff}
    #lmTgFab .lm-tg-fab-dot{position:absolute;top:2px;right:2px;width:12px;height:12px;background:#94a3b8;border:2px solid #fff;border-radius:50%}
    #lmTgFab.linked .lm-tg-fab-dot{background:#22c55e}
    #lmTgFab.open{display:none !important}

    /* Hide sticky floating button when any modal is open */
    body.modal-open #lmTgFab {
        display: none !important;
    }

    #lmTgDrawerOverlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1040;opacity:0;transition:opacity .18s ease}
    #lmTgDrawerOverlay.open{display:block;opacity:1}

    #lmTgDrawer{display:none;position:fixed;top:50%;left:50%;width:min(940px,94vw);height:min(660px,86vh);background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.3);z-index:1050;border-radius:14px;overflow:hidden;flex-direction:row;opacity:0;transform:translate(-50%,-50%) scale(.96);transition:opacity .18s ease,transform .18s ease;font-family:"Khmer OS Battambang","Noto Sans Khmer","Segoe UI",Arial,sans-serif}
    #lmTgDrawer.open{display:flex;opacity:1;transform:translate(-50%,-50%) scale(1)}
    .lm-send-invoice-confirm-modal{z-index:1090!important}
    .lm-send-invoice-confirm-backdrop{z-index:1085!important}

    .lm-tg-sidebar{width:300px;flex:0 0 300px;border-right:1px solid #e5e7eb;background:#f7f9fb;display:flex;flex-direction:column;min-height:0}
    .lm-tg-sidebar-head{padding:14px 14px 10px;flex:0 0 auto}
    .lm-tg-sidebar-head h4{margin:0 0 10px;font-size:16px;font-weight:700;color:#0f172a}
    .lm-tg-current-location{font-size:11px;color:#64748b;margin:-4px 0 8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-sidebar-head input,.lm-tg-sidebar-head select{width:100%;border:1px solid #d1d5db;border-radius:18px;padding:8px 14px;font-size:12.5px;outline:none;box-sizing:border-box;background:#fff}
    .lm-tg-sidebar-head input:focus,.lm-tg-sidebar-head select:focus{border-color:#54a9eb}
    .lm-tg-filter-row{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:7px}
    .lm-tg-contact-list{flex:1 1 auto;overflow-y:auto;min-height:0;padding:4px 8px 10px}
    .lm-tg-contact{display:flex;align-items:center;gap:10px;padding:9px 8px;cursor:pointer;border-radius:9px;margin-bottom:2px}
    .lm-tg-contact:hover{background:#eef4fb}
    .lm-tg-contact.active{background:#dbeafe}
    .lm-tg-contact-avatar{width:38px;height:38px;border-radius:50%;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 auto;font-size:14px;position:relative}
    .lm-tg-contact-avatar img,.lm-tg-header .lm-tg-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}
    .lm-tg-contact-avatar .dot{position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:#cbd5e1;border:2px solid #f7f9fb}
    .lm-tg-contact-avatar .dot.linked{background:#22c55e}
    .lm-tg-contact-info{min-width:0;flex:1}
    .lm-tg-contact-name{font-weight:600;font-size:13px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-contact-sub{font-size:11.5px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-contact-badge{min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:10.5px;display:flex;align-items:center;justify-content:center;padding:0 5px;flex:0 0 auto}
    .lm-tg-empty-side{text-align:center;color:#94a3b8;font-size:11.5px;margin-top:24px;padding:0 12px}

    .lm-tg-chat{flex:1 1 auto;display:flex;flex-direction:column;min-width:0;min-height:0}
    .lm-tg-header{background:linear-gradient(135deg,#54a9eb,#4592d6);color:#fff;padding:13px 16px;display:flex;align-items:center;gap:10px;flex:0 0 auto;box-shadow:0 1px 4px rgba(0,0,0,.12)}
    .lm-tg-header .lm-tg-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex:0 0 auto}
    .lm-tg-header-info{flex:1;min-width:0}
    .lm-tg-header-info .name{font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-header-info .status{font-size:11.5px;opacity:.9;display:flex;align-items:center;gap:5px}
    .lm-tg-header-info .status .dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.6)}
    .lm-tg-header-info .status.online .dot{background:#7CFC98}
    .lm-tg-close{background:rgba(255,255,255,.15);border:0;color:#fff;font-size:16px;line-height:1;cursor:pointer;padding:6px 9px;border-radius:50%;flex:0 0 auto}

    .lm-tg-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:14px;background:#e7ecf1;background-image:radial-gradient(rgba(84,169,235,.06) 1px,transparent 1px);background-size:14px 14px}
    .lm-tg-date-sep{text-align:center;margin:12px 0}
    .lm-tg-date-sep span{background:rgba(255,255,255,.75);color:#64748b;font-size:11px;padding:3px 12px;border-radius:12px;box-shadow:0 1px 1px rgba(0,0,0,.05)}
    .lm-tg-row{display:flex;margin-bottom:6px}
    .lm-tg-row.own{justify-content:flex-end}
    .lm-tg-bubble{max-width:74%;padding:7px 10px;border-radius:15px;background:#fff;box-shadow:0 1px 1px rgba(0,0,0,.07);font-size:13.5px;line-height:1.42;overflow-wrap:anywhere;position:relative}
    .lm-tg-text{white-space:pre-wrap}
    .lm-tg-row.own .lm-tg-bubble{background:linear-gradient(135deg,#e3fbd4,#d5f7c4);border-bottom-right-radius:4px}
    .lm-tg-row:not(.own) .lm-tg-bubble{border-bottom-left-radius:4px}
    .lm-tg-meta{display:flex;align-items:center;gap:4px;margin-top:3px;font-size:10px;color:#94a3b8;justify-content:flex-end}
    .lm-tg-meta .fa-telegram{color:#54a9eb}
    .lm-tg-ticks{color:#9ca3af}
    .lm-tg-ticks.read{color:#54a9eb}
    .lm-tg-actions{display:flex;gap:4px;justify-content:flex-end;margin-top:4px;opacity:.85}
    .lm-tg-action{border:0;background:rgba(15,23,42,.08);color:#334155;border-radius:10px;padding:2px 7px;font-size:10px;line-height:1.4;cursor:pointer}
    .lm-tg-action:hover{background:rgba(15,23,42,.14)}
    .lm-tg-action.delete{color:#b91c1c;background:rgba(239,68,68,.12)}
    .lm-tg-image-wrap{margin-top:6px}
    .lm-tg-image-thumb{display:block;max-width:220px;max-height:260px;border-radius:8px;object-fit:cover;cursor:pointer;border:1px solid rgba(15,23,42,.08)}
    .lm-tg-file-card{display:flex;align-items:center;gap:8px;margin-top:6px;padding:7px 8px;border:1px solid rgba(15,23,42,.08);border-radius:10px;background:rgba(255,255,255,.58)}
    .lm-tg-file-card i{color:#64748b}
    .lm-tg-file-name{min-width:0;flex:1;color:#0f172a;font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lm-tg-media-actions{display:flex;gap:5px;flex-wrap:wrap;margin-top:6px}
    .lm-tg-media-action{display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(15,23,42,.12);background:rgba(255,255,255,.82);color:#334155;border-radius:10px;padding:3px 8px;font-size:10.5px;font-weight:800;line-height:1.35;text-decoration:none;cursor:pointer}
    .lm-tg-media-action:hover{background:#fff;color:#1d4ed8;text-decoration:none}
    .lm-tg-image-viewer{display:none;position:fixed;inset:0;background:rgba(15,23,42,.86);z-index:1100;align-items:center;justify-content:center;padding:18px}
    .lm-tg-image-viewer.open{display:flex}
    .lm-tg-image-viewer-inner{position:relative;max-width:96vw;max-height:94vh;display:flex;flex-direction:column;gap:10px;align-items:center}
    .lm-tg-image-viewer img{max-width:96vw;max-height:82vh;object-fit:contain;border-radius:8px;background:#fff;box-shadow:0 20px 50px rgba(0,0,0,.35)}
    .lm-tg-image-viewer-toolbar{display:flex;align-items:center;gap:8px;max-width:96vw}
    .lm-tg-image-viewer-toolbar .lm-tg-media-action{font-size:12px;padding:7px 12px}
    .lm-tg-image-viewer-close{position:absolute;top:-10px;right:-10px;width:34px;height:34px;border:0;border-radius:50%;background:#fff;color:#0f172a;font-size:22px;line-height:1;box-shadow:0 8px 20px rgba(0,0,0,.28)}
    .lm-tg-edited{font-style:italic;color:#94a3b8}
    .lm-tg-empty{text-align:center;color:#94a3b8;font-size:12px;margin-top:30px}
    .lm-tg-composer{flex:0 0 auto;background:#fff;padding:9px 10px;display:flex;gap:8px;align-items:center;border-top:1px solid #e2e8f0}
    .lm-tg-composer input[type=text]{flex:1;border:1px solid #d1d5db;background:#f4f6f8;border-radius:20px;padding:9px 15px;outline:none;font-size:13px}
    .lm-tg-composer input[type=text]:focus{border-color:#54a9eb;background:#fff}
    .lm-tg-composer button{border:0;background:linear-gradient(135deg,#6dc9f7,#2894e0);color:#fff;width:38px;height:38px;border-radius:50%;flex:0 0 auto;cursor:pointer;font-size:14px}
    .lm-tg-composer button:disabled{opacity:.5;cursor:default}
    .lm-tg-not-linked{padding:9px 14px;background:#fff7ed;border-bottom:1px solid #fde68a;font-size:11.5px;color:#92400e;flex:0 0 auto}
    .lm-tg-tools{flex:0 0 auto;display:none;gap:6px;align-items:center;flex-wrap:wrap;padding:7px 10px;background:#fff;border-top:1px solid #e2e8f0}
    .lm-tg-tools button{border:1px solid #dbe4ef;background:#f8fafc;color:#334155;border-radius:14px;padding:5px 9px;font-size:11px;font-weight:700;cursor:pointer}
    .lm-tg-tools button:hover{background:#eff6ff;color:#1d4ed8}
    .lm-tg-tools button.recording{background:#fee2e2;color:#b91c1c;border-color:#fecaca}
    .lm-tg-tools button.clear-voice{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .lm-tg-tools button.clear-voice:hover{background:#ffe4e6;color:#9f1239}

    @media (max-width:760px){
        #lmTgFab{right:14px;left:auto;top:auto;bottom:calc(14px + env(safe-area-inset-bottom,0px));width:48px;height:48px;font-size:20px;box-shadow:0 6px 18px rgba(41,148,224,.4);z-index:1030}
        #lmTgFab .lm-tg-fab-icon{width:24px;height:24px}
        #lmTgFab .lm-tg-fab-dot{width:11px;height:11px;top:1px;right:1px}
        #lmTgDrawer{top:auto;left:8px;right:8px;bottom:calc(76px + env(safe-area-inset-bottom,0px));width:auto;height:min(76vh,620px);border-radius:14px;transform:translateY(14px) scale(.98)}
        #lmTgDrawer.open{transform:translateY(0) scale(1)}
        .lm-tg-sidebar{width:104px;flex-basis:104px}
        .lm-tg-sidebar-head{padding:10px 8px 8px}
        .lm-tg-sidebar-head h4{font-size:13px;margin-bottom:8px}
        .lm-tg-current-location,.lm-tg-filter-row{display:none}
        .lm-tg-sidebar-head input{padding:7px 8px;font-size:11px;border-radius:12px}
        .lm-tg-contact-list{padding:4px 5px 8px}
        .lm-tg-contact{justify-content:center;padding:7px 4px}
        .lm-tg-contact-avatar{width:34px;height:34px}
        .lm-tg-contact-info{display:none}
        .lm-tg-header{padding:10px 12px}
        .lm-tg-header .lm-tg-avatar{width:34px;height:34px}
        .lm-tg-header-info .name{font-size:13px}
        .lm-tg-header-info .status{font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lm-tg-body{padding:10px}
        .lm-tg-bubble{max-width:86%;font-size:12.5px}
        .lm-tg-image-thumb{max-width:190px;max-height:230px}
        .lm-tg-composer{padding:8px;gap:6px}
        .lm-tg-composer input[type=text]{min-width:0;padding:8px 11px;font-size:12px}
        .lm-tg-composer button{width:34px;height:34px;font-size:12px}
    }
    @media (max-width:380px){
        #lmTgFab{right:10px;bottom:calc(10px + env(safe-area-inset-bottom,0px));width:44px;height:44px}
        #lmTgFab .lm-tg-fab-icon{width:22px;height:22px}
        #lmTgDrawer{left:6px;right:6px;bottom:calc(70px + env(safe-area-inset-bottom,0px));height:min(74vh,560px)}
        .lm-tg-sidebar{width:82px;flex-basis:82px}
    }
</style>

<button type="button" id="lmTgFab" title="Open Telegram Chat" aria-label="Open Telegram Chat" class="{{ $tgBoundLinked ? 'linked' : '' }}">
    <svg class="lm-tg-fab-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M21.94 4.16c.28-1.18-.86-2.16-1.95-1.68L2.83 10.02c-1.22.54-1.19 2.29.05 2.78l4.28 1.68 1.63 5.15c.38 1.21 1.91 1.56 2.77.64l2.34-2.5 4.55 3.35c1.02.75 2.49.18 2.78-1.06l.71-15.9ZM8.2 13.57l9.8-6.23c.45-.29.92.31.53.67l-8.07 7.47-.31 3.36-1.95-5.27Z"/>
    </svg>
    <span class="lm-tg-fab-dot"></span>
</button>

<div id="lmTgDrawerOverlay"></div>
<div id="lmTgDrawer">
    <aside class="lm-tg-sidebar">
        <div class="lm-tg-sidebar-head">
            <h4><i class="fa fa-telegram"></i> Chats</h4>
            <div class="lm-tg-current-location"><i class="fa fa-map-marker"></i> {{ $tgUserLocationText }}</div>
            <input type="text" id="lmTgSearchInput" placeholder="Search name, phone, code or invoice" autocomplete="off">
            <div class="lm-tg-filter-row">
                <select id="lmTgLocationFilter" aria-label="Filter by location">
                    <option value="">All branches</option>
                    @foreach($tgUserLocationOptions as $location)
                        <option value="{{ (int) $location->id }}" {{ (string) $tgDefaultLocationId === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                    @endforeach
                </select>
                <select id="lmTgLinkedFilter" aria-label="Filter by Telegram link">
                    <option value="">All</option>
                    <option value="linked">Linked</option>
                    <option value="unlinked">Not linked</option>
                </select>
            </div>
        </div>
        <div class="lm-tg-contact-list" id="lmTgContactList">
            <div class="lm-tg-empty-side">Loading contacts...</div>
        </div>
    </aside>
    <main class="lm-tg-chat">
        <div class="lm-tg-header">
            <div class="lm-tg-avatar" id="lmTgHeaderAvatar"><i class="fa fa-telegram"></i></div>
            <div class="lm-tg-header-info">
                <div class="name" id="lmTgHeaderName">Select a conversation</div>
                <div class="status" id="lmTgHeaderStatus"><span class="dot"></span><span id="lmTgHeaderStatusText">Choose a customer from the list</span></div>
            </div>
            <button type="button" class="lm-tg-close" id="lmTgDrawerCloseX" aria-label="Close">&times;</button>
        </div>
        <div class="lm-tg-not-linked" id="lmTgNotLinkedBanner" style="display:none">This customer hasn't connected Telegram yet. Messages sent here are still saved, but won't reach them until they connect.</div>
        <div class="lm-tg-body" id="lmTgMessages">
            <div class="lm-tg-empty">Select a conversation from the left to start chatting.</div>
        </div>
        <div id="lmTgComposerError" style="display:none;padding:4px 14px 0;font-size:11px;color:#dc2626;background:#fff"></div>
        <div class="lm-tg-tools" id="lmTgTools">
            <button type="button" id="lmTgSendInvoice"><i class="fa fa-file-text-o"></i> Send Invoice</button>
            <button type="button" id="lmTgSendPay"><i class="fa fa-money"></i> Pay</button>
            <button type="button" id="lmTgPickImages"><i class="fa fa-image"></i> Images</button>
            <button type="button" id="lmTgPickDocs"><i class="fa fa-paperclip"></i> Documents</button>
            <button type="button" id="lmTgSendLocation"><i class="fa fa-map-marker"></i> Location</button>
            <button type="button" id="lmTgVoiceBtn"><i class="fa fa-microphone"></i> Voice</button>
            <button type="button" id="lmTgVoiceStopBtn" style="display:none"><i class="fa fa-stop"></i> Stop</button>
            <button type="button" id="lmTgVoiceSendBtn" style="display:none"><i class="fa fa-paper-plane"></i> Send Voice</button>
            <button type="button" id="lmTgVoiceClearBtn" class="clear-voice" style="display:none"><i class="fa fa-times"></i> Clear</button>
            <input type="file" id="lmTgImageInput" accept="image/*" multiple style="display:none">
            <input type="file" id="lmTgDocInput" multiple style="display:none">
        </div>
        <form class="lm-tg-composer" id="lmTgComposerForm" style="display:none">
            <input type="text" id="lmTgMessageInput" placeholder="Write a message" autocomplete="off">
            <button type="submit" aria-label="Send"><i class="fa fa-paper-plane"></i></button>
        </form>
    </main>
</div>
<div class="lm-tg-image-viewer" id="lmTgImageViewer" aria-hidden="true">
    <div class="lm-tg-image-viewer-inner">
        <button type="button" class="lm-tg-image-viewer-close" id="lmTgImageViewerClose" aria-label="Close">&times;</button>
        <img src="" alt="" id="lmTgImageViewerImage">
        <div class="lm-tg-image-viewer-toolbar">
            <a href="#" target="_blank" rel="noopener" class="lm-tg-media-action" id="lmTgImageViewerOpen"><i class="fa fa-external-link"></i> Open full</a>
            <a href="#" download class="lm-tg-media-action" id="lmTgImageViewerDownload"><i class="fa fa-download"></i> Download</a>
        </div>
    </div>
</div>

<script>
(function($){
    var csrf = '{{ csrf_token() }}';
    var boundCustomerId = @json($tgBoundId);
    var boundCustomerName = @json($tgBoundName);
    var boundTelegramLinked = @json($tgBoundLinked);
    var chatBaseUrl = '{{ url("loan-management/telegram-chat-api/chats") }}';
    var pollMs = parseInt('{{ $tgPollMs }}', 10);
    var invoiceMessageTemplate = @json($tgInvoiceMessageTemplate);
    var invoiceServerImageEnabled = @json((bool) $tgInvoiceServerImageEnabled);
    var activeThreadId = null;
    var activeCustomerId = null;
    var loanPrintBaseUrl = '{{ url("loan-management/loans") }}';
    var pollTimer = null;
    var loadingThread = false;
    var searchTimer = null;
    var contacts = [];
    var activeCustomerName = '';
    var activeLoanContext = {};
    var mediaRecorder = null;
    var voiceChunks = [];
    var voiceStartedAt = null;
    var voiceElapsedBeforePause = 0;
    var voicePausedAt = null;
    var pendingVoiceFile = null;
    var pendingVoiceDuration = 0;
    var discardVoiceOnStop = false;
    var sendingInvoiceImage = false;

    function esc(v){ return $('<div>').text(v == null ? '' : String(v)).html(); }
    function pad2(v){ return String(v).padStart(2, '0'); }
    function formatTime(value){
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }
    function dateKey(value){
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    function dateLabel(value){
        var key = dateKey(value);
        if (!key) return '';
        var today = dateKey(new Date().toISOString());
        var yestDate = new Date(); yestDate.setDate(yestDate.getDate() - 1);
        var yesterday = dateKey(yestDate.toISOString());
        if (key === today) return 'Today';
        if (key === yesterday) return 'Yesterday';
        var d = new Date(String(value).replace(' ', 'T'));
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function apiGet(url){
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        }).then(function(r){ return r.json(); });
    }
    function apiPostJson(url, payload){
        return apiJson(url, 'POST', payload);
    }
    function apiPostForm(url, formData){
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: formData
        }).then(parseJsonResponse);
    }
    function apiJson(url, method, payload){
        return fetch(url, {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(payload || {})
        }).then(parseJsonResponse);
    }
    function parseJsonResponse(response){
        return response.json()
            .catch(function(){
                return {
                    success: false,
                    message: response.ok ? 'Invalid server response.' : ('Request failed with status ' + response.status + '.')
                };
            })
            .then(function(json){
                if (!response.ok && json && json.success !== false) {
                    json.success = false;
                }
                if (!response.ok && json && !json.message) {
                    json.message = 'Request failed with status ' + response.status + '.';
                }
                return json;
            });
    }
    function singleData(resp){ return resp && resp.data ? resp.data : null; }
    function listData(resp){ return resp && Array.isArray(resp.data) ? resp.data : []; }

    function profileName(profile, fallback){
        return (profile && (profile.display_name || profile.customer_name || profile.name)) || fallback || 'Customer';
    }
    function profileInitial(name){
        return (name || 'C').charAt(0).toUpperCase();
    }
    function setHeader(profile, linked, statusText){
        if (typeof profile === 'string') {
            profile = {display_name: profile};
        }
        profile = profile || {};
        var name = profileName(profile);
        var isLinked = typeof profile.telegram_linked === 'boolean' ? profile.telegram_linked : !!linked;
        var details = profile.subtitle || [profile.phone, profile.customer_code, profile.location_name].filter(Boolean).join(' · ') || '';

        if (profile.avatar_url) {
            $('#lmTgHeaderAvatar').html('<img src="' + esc(profile.avatar_url) + '" alt="">');
        } else {
            $('#lmTgHeaderAvatar').html(esc(profileInitial(name)));
        }

        $('#lmTgHeaderName').text(name);
        $('#lmTgHeaderStatus').toggleClass('online', isLinked);
        $('#lmTgHeaderStatusText').text(statusText || (details ? details + ' · ' : '') + (isLinked ? 'connected via Telegram' : 'not connected'));
        $('#lmTgNotLinkedBanner').toggle(!isLinked);
    }

    function formatChatText(text){
        var safe = esc(text || '');

        safe = safe.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        return '<div class="lm-tg-text">' + safe + '</div>';
    }

    function fileNameFromUrl(url){
        try {
            var path = new URL(url, window.location.origin).pathname;
            var last = path.split('/').filter(Boolean).pop();
            return decodeURIComponent(last || 'chat-file');
        } catch (e) {
            return 'chat-file';
        }
    }

    function fileName(file, fallback){
        return (file && file.name) || fallback || fileNameFromUrl(file && file.url ? file.url : '');
    }

    function downloadUrl(url){
        if (!url) return '#';
        return url + (String(url).indexOf('?') === -1 ? '?' : '&') + 'download=1';
    }

    function mediaActions(file, isImage){
        if (!file || !file.url) return '';
        var name = esc(fileName(file, isImage ? 'chat-image' : 'chat-file'));
        return '' +
            '<div class="lm-tg-media-actions">' +
                (isImage ? '<button type="button" class="lm-tg-media-action js-lm-tg-view-image" data-url="' + esc(file.url) + '" data-name="' + name + '"><i class="fa fa-search-plus"></i> View full</button>' : '') +
                '<a href="' + esc(file.url) + '" target="_blank" rel="noopener" class="lm-tg-media-action"><i class="fa fa-external-link"></i> Open</a>' +
                '<a href="' + esc(downloadUrl(file.url)) + '" download="' + name + '" class="lm-tg-media-action"><i class="fa fa-download"></i> Download</a>' +
            '</div>';
    }

    function renderImageMessage(file){
        if (!file || !file.url) return '';
        var name = fileName(file, 'chat-image');
        return '' +
            '<div class="lm-tg-image-wrap">' +
                '<img src="' + esc(file.url) + '" alt="' + esc(name) + '" class="lm-tg-image-thumb js-lm-tg-view-image" data-url="' + esc(file.url) + '" data-name="' + esc(name) + '">' +
                mediaActions(file, true) +
            '</div>';
    }

    function renderFileMessage(file){
        if (!file || !file.url) return '';
        var name = fileName(file, 'Download file');
        return '' +
            '<div class="lm-tg-file-card">' +
                '<i class="fa fa-paperclip"></i>' +
                '<a href="' + esc(file.url) + '" target="_blank" rel="noopener" class="lm-tg-file-name">' + esc(name) + '</a>' +
            '</div>' +
            mediaActions(file, false);
    }

    function renderMessages(messages){
        var box = $('#lmTgMessages').empty();
        if (!messages || !messages.length) {
            box.html('<div class="lm-tg-empty">No messages yet. Say hello!</div>');
            return;
        }
        var lastDateKey = null;
        messages.forEach(function(m){
            var thisDateKey = dateKey(m.created_at);
            if (thisDateKey !== lastDateKey) {
                box.append('<div class="lm-tg-date-sep"><span>' + esc(dateLabel(m.created_at)) + '</span></div>');
                lastDateKey = thisDateKey;
            }

            var body = formatChatText(m.message || '');
            if (m.message_type === 'image' && m.file && m.file.url) body += renderImageMessage(m.file);
            if (m.message_type === 'file' && m.file && m.file.url) body += renderFileMessage(m.file);
            if (m.message_type === 'audio' && m.file && m.file.url) body += '<div><audio controls src="'+esc(m.file.url)+'" style="max-width:200px;margin-top:6px"></audio></div>';
            if (m.message_type === 'location' && m.latitude && m.longitude) body += '<div><a href="https://maps.google.com/?q='+esc(m.latitude)+','+esc(m.longitude)+'" target="_blank"><i class="fa fa-map-marker"></i> Open location</a></div>';
            var ticks = '';
            if (m.is_own) {
                var isRead = !!m.read_at;
                ticks = '<span class="lm-tg-ticks' + (isRead ? ' read' : '') + '">' + (isRead ? '&#10003;&#10003;' : '&#10003;') + '</span>';
            }
            var edited = m.edited ? '<span class="lm-tg-edited">edited</span>' : '';
            var actions = '';
            if (m.can_update || m.can_delete) {
                actions += '<div class="lm-tg-actions">';
                if (m.can_update) {
                    actions += '<button type="button" class="lm-tg-action edit" data-message-id="' + esc(m.id) + '" data-message-text="' + esc(m.message || '') + '"><i class="fa fa-pencil"></i> Edit</button>';
                }
                if (m.can_delete) {
                    actions += '<button type="button" class="lm-tg-action delete" data-message-id="' + esc(m.id) + '"><i class="fa fa-trash"></i> Delete</button>';
                }
                actions += '</div>';
            }
            box.append(
                '<div class="lm-tg-row '+(m.is_own ? 'own' : '')+'">' +
                    '<div class="lm-tg-bubble">' + body +
                        '<div class="lm-tg-meta"><span>' + esc(formatTime(m.created_at)) + '</span>' + edited + ticks + '</div>' +
                        actions +
                    '</div>' +
                '</div>'
            );
        });
        box.scrollTop(box[0].scrollHeight);
    }

    function openImageViewer(url, name){
        if (!url) return;
        name = name || fileNameFromUrl(url);
        $('#lmTgImageViewerImage').attr('src', url).attr('alt', name);
        $('#lmTgImageViewerOpen').attr('href', url);
        $('#lmTgImageViewerDownload').attr('href', downloadUrl(url)).attr('download', name);
        $('#lmTgImageViewer').addClass('open').attr('aria-hidden', 'false');
    }

    function closeImageViewer(){
        $('#lmTgImageViewer').removeClass('open').attr('aria-hidden', 'true');
        $('#lmTgImageViewerImage').attr('src', '');
    }

    $(document).on('click', '.js-lm-tg-view-image', function(){
        openImageViewer($(this).data('url'), $(this).data('name'));
    });
    $('#lmTgImageViewerClose').on('click', closeImageViewer);
    $('#lmTgImageViewer').on('click', function(e){
        if (e.target === this) closeImageViewer();
    });
    $(document).on('keydown', function(e){
        if (e.key === 'Escape' && $('#lmTgImageViewer').hasClass('open')) {
            closeImageViewer();
        }
    });

    function renderContacts(rows){
        contacts = rows || [];
        var list = $('#lmTgContactList').empty();
        if (!contacts.length) {
            list.html('<div class="lm-tg-empty-side">No customers found in your branch.</div>');
            return;
        }
        contacts.forEach(function(r){
            if (!r.customer_id) return;
            var name = r.display_name || r.customer_name || 'Customer';
            var inv = r.invoice_no ? 'Inv '+r.invoice_no : '';
            var inst = r.installment_no ? 'Inst ' + r.installment_no + (r.installment_total ? '/' + r.installment_total : '') : '';
            var fallbackSub = [inv, inst, r.customer_phone, r.location_name].filter(Boolean).join(' · ') || 'New chat';
            var sub = fallbackSub;
            var badge = Number(r.unread_count || 0) > 0 ? '<span class="lm-tg-contact-badge">' + Number(r.unread_count) + '</span>' : '';
            var avatar = r.avatar_url
                ? '<img src="' + esc(r.avatar_url) + '" alt="">'
                : esc(profileInitial(name));
            var item = $('<div class="lm-tg-contact" data-customer-id="'+r.customer_id+'" data-thread-id="'+(r.id || '')+'"></div>')
                .append('<div class="lm-tg-contact-avatar">' + avatar + '<span class="dot'+(r.telegram_linked ? ' linked' : '')+'"></span></div>')
                .append('<div class="lm-tg-contact-info"><div class="lm-tg-contact-name">' + esc(name) + '</div><div class="lm-tg-contact-sub">' + esc(sub) + '</div></div>')
                .append(badge);
            if (String(r.customer_id) === String(activeCustomerId)) item.addClass('active');
            item.on('click', function(){ openContact(r.customer_id, name, !!r.telegram_linked, {profile: r}); });
            list.append(item);
        });
    }

    function loadContacts(search){
        var params = [
            'search=' + encodeURIComponent(search || ''),
            'location_id=' + encodeURIComponent($('#lmTgLocationFilter').val() || ''),
            'telegram_status=' + encodeURIComponent($('#lmTgLinkedFilter').val() || '')
        ];
        return apiGet(chatBaseUrl + '?' + params.join('&')).then(function(resp){
            renderContacts(listData(resp));
        }).catch(function(){});
    }

    function loadThread(showLoading){
        if (!activeThreadId || loadingThread) return;
        loadingThread = true;
        if (showLoading) $('#lmTgMessages').html('<div class="lm-tg-empty">Loading conversation...</div>');
        apiGet(chatBaseUrl + '/' + activeThreadId).then(function(resp){
            var thread = singleData(resp);
            if (thread && String(thread.customer_id) === String(activeCustomerId)) {
                setHeader(thread.customer_profile || thread, !!thread.telegram_linked);
                activeCustomerName = profileName(thread.customer_profile || thread, activeCustomerName);
            }
            renderMessages(thread ? thread.messages : []);
            apiPostJson(chatBaseUrl + '/' + activeThreadId + '/read', {});
        }).catch(function(){}).finally(function(){ loadingThread = false; });
    }

    function startPolling(){
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = window.setInterval(function(){
            loadThread(false);
            loadContacts($('#lmTgSearchInput').val());
        }, pollMs);
    }
    function stopPolling(){
        if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
    }

    function openContact(customerId, name, linked, context){
        activeLoanContext = context || {};
        var initialProfile = activeLoanContext.profile || {display_name: name, telegram_linked: !!linked};
        if (!activeLoanContext.loan_id && initialProfile.loan_id) {
            activeLoanContext.loan_id = initialProfile.loan_id;
            activeLoanContext.loan_number = initialProfile.loan_number || '';
            activeLoanContext.balance_amount = initialProfile.balance_amount || '';
        }
        activeCustomerName = profileName(initialProfile, name);
        activeCustomerId = customerId;
        $('.lm-tg-contact').removeClass('active');
        $('.lm-tg-contact[data-customer-id="'+customerId+'"]').addClass('active');
        $('#lmTgComposerForm').show();
        $('#lmTgTools').css('display', 'flex');
        setHeader(initialProfile, linked);
        $('#lmTgMessages').html('<div class="lm-tg-empty">Loading conversation...</div>');
        apiPostJson(chatBaseUrl, {customer_id: customerId}).then(function(resp){
            var thread = singleData(resp);
            if (thread && thread.id) {
                activeThreadId = thread.id;
                setHeader(thread.customer_profile || thread, !!thread.telegram_linked);
                activeCustomerName = profileName(thread.customer_profile || thread, activeCustomerName);
                if (!activeLoanContext.loan_id && thread.loan_id) {
                    activeLoanContext.loan_id = thread.loan_id;
                    activeLoanContext.loan_number = thread.loan_number || '';
                    activeLoanContext.balance_amount = thread.balance_amount || '';
                }
                loadThread(true);
                startPolling();
                if (activeLoanContext.auto_action === 'invoice') {
                    activeLoanContext.auto_action = '';
                    window.setTimeout(function(){ $('#lmTgSendInvoice').trigger('click'); }, 250);
                } else if (activeLoanContext.auto_action === 'pay') {
                    activeLoanContext.auto_action = '';
                    window.setTimeout(function(){ $('#lmTgSendPay').trigger('click'); }, 250);
                }
            } else {
                $('#lmTgMessages').html('<div class="lm-tg-empty">Unable to open this chat.</div>');
            }
        }).catch(function(){
            $('#lmTgMessages').html('<div class="lm-tg-empty">Unable to open this chat.</div>');
        });
    }

    function openDrawer(){
        $('#lmTgDrawer').addClass('open');
        $('#lmTgDrawerOverlay').addClass('open');
        $('#lmTgFab').addClass('open');

        loadContacts('');

        if (boundCustomerId && !activeThreadId) {
            openContact(boundCustomerId, boundCustomerName, boundTelegramLinked, {});
        }
    }

    function closeDrawer(){
        $('#lmTgDrawer').removeClass('open');
        $('#lmTgDrawerOverlay').removeClass('open');
        $('#lmTgFab').removeClass('open');
        stopPolling();
    }

    $('#lmTgFab').on('click', openDrawer);
    $('#lmTgDrawerCloseX, #lmTgDrawerOverlay').on('click', closeDrawer);

    // Auto-close Telegram drawer whenever any Bootstrap modal opens
    $(document).on('show.bs.modal', function(e){
        if (!$(e.target).hasClass('lm-send-invoice-confirm-modal')) {
            if ($('#lmTgDrawer').hasClass('open')) {
                closeDrawer();
            }
        }
    });

    $('#lmTgSearchInput').on('input', function(){
        var q = $(this).val();
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function(){ loadContacts(q); }, 300);
    });
    $('#lmTgLocationFilter, #lmTgLinkedFilter').on('change', function(){
        loadContacts($('#lmTgSearchInput').val());
    });

    var sendingMessage = false;
    function showComposerError(message){
        if (!message) {
            $('#lmTgComposerError').hide().text('');
            return;
        }
        var $err = $('#lmTgComposerError').text(message).show();
        window.clearTimeout(showComposerError._t);
        showComposerError._t = window.setTimeout(function(){ $err.fadeOut(200); }, 4000);
    }
    function showVisibleError(message){
        showComposerError(message);
        if (window.toastr) {
            toastr.error(message);
        } else {
            alert(message);
        }
    }

    $('#lmTgComposerForm').on('submit', function(e){
        e.preventDefault();
        var text = $('#lmTgMessageInput').val().trim();
        if (!text || !activeThreadId || sendingMessage) return;

        sendingMessage = true;
        var $btn = $('#lmTgComposerForm button[type=submit]').prop('disabled', true);

        apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {message_type: 'text', message: text})
            .then(function(resp){
                if (resp && resp.success) {
                    $('#lmTgMessageInput').val('');
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to send message. Check your connection.');
            })
            .finally(function(){
                sendingMessage = false;
                $btn.prop('disabled', false);
                $('#lmTgMessageInput').trigger('focus');
            });
    });

    function sendTelegramText(text){
        if (!text || !activeThreadId) return;
        return apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {message_type: 'text', message: text})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send message.');
                }
            })
            .catch(function(){ showComposerError('Failed to send message.'); });
    }

    function invoiceCaption(customerName){
        var name = customerName || activeCustomerName || 'Customer';

        return String(invoiceMessageTemplate || '')
            .split('{Customer Name}').join(name)
            .split('{Business Name}').join(@json(\Modules\LoanManagement\Services\BusinessSettingsService::businessName()));
    }

    function invoicePrintUrl(loanId){
        return loanPrintBaseUrl + '/' + encodeURIComponent(loanId) + '/print?auto_print=0&_lm_invoice_preview=1';
    }

    function confirmInvoiceSend(loanId, caption){
        var deferred = $.Deferred();
        var $modal = $(
            '<div class="modal fade lm-send-invoice-confirm-modal" tabindex="-1" role="dialog">' +
                '<div class="modal-dialog modal-xl" role="document" style="width:96%;max-width:1180px;">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<h4 class="modal-title"><i class="fa fa-file-text-o"></i> Preview Invoice Before Sending</h4>' +
                        '</div>' +
                        '<div class="modal-body" style="padding:0;">' +
                            '<div style="display:grid;grid-template-columns:320px minmax(0,1fr);height:78vh;">' +
                                '<div style="border-right:1px solid #e5e7eb;padding:16px;overflow:auto;background:#f8fafc;">' +
                                    '<div style="font-weight:700;margin-bottom:8px;color:#111827;">Message to customer</div>' +
                                    '<div class="lm-tg-bubble" style="max-width:none;background:#e3fbd4;box-shadow:none;">' + formatChatText(caption) + '</div>' +
                                '</div>' +
                                '<iframe id="lmSendInvoiceConfirmFrame" src="' + esc(invoicePrintUrl(loanId)) + '" style="width:100%;height:100%;border:0;background:#fff;"></iframe>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
                            '<button type="button" class="btn btn-primary lm-confirm-send-invoice-now" data-compress="1">' +
                                '<i class="fa fa-compress"></i> Send Compressed' +
                            '</button>' +
                            '<button type="button" class="btn btn-success lm-confirm-send-invoice-now" data-compress="0">' +
                                '<i class="fa fa-image"></i> Send Original' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        ).appendTo('body');

        var resolved = false;
        $modal.on('click', '.lm-confirm-send-invoice-now', function(){
            resolved = true;
            $modal.modal('hide');
            deferred.resolve({
                previewFrameId: 'lmSendInvoiceConfirmFrame',
                compress: String($(this).data('compress')) !== '0'
            });
        });
        $modal.on('hidden.bs.modal', function(){
            $modal.remove();
            if (!resolved) {
                deferred.reject({cancelled: true});
            }
        });
        $modal.on('shown.bs.modal', function(){
            $('.modal-backdrop').last().addClass('lm-send-invoice-confirm-backdrop');
        });
        $modal.modal({backdrop: 'static', keyboard: false});

        return deferred.promise();
    }

    function sendInvoiceImage(){
        if (!activeThreadId || !activeLoanContext.loan_id) {
            return false;
        }
        if (sendingInvoiceImage) {
            return true;
        }

        var caption = invoiceCaption();
        var $button = $('#lmTgSendInvoice');
        sendingInvoiceImage = true;
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Previewing');
        showComposerError('');

        function resetInvoiceButton(){
            sendingInvoiceImage = false;
            $button.prop('disabled', false).html('<i class="fa fa-file-text-o"></i> Send Invoice');
        }

        Promise.resolve(confirmInvoiceSend(activeLoanContext.loan_id, caption))
            .then(function(sendOptions){
                $button.html('<i class="fa fa-spinner fa-spin"></i> Sending Invoice');
                activeLoanContext.preview_frame_id = (sendOptions && sendOptions.previewFrameId) || activeLoanContext.preview_frame_id || '';
                return sendInvoiceImageFast(activeLoanContext.loan_id, caption, !(sendOptions && sendOptions.compress === false));
            })
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                    return null;
                }
            })
            .then(function(resp){
                if (resp === null) {
                    return;
                }
                if (!(resp && resp.success)) {
                    showComposerError((resp && resp.message) || 'Failed to send invoice image.');
                    return;
                }

                loadThread(false);
                loadContacts($('#lmTgSearchInput').val());
            })
            .then(function(){
                resetInvoiceButton();
            }, function(error){
                if (error && error.cancelled) {
                    resetInvoiceButton();
                    return;
                }
                showComposerError('Failed to send invoice image.');
                resetInvoiceButton();
            });

        return true;
    }

    function sendInvoiceImageFromServer(loanId, caption){
        return apiPostJson(chatBaseUrl + '/' + activeThreadId + '/invoice-image', {
            loan_id: loanId,
            message: caption || ''
        });
    }

    function sendInvoiceImageFast(loanId, caption, compressPreview){
        return sendInvoiceImageFromPreview(caption, compressPreview !== false)
            .then(function(resp){
                if (resp && resp.success) {
                    return resp;
                }

                if (invoiceServerImageEnabled) {
                    return sendInvoiceImageFromServer(loanId, caption);
                }

                return resp;
            })
            .catch(function(error){
                if (invoiceServerImageEnabled) {
                    return sendInvoiceImageFromServer(loanId, caption);
                }

                throw error;
            });
    }

    function sendInvoiceImageFromPreview(caption, compressPreview){
        caption = caption || invoiceCaption();
        showComposerError(compressPreview ? 'Compressing invoice image...' : 'Preparing original invoice image...');

        return buildLoanPrintImageFromPreview(activeLoanContext.loan_id, activeLoanContext.preview_frame_id || '')
            .then(function(blob){
                return compressPreview ? compressInvoiceImageBlob(blob, 820, 1300, 0.58) : blob;
            })
            .then(function(blob){
                showComposerError('');
                var fileName = 'loan-invoice-' + String(activeLoanContext.loan_number || activeLoanContext.loan_id).replace(/[^a-zA-Z0-9_-]+/g, '-') + '.jpg';
                var file = new File([blob], fileName, {type: 'image/jpeg'});
                return sendTelegramFile(file, 'image', caption);
            });
    }

    function compressInvoiceImageBlob(blob, maxWidth, maxHeight, quality){
        if (!blob) {
            return Promise.reject(new Error('Invoice image was not created.'));
        }

        return new Promise(function(resolve){
            var image = new Image();
            var objectUrl = URL.createObjectURL(blob);

            image.onload = function(){
                URL.revokeObjectURL(objectUrl);

                var width = image.naturalWidth || image.width;
                var height = image.naturalHeight || image.height;
                var ratio = Math.min(1, maxWidth / width, maxHeight / height);
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(width * ratio));
                canvas.height = Math.max(1, Math.round(height * ratio));

                var context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function(compressed){
                    resolve(compressed || blob);
                }, 'image/jpeg', quality);
            };

            image.onerror = function(){
                URL.revokeObjectURL(objectUrl);
                resolve(blob);
            };

            image.src = objectUrl;
        });
    }

    function buildLoanPrintImageFromPreview(loanId, previewFrameId){
        var existingFrame = previewFrameId ? document.getElementById(previewFrameId) : null;
        if (existingFrame && existingFrame.contentWindow && typeof existingFrame.contentWindow.loanManagementBuildLoanPrintImageBlob === 'function') {
            return existingFrame.contentWindow.loanManagementBuildLoanPrintImageBlob(0.9, 'image/jpeg', 0.66)
                .catch(function(){
                    return buildLoanPrintImageFromHiddenFrame(loanId);
                });
        }

        return buildLoanPrintImageFromHiddenFrame(loanId);
    }

    function buildLoanPrintImageFromHiddenFrame(loanId){
        return new Promise(function(resolve, reject){
            var iframe = document.createElement('iframe');
            var timeout = window.setTimeout(function(){
                cleanup();
                reject(new Error('Print preview image timed out.'));
            }, 30000);

            function cleanup(){
                window.clearTimeout(timeout);
                if (iframe.parentNode) {
                    iframe.parentNode.removeChild(iframe);
                }
            }

            iframe.style.position = 'fixed';
            iframe.style.left = '-10000px';
            iframe.style.top = '0';
            iframe.style.width = '1240px';
            iframe.style.height = '1800px';
            iframe.style.opacity = '0';
            iframe.style.pointerEvents = 'none';
            iframe.setAttribute('aria-hidden', 'true');
            iframe.onload = function(){
                try {
                    var win = iframe.contentWindow;
                    if (!win || typeof win.loanManagementBuildLoanPrintImageBlob !== 'function') {
                        throw new Error('Print preview image builder is not available.');
                    }

                    win.loanManagementBuildLoanPrintImageBlob(0.9, 'image/jpeg', 0.66)
                        .then(function(blob){
                            cleanup();
                            resolve(blob);
                        })
                        .catch(function(error){
                            cleanup();
                            reject(error);
                        });
                } catch (error) {
                    cleanup();
                    reject(error);
                }
            };
            iframe.onerror = function(){
                cleanup();
                reject(new Error('Unable to load print preview.'));
            };

            iframe.src = loanPrintBaseUrl + '/' + encodeURIComponent(loanId) + '/print?_lm_telegram_image=1&_lm_reload=' + Date.now();
            document.body.appendChild(iframe);
        });
    }

    function sendTelegramFile(file, type, caption, durationSeconds){
        if (!file || !activeThreadId) return $.Deferred().reject().promise();
        var fd = new FormData();
        fd.append('message_type', type);
        fd.append('file', file);
        fd.append('message', caption || '');
        if (durationSeconds) fd.append('duration_seconds', durationSeconds);

        return apiPostForm(chatBaseUrl + '/' + activeThreadId + '/messages', fd)
            .then(function(resp){
                if (!(resp && resp.success)) {
                    showComposerError((resp && resp.message) || 'Failed to send file.');
                }
                return resp;
            })
            .catch(function(){ showComposerError('Failed to send file.'); });
    }

    function sendSelectedFiles(files, type){
        files = Array.prototype.slice.call(files || []);
        if (!files.length) return;
        var caption = $('#lmTgMessageInput').val().trim();
        var chain = Promise.resolve();
        files.forEach(function(file){
            chain = chain.then(function(){ return sendTelegramFile(file, type, caption); });
        });
        chain.then(function(){
            $('#lmTgMessageInput').val('');
            loadThread(false);
            loadContacts($('#lmTgSearchInput').val());
        });
    }

    $('#lmTgSendInvoice').on('click', function(){
        if (sendInvoiceImage()) {
            return;
        }

        var loanNo = activeLoanContext.loan_number ? ('Installment #: ' + activeLoanContext.loan_number + '\n') : '';
        var balance = activeLoanContext.balance_amount ? ('Balance: ' + activeLoanContext.balance_amount + '\n') : '';
        var text = window.prompt('Invoice message:', 'Dear ' + activeCustomerName + ',\n' + loanNo + balance + 'Please review your invoice and contact us if you have questions.');
        if (text) sendTelegramText(text);
    });

    $('#lmTgSendPay').on('click', function(){
        var loanId = activeLoanContext.loan_id || '';
        if (!loanId) {
            showVisibleError('No current loan found for this customer.');
            return;
        }

        if (!$('.view_modal').length) {
            showVisibleError('Payment modal is not available on this page.');
            return;
        }

        closeDrawer();
        $.ajax({
            url: loanPrintBaseUrl + '/' + encodeURIComponent(loanId) + '/payment/create?return_to=' + encodeURIComponent(window.location.href),
            dataType: 'html',
            beforeSend: function(){
                $('.view_modal').html(
                    '<div class="modal-dialog modal-lg" role="document">' +
                        '<div class="modal-content">' +
                            '<div class="modal-body text-center" style="padding:32px 16px;">' +
                                '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                ).modal('show');
            },
            success: function(html){
                $('.view_modal').html(html).modal('show');
            },
            error: function(){
                $('.view_modal').modal('hide');
                showVisibleError('Unable to open payment form.');
            }
        });
    });

    $('#lmTgPickImages').on('click', function(){ $('#lmTgImageInput').trigger('click'); });
    $('#lmTgPickDocs').on('click', function(){ $('#lmTgDocInput').trigger('click'); });
    $('#lmTgImageInput').on('change', function(){ sendSelectedFiles(this.files, 'image'); this.value = ''; });
    $('#lmTgDocInput').on('change', function(){ sendSelectedFiles(this.files, 'file'); this.value = ''; });

    $('#lmTgSendLocation').on('click', function(){
        if (!navigator.geolocation) {
            showComposerError('Location is not available in this browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function(pos){
            apiPostJson(chatBaseUrl + '/' + activeThreadId + '/messages', {
                message_type: 'location',
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude
            }).then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                } else {
                    showComposerError((resp && resp.message) || 'Failed to send location.');
                }
            });
        }, function(){ showComposerError('Unable to get location permission.'); });
    });

    function resetVoiceDraft(){
        pendingVoiceFile = null;
        pendingVoiceDuration = 0;
        voiceChunks = [];
        voiceStartedAt = null;
        voiceElapsedBeforePause = 0;
        voicePausedAt = null;
        discardVoiceOnStop = false;
        $('#lmTgVoiceStopBtn').hide().prop('disabled', false).html('<i class="fa fa-stop"></i> Stop');
        $('#lmTgVoiceSendBtn').hide().prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send Voice');
        $('#lmTgVoiceClearBtn').hide().prop('disabled', false).html('<i class="fa fa-times"></i> Clear');
        $('#lmTgVoiceBtn').removeClass('recording').prop('disabled', false).html('<i class="fa fa-microphone"></i> Voice').show();
    }

    function currentVoiceDuration(){
        var elapsed = voiceElapsedBeforePause;
        if (voiceStartedAt && (!mediaRecorder || mediaRecorder.state === 'recording')) {
            elapsed += Date.now() - voiceStartedAt;
        }

        return Math.max(1, Math.round(elapsed / 1000));
    }

    $('#lmTgVoiceBtn').on('click', function(){
        var $btn = $(this);
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            if (typeof mediaRecorder.pause === 'function') {
                mediaRecorder.pause();
                voiceElapsedBeforePause += Date.now() - voiceStartedAt;
                voicePausedAt = Date.now();
                voiceStartedAt = null;
                $btn.html('<i class="fa fa-play"></i> Start');
                showComposerError('Voice recording paused.');
            } else {
                showVisibleError('Pause is not available in this browser.');
            }
            return;
        }

        if (mediaRecorder && mediaRecorder.state === 'paused') {
            if (typeof mediaRecorder.resume === 'function') {
                mediaRecorder.resume();
                voiceStartedAt = Date.now();
                voicePausedAt = null;
                $btn.addClass('recording').html('<i class="fa fa-pause"></i> Pause');
                showComposerError('Voice recording resumed.');
            }
            return;
        }

        if (!navigator.mediaDevices || !window.MediaRecorder) {
            showComposerError('Voice recording is not available in this browser.');
            return;
        }

        resetVoiceDraft();
        navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream){
            voiceChunks = [];
            voiceStartedAt = Date.now();
            voiceElapsedBeforePause = 0;
            pendingVoiceFile = null;
            pendingVoiceDuration = 0;
            discardVoiceOnStop = false;
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function(event){
                if (event.data && event.data.size) voiceChunks.push(event.data);
            };
            mediaRecorder.onstop = function(){
                stream.getTracks().forEach(function(track){ track.stop(); });
                if (discardVoiceOnStop) {
                    mediaRecorder = null;
                    resetVoiceDraft();
                    showComposerError('Voice recording cleared.');
                    return;
                }
                var blob = new Blob(voiceChunks, {type: mediaRecorder.mimeType || 'audio/webm'});
                pendingVoiceFile = new File([blob], 'voice-message.webm', {type: blob.type});
                pendingVoiceDuration = currentVoiceDuration();
                mediaRecorder = null;
                voiceStartedAt = null;
                voicePausedAt = null;
                $('#lmTgVoiceBtn').removeClass('recording').hide();
                $('#lmTgVoiceStopBtn').hide().prop('disabled', false);
                $('#lmTgVoiceSendBtn').show().prop('disabled', false);
                $('#lmTgVoiceClearBtn').show().prop('disabled', false).html('<i class="fa fa-times"></i> Clear');
                showComposerError('Voice ready. Click Send Voice.');
            };
            mediaRecorder.start();
            $btn.addClass('recording').html('<i class="fa fa-pause"></i> Pause');
            $('#lmTgVoiceStopBtn').show().prop('disabled', false);
            $('#lmTgVoiceSendBtn').hide();
            $('#lmTgVoiceClearBtn').show().prop('disabled', false).html('<i class="fa fa-times"></i> Cancel');
            showComposerError('Recording voice...');
        }).catch(function(){
            resetVoiceDraft();
            showComposerError('Unable to access microphone.');
        });
    });

    $('#lmTgVoiceStopBtn').on('click', function(){
        if (!mediaRecorder || ['recording', 'paused'].indexOf(mediaRecorder.state) === -1) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Preparing');
        if (mediaRecorder.state === 'recording' && voiceStartedAt) {
            voiceElapsedBeforePause += Date.now() - voiceStartedAt;
            voiceStartedAt = null;
        }
        mediaRecorder.stop();
    });

    $('#lmTgVoiceClearBtn').on('click', function(){
        if (mediaRecorder) {
            discardVoiceOnStop = true;
            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing');
            $('#lmTgVoiceStopBtn, #lmTgVoiceSendBtn, #lmTgVoiceBtn').prop('disabled', true);
            if (['recording', 'paused'].indexOf(mediaRecorder.state) !== -1) {
                try {
                    mediaRecorder.stop();
                } catch (e) {
                    mediaRecorder = null;
                    resetVoiceDraft();
                    showComposerError('Voice recording cleared.');
                }
            }
            return;
        }

        resetVoiceDraft();
        showComposerError('Voice recording cleared.');
    });

    $('#lmTgVoiceSendBtn').on('click', function(){
        if (!pendingVoiceFile) {
            showVisibleError('No voice recording is ready to send.');
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending');
        $('#lmTgVoiceClearBtn').prop('disabled', true);
        sendTelegramFile(pendingVoiceFile, 'audio', '', pendingVoiceDuration)
            .then(function(resp){
                if (resp && resp.success) {
                    resetVoiceDraft();
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showVisibleError((resp && resp.message) || 'Failed to send voice message.');
                    $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send Voice');
                    $('#lmTgVoiceClearBtn').prop('disabled', false);
                }
            })
            .catch(function(){
                showVisibleError('Failed to send voice message.');
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send Voice');
                $('#lmTgVoiceClearBtn').prop('disabled', false);
            });
    });

    $('#lmTgMessages').on('click', '.lm-tg-action.edit', function(){
        if (!activeThreadId) return;
        var messageId = $(this).data('message-id');
        var currentText = $(this).data('message-text') || '';
        var nextText = window.prompt('Update Telegram message:', currentText);
        if (nextText === null) return;
        nextText = nextText.trim();
        if (!nextText) {
            showComposerError('Message cannot be empty.');
            return;
        }

        apiJson(chatBaseUrl + '/' + activeThreadId + '/messages/' + messageId, 'PUT', {message: nextText})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to update message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to update message.');
            });
    });

    $('#lmTgMessages').on('click', '.lm-tg-action.delete', function(){
        if (!activeThreadId) return;
        var messageId = $(this).data('message-id');
        if (!window.confirm('Delete this Telegram chat message?')) {
            return;
        }

        apiJson(chatBaseUrl + '/' + activeThreadId + '/messages/' + messageId, 'DELETE', {})
            .then(function(resp){
                if (resp && resp.success) {
                    loadThread(false);
                    loadContacts($('#lmTgSearchInput').val());
                } else {
                    showComposerError((resp && resp.message) || 'Failed to delete message.');
                }
            })
            .catch(function(){
                showComposerError('Failed to delete message.');
            });
    });

    window.loanManagementOpenTelegramCustomer = function(customerId, name, linked, context){
        $('#lmTgDrawer').addClass('open');
        $('#lmTgDrawerOverlay').addClass('open');
        $('#lmTgFab').addClass('open');
        loadContacts('');
        openContact(customerId, name || 'Customer', !!linked, context || {});
    };

    window.loanManagementSendInvoiceToTelegramCustomer = function(customerId, name, linked, context){
        context = context || {};
        $('#lmTgDrawer').addClass('open');
        $('#lmTgDrawerOverlay').addClass('open');
        $('#lmTgFab').addClass('open');
        loadContacts('');

        activeLoanContext = context;
        activeCustomerId = customerId;
        activeCustomerName = name || 'Customer';
        setHeader({display_name: activeCustomerName, telegram_linked: !!linked}, linked, 'Preparing invoice...');
        $('#lmTgComposerForm').show();
        $('#lmTgTools').css('display', 'flex');
        $('#lmTgMessages').html('<div class="lm-tg-empty">Preparing invoice message...</div>');
        showComposerError('');

        return apiPostJson(chatBaseUrl, {customer_id: customerId})
            .then(function(resp){
                var thread = singleData(resp);
                if (!thread || !thread.id) {
                    throw new Error((resp && resp.message) || 'Unable to open this customer chat.');
                }

                activeThreadId = thread.id;
                setHeader(thread.customer_profile || thread, !!thread.telegram_linked, 'Previewing invoice...');
                activeCustomerName = profileName(thread.customer_profile || thread, activeCustomerName);
                startPolling();

                var loanId = context.loan_id || '';
                if (!loanId) {
                    throw new Error('No loan selected for this invoice.');
                }

                var caption = context.message || invoiceCaption(activeCustomerName);
                return confirmInvoiceSend(loanId, caption)
                    .then(function(sendOptions){
                        activeLoanContext.preview_frame_id = (sendOptions && sendOptions.previewFrameId) || activeLoanContext.preview_frame_id || '';
                        setHeader(thread.customer_profile || thread, !!thread.telegram_linked, 'Sending invoice...');
                        return sendInvoiceImageFast(loanId, caption, !(sendOptions && sendOptions.compress === false));
                    });
            })
            .then(function(resp){
                if (!(resp && resp.success)) {
                    throw new Error((resp && resp.message) || 'Failed to send invoice image.');
                }

                loadThread(false);
                loadContacts($('#lmTgSearchInput').val());
                return resp;
            })
            .catch(function(error){
                if (error && error.cancelled) {
                    throw error;
                }
                var message = error && error.message ? error.message : 'Failed to send invoice image.';
                showComposerError(message);
                throw error;
            });
    };
})(jQuery);
</script>
