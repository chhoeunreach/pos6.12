@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = request('lang') === 'km' || $loanLanguage === 'km' || request()->cookie('lm_lang') === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;

    $cards = [
        ['key' => 'total_loans', 'label' => $lmText('All Installments', 'រំលស់ទាំងអស់'), 'icon' => 'fa fa-files-o', 'tone' => 'slate', 'url' => route('loan-management.loans.index')],
        ['key' => 'pending_requests', 'label' => $lmText('Pending Requests', 'សំណើកំពុងរង់ចាំ'), 'icon' => 'fa fa-hourglass-half', 'tone' => 'amber', 'url' => route('loan-management.loans.index', ['status' => 'pending'])],
        ['key' => 'due_today', 'label' => $lmText('Due Today', 'ដល់ថ្ងៃបង់ថ្ងៃនេះ'), 'icon' => 'fa fa-calendar-check-o', 'tone' => 'blue', 'url' => route('loan-management.operations.page', ['page' => 'due-today'])],
        ['key' => 'overdue_accounts', 'label' => $lmText('Overdue Accounts', 'គណនីហួសកំណត់'), 'icon' => 'fa fa-exclamation-circle', 'tone' => 'red', 'url' => route('loan-management.collection.page', ['page' => 'overdue-accounts'])],
        ['key' => 'broken_ptp', 'label' => $lmText('Broken PTP', 'ខកខានសន្យា'), 'icon' => 'fa fa-chain-broken', 'tone' => 'amber', 'url' => route('loan-management.collection.page', ['page' => 'broken-promise'])],
        ['key' => 'blacklist_customers', 'label' => $lmText('Blacklist Customers', 'អតិថិជនបញ្ជីខ្មៅ'), 'icon' => 'fa fa-user-times', 'tone' => 'red', 'url' => route('loan-management.blacklist.index')],
    ];
    $dashboardBadgeCounts = \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $dashboardUnreadChats = (int) ($dashboardBadgeCounts['unread_chat'] ?? 0);
    $dashboardPendingVisits = (int) ($dashboardBadgeCounts['pending_visits'] ?? 0);
    $dashboardOverdue = (int) ($quickCards['overdue_accounts'] ?? 0);
    $dashboardBlacklist = (int) ($quickCards['blacklist_customers'] ?? 0);
    $dashboardDueToday = (int) ($quickCards['due_today'] ?? 0);
    $dashboardBrokenPtp = (int) ($quickCards['broken_ptp'] ?? 0);
    $dashboardHighRisk = (int) ($quickCards['high_risk_customers'] ?? 0);
    $dashboardTodayCollection = (float) ($quickCards['today_collection'] ?? ($quickCards['collection_amount_today'] ?? 0));
    $dashboardMonthlyIncome = (float) ($quickCards['monthly_income'] ?? 0);
    $dashboardPriorityTotal = $dashboardOverdue + $dashboardDueToday + $dashboardBrokenPtp + $dashboardHighRisk + $dashboardPendingVisits + $dashboardUnreadChats;
    $dashboardHealthLabel = !empty($systemHealth) && !empty($systemHealth['has_issues'])
        ? ($systemHealth['has_critical_errors'] ? $lmText('Critical system notice', 'ប្រព័ន្ធមានបញ្ហាសំខាន់') : $lmText('System needs attention', 'ប្រព័ន្ធត្រូវការត្រួតពិនិត្យ'))
        : $lmText('System healthy', 'ប្រព័ន្ធដំណើរការល្អ');
    $dashboardHealthTone = !empty($systemHealth) && !empty($systemHealth['has_issues'])
        ? ($systemHealth['has_critical_errors'] ? 'danger' : 'warning')
        : 'success';
    $dashboardActions = [
        ['label' => $lmText('Telegram Chats', 'ជជែក Telegram'), 'icon' => 'fa fa-comments', 'tone' => 'primary', 'url' => route('loan-management.chat.index')],
        ['label' => $lmText('Collect Payment', 'ប្រមូលប្រាក់បង់'), 'icon' => 'fa fa-money', 'tone' => 'success', 'url' => route('loan-management.operations.page', ['page' => 'due-today'])],
        ['label' => $lmText('Customers', 'អតិថិជន'), 'icon' => 'fa fa-users', 'tone' => 'info', 'url' => route('loan-management.customers.index')],
        ['label' => $lmText('Reports', 'របាយការណ៍'), 'icon' => 'fa fa-line-chart', 'tone' => 'neutral', 'url' => route('loan-management.reports.index')],
    ];
    $dashboardSummary = [
        ['label' => $lmText('Today Collection', 'ប្រមូលថ្ងៃនេះ'), 'value' => number_format($dashboardTodayCollection, 2), 'icon' => 'fa fa-money', 'tone' => 'green'],
        ['label' => $lmText('Monthly Income', 'ចំណូលប្រចាំខែ'), 'value' => number_format($dashboardMonthlyIncome, 2), 'icon' => 'fa fa-bar-chart', 'tone' => 'blue'],
        ['label' => $lmText('Priority Work', 'ការងារអាទិភាព'), 'value' => number_format($dashboardPriorityTotal), 'icon' => 'fa fa-bolt', 'tone' => 'amber'],
        ['label' => $lmText('Unread Chat', 'សារមិនទាន់អាន'), 'value' => number_format($dashboardUnreadChats), 'icon' => 'fa fa-comments', 'tone' => 'slate', 'key' => 'unread_chats'],
    ];
@endphp

<div class="lm-dashboard">
    <section class="lm-dashboard-command">
        <div class="lm-dashboard-command__main">
            <span class="lm-dashboard-command__eyebrow"><i class="fa fa-tachometer"></i> {{ $lmText('Loan Management', 'ការគ្រប់គ្រងរំលស់') }}</span>
            <h2 class="lm-dashboard-command__title">{{ $lmText('Dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</h2>
            <p class="lm-dashboard-command__subtitle">{{ $lmText('Monitor collections, customer follow-up, overdue risk, and daily payment activity from one clear workspace.', 'តាមដានការប្រមូលប្រាក់ ការតាមដានអតិថិជន ហានិភ័យហួសកំណត់ និងសកម្មភាពបង់ប្រាក់ប្រចាំថ្ងៃក្នុងផ្ទាំងតែមួយ។') }}</p>
            <div class="lm-dashboard-command__chips">
                <span class="lm-dashboard-chip lm-dashboard-chip--{{ $dashboardHealthTone }}"><i class="fa fa-heartbeat"></i> {{ $dashboardHealthLabel }}</span>
                <span class="lm-dashboard-chip"><i class="fa fa-calendar"></i> {{ now()->format('M d, Y') }}</span>
            </div>
        </div>
        <div class="lm-dashboard-command__actions">
            @foreach($dashboardActions as $action)
                <a href="{{ $action['url'] }}" class="lm-dashboard-action lm-dashboard-action--{{ $action['tone'] }}">
                    <span><i class="{{ $action['icon'] }}"></i></span>
                    <strong>{{ $action['label'] }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="lm-dashboard-summary-strip">
        @foreach($dashboardSummary as $summary)
            <div class="lm-dashboard-summary-card">
                <span class="lm-dashboard-summary-card__icon lm-tone-{{ $summary['tone'] }}"><i class="{{ $summary['icon'] }}"></i></span>
                <div>
                    <span class="lm-dashboard-summary-card__label">{{ $summary['label'] }}</span>
                    <strong class="lm-dashboard-summary-card__value"@if(!empty($summary['key'])) data-loan-card="{{ $summary['key'] }}" data-format="int" @endif>{{ $summary['value'] }}</strong>
                </div>
            </div>
        @endforeach
    </section>

    @if (!empty($systemHealth) && !empty($systemHealth['has_issues']))
        <div class="alert alert-{{ $systemHealth['has_critical_errors'] ? 'danger' : 'warning' }}" style="border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.08); border-left: 6px solid {{ $systemHealth['has_critical_errors'] ? '#dc2626' : '#f59e0b' }};">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <span style="font-size: 24px;">{{ $systemHealth['has_critical_errors'] ? '⚠️' : '🔔' }}</span>
                    <div>
                        <h4 style="margin: 0 0 6px; font-weight: 800; font-size: 16px; color: {{ $systemHealth['has_critical_errors'] ? '#991b1b' : '#92400e' }};">
                            {{ $lmText('System & Database Requirement Notice', 'ដំណឹងត្រួតពិនិត្យប្រព័ន្ធ និង មូលដ្ឋានទិន្នន័យ') }}
                            <span class="badge" style="background: {{ $systemHealth['has_critical_errors'] ? '#dc2626' : '#d97706' }}; margin-left: 8px;">{{ $systemHealth['error_count'] }} {{ $lmText('Errors', 'កំហុស') }}</span>
                        </h4>
                        <p style="margin: 0 0 10px; font-size: 13.5px; color: #475569;">
                            {{ $lmText('Some server requirements, extensions, database tables, or seed data require attention.', 'ប្រព័ន្ធបានរកឃើញតម្រូវការ ឬតារាងទិន្នន័យមួយចំនួនដែលមិនទាន់បានរៀបចំរួចរាល់។') }}
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            @foreach(array_slice($systemHealth['alerts'], 0, 3) as $alert)
                                <div style="font-size: 13px; line-height: 1.4;">
                                    <strong>• {{ $lmIsKhmer ? $alert['title_km'] : $alert['title_en'] }}:</strong> {{ $lmIsKhmer ? $alert['message_km'] : $alert['message_en'] }}
                                    @if(!empty($alert['action_route']) && \Illuminate\Support\Facades\Route::has($alert['action_route']))
                                        <a href="{{ route($alert['action_route']) }}" class="btn btn-xs btn-default" style="margin-left: 6px; font-weight: 700;">
                                            <i class="fa fa-users"></i> {{ $lmText('Manage Users', 'គ្រប់គ្រងអ្នកប្រើ') }}
                                        </a>
                                    @elseif(!empty($alert['remedy']))
                                        <code style="background: #0f172a; color: #38bdf8; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 6px;">{{ $alert['remedy'] }}</code>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div style="align-self: center;">
                    <a href="{{ route('loan-management.system.status') }}" class="btn btn-sm btn-{{ $systemHealth['has_critical_errors'] ? 'danger' : 'warning' }}" style="font-weight: 700; border-radius: 8px; padding: 8px 16px;">
                        <i class="fa fa-wrench"></i> {{ $lmText('View System Diagnostics', 'មើលការពិនិត្យប្រព័ន្ធលម្អិត') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
    <div class="lm-dashboard-pane is-active" data-dashboard-pane="overview">
    <section class="lm-dashboard-cards">
        @foreach($cards as $card)
            @php $val = $quickCards[$card['key']] ?? 0; @endphp
            <a href="{{ $card['url'] }}" class="lm-stat-card lm-stat-card--link">
                <span class="lm-stat-card__icon lm-tone-{{ $card['tone'] }}"><i class="{{ $card['icon'] }}"></i></span>
                <div>
                    <span class="lm-stat-card__label">{{ $card['label'] }}</span>
                    <span class="lm-stat-card__value" data-loan-card="{{ $card['key'] }}" data-format="{{ in_array($card['key'], ['collection_amount_today']) ? 'money' : 'int' }}">{{ in_array($card['key'], ['collection_amount_today']) ? number_format((float) $val, 2) : (int) $val }}</span>
                    <span class="lm-stat-card__meta"><i class="fa fa-arrow-circle-right"></i> {{ $lmText('View details', 'មើលលម្អិត') }}</span>
                </div>
            </a>
        @endforeach
    </section>

    <section class="lm-dashboard-grid">
        <div class="lm-dashboard-panel lm-dashboard-panel--feature lm-dashboard-panel--quick-payment">
            <div class="lm-dashboard-panel__body lm-dashboard-panel__body--quick-actions">
                <div class="lm-quick-grid">
                    <div class="lm-quick-box lm-quick-box--loan">
                        <div class="lm-quick-box__topline">
                            <h4 class="lm-quick-box__title"><span class="lm-quick-box__icon lm-quick-box__icon--pay"><i class="fa fa-money"></i></span> {{ $lmText('Collect Payment', 'ប្រមូលប្រាក់បង់') }}</h4>
                            <div class="lm-quick-box__meta">
                                <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-calendar"></i> {{ $lmText('Due Date', 'ថ្ងៃត្រូវបង់') }}</span>
                                <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-money"></i> {{ $lmText('Balance', 'សមតុល្យ') }}</span>
                                <span class="lm-quick-box__chip lm-quick-box__chip--pay"><i class="fa fa-check-circle"></i> {{ $lmText('Quick Pay', 'បង់ប្រាក់រហ័ស') }}</span>
                            </div>
                        </div>
                        <p class="lm-quick-box__subtitle">{{ $lmText('Search by name, phone, or installment # to collect payment.', 'ស្វែងរកតាមឈ្មោះ លេខទូរស័ព្ទ ឬលេខរំលស់ដើម្បីប្រមូលប្រាក់បង់។') }}</p>
                        <div class="lm-quick-filter-row">
                            <div class="form-group lm-quick-input lm-quick-input--search">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                    <input type="text" class="form-control" id="loanDashboardQuickSearchInput" placeholder="{{ $lmText('Search installment #, customer name, phone...', 'ស្វែងរកលេខរំលស់ ឈ្មោះអតិថិជន លេខទូរស័ព្ទ...') }}">
                                </div>
                            </div>
                            <div class="form-group lm-quick-input lm-quick-input--location">
                                <div class="input-group">
                                    <span class="input-group-addon"><i class="fa fa-map-marker"></i></span>
                                    <select class="form-control" id="loanDashboardQuickLocationFilter">
                                        <option value="">{{ $lmText('All Locations', 'គ្រប់ទីតាំង') }}</option>
                                        @foreach($locations as $location)
                                            <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="lm-table-wrap lm-table-wrap--hover-actions lm-collect-payment-scroll">
                            <table class="table table-condensed lm-dashboard-table lm-mini-table" id="loanDashboardQuickSearchTable">
                                <thead>
                                    <tr>
                                        <th class="lm-col-customer">{{ $lmText('Customer', 'អតិថិជន') }}</th>
                                        <th class="lm-col-code">{{ $lmText('Installment #', 'លេខរំលស់') }}</th>
                                        <th class="lm-col-date">{{ $lmText('Next Pay Date', 'ថ្ងៃបង់បន្ទាប់') }}</th>
                                        <th class="text-right lm-col-money">{{ $lmText('Paid', 'បានបង់') }}</th>
                                        <th class="text-right lm-col-money">{{ $lmText('Balance', 'សមតុល្យ') }}</th>
                                        <th class="text-center lm-col-status">{{ $lmText('Status', 'ស្ថានភាព') }}</th>
                                        <th class="text-center lm-col-action">{{ $lmText('Action', 'សកម្មភាព') }}</th>
                                    </tr>
                                </thead>
                                <tbody data-loan-table="dashboard_quick_search">
                                    <tr><td colspan="7" class="text-center text-muted">{{ $lmText('Type to search for payment collection.', 'វាយបញ្ចូលដើម្បីស្វែងរកការប្រមូលប្រាក់។') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="lm-collect-payment-mobile-list" id="loanDashboardQuickSearchMobile">
                            <div class="lm-mobile-loan-empty">{{ $lmText('Type to search for payment collection.', 'វាយបញ្ចូលដើម្បីស្វែងរកការប្រមូលប្រាក់។') }}</div>
                        </div>
                        <div class="lm-quick-box__footer"><i class="fa fa-bolt"></i> {{ $lmText('Search customer name or phone for fast payment.', 'ស្វែងរកឈ្មោះអតិថិជន ឬលេខទូរស័ព្ទដើម្បីបង់ប្រាក់រហ័ស។') }}</div>
                    </div>

                </div>
            </div>
        </div>

        <div class="lm-customer-chat-source" id="lmCustomerChatPanel">
        <div class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div class="lm-chat-header-text">
                    <h3 class="lm-dashboard-panel__title">{{ $lmText('Customer Chat', 'ជជែកជាមួយអតិថិជន') }}</h3>
                    <p class="lm-dashboard-panel__hint">{{ $lmText('Recent conversations before field follow-up.', 'ការសន្ទនាថ្មីៗមុនពេលចុះតាមដានផ្ទាល់។') }}</p>
                </div>
                <div class="lm-chat-header-actions">
                    <span class="lm-dashboard-panel__badge" id="loanUnreadChatBadge"><i class="fa fa-comments"></i> {{ $dashboardUnreadChats }} {{ $lmText('unread', 'មិនទាន់អាន') }}</span>
                </div>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-chat-card">
                    <div class="lm-chat-card__toolbar">
                        <h4 class="lm-chat-card__title">{{ $lmText('Chat', 'ការជជែក') }}</h4>
                        <div class="lm-chat-card__actions">
                            <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-ellipsis-h"></i></span>
                            <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-expand"></i></span>
                            @if(Route::has('loan-management.chat.index'))
                                <a href="{{ route('loan-management.chat.index') }}" class="lm-chat-card__icon" title="{{ $lmText('Open Messenger style inbox', 'បើកប្រអប់សារ Messenger') }}">
                                    <i class="fa fa-pencil-square-o"></i>
                                </a>
                            @else
                                <span class="lm-chat-card__icon" aria-hidden="true"><i class="fa fa-pencil-square-o"></i></span>
                            @endif
                        </div>
                    </div>

                    <div class="lm-chat-card__search">
                        <i class="fa fa-search"></i>
                        <span>{{ $lmText('Search Messenger', 'ស្វែងរក Messenger') }}</span>
                    </div>

                    <div class="lm-chat-card__tabs">
                        <span class="lm-chat-card__tab is-active">{{ $lmText('All', 'ទាំងអស់') }}</span>
                        <span class="lm-chat-card__tab">{{ $lmText('Unread', 'មិនទាន់អាន') }}</span>
                        <span class="lm-chat-card__tab">{{ $lmText('Groups', 'ក្រុម') }}</span>
                        <span class="lm-chat-card__tab"><i class="fa fa-ellipsis-h"></i></span>
                    </div>

                    <div class="lm-chat-card__summary">
                        <div class="lm-chat-card__summary-box">
                            <span class="lm-chat-card__summary-label">{{ $lmText('Unread queue', 'ជួរមិនទាន់អាន') }}</span>
                            <span class="lm-chat-card__summary-value" id="loanUnreadChatSummaryValue">{{ $dashboardUnreadChats }}</span>
                            <span class="lm-chat-card__summary-note">{{ $lmText('Messages waiting for staff reply', 'សាររង់ចាំបុគ្គលិកឆ្លើយតប') }}</span>
                        </div>
                        <div class="lm-chat-card__summary-box">
                            <span class="lm-chat-card__summary-label">{{ $lmText('Pending visits', 'ដំណើរចុះជួបកំពុងរង់ចាំ') }}</span>
                            <span class="lm-chat-card__summary-value">{{ $dashboardPendingVisits }}</span>
                            <span class="lm-chat-card__summary-note">{{ $lmText('Field follow-up cases linked to chat', 'ករណីតាមដានផ្ទាល់ភ្ជាប់ជាមួយការជជែក') }}</span>
                        </div>
                    </div>

                    <div class="lm-chat-card__list">
                        <div class="lm-chat-card__request">
                            <span class="lm-chat-card__request-avatar"><i class="fa fa-comments"></i></span>
                            <div>
                                <p class="lm-chat-card__request-title">{{ $lmText('New message request', 'សំណើសារថ្មី') }}</p>
                                <p class="lm-chat-card__request-subtitle">
                                    {{ $dashboardUnreadChats > 0 ? $dashboardUnreadChats . ' ' . $lmText('unread customer chat(s) waiting for reply.', 'ការជជែកអតិថិជនមិនទាន់អានរង់ចាំការឆ្លើយតប។') : $lmText('No unread customer chats right now.', 'មិនមានការជជែកអតិថិជនមិនទាន់អាននៅពេលនេះទេ។') }}
                                </p>
                            </div>
                            <span class="lm-chat-card__request-arrow"><i class="fa fa-angle-right"></i></span>
                        </div>

                        @if(!empty($recentChats))
                            @foreach($recentChats as $chat)
                                @php
                                    $chatUrl = Route::has('loan-management.chat.detail')
                                        ? route('loan-management.chat.detail', $chat['id'])
                                        : (Route::has('loan-management.chat.index') ? route('loan-management.chat.index') : '#');
                                    $chatName = trim((string) ($chat['display_name'] ?? 'Customer Chat'));
                                    $avatarSeed = mb_substr($chatName !== '' ? $chatName : 'C', 0, 1);
                                    $previewText = trim((string) ($chat['last_message'] ?: ($chat['display_subtitle'] ?: $lmText('Open the conversation to continue the follow-up.', 'បើកការសន្ទនាដើម្បីបន្តការតាមដាន។'))));
                                    $timeText = !empty($chat['last_message_at']) ? \Carbon\Carbon::parse($chat['last_message_at'])->diffForHumans() : ucfirst((string) ($chat['status'] ?: 'open'));
                                @endphp
                                <a href="{{ $chatUrl }}" class="lm-chat-card__item">
                                    <span class="lm-chat-card__avatar-wrap">
                                        <span class="lm-chat-card__avatar">{{ $avatarSeed }}</span>
                                        <span class="lm-chat-card__presence"></span>
                                    </span>
                                    <span>
                                        <span class="lm-chat-card__name">{{ \Illuminate\Support\Str::limit($chatName, 34) }}</span>
                                        <span class="lm-chat-card__preview">
                                            {{ \Illuminate\Support\Str::limit($previewText, 60) }}
                                            <span class="lm-chat-card__time">&middot; {{ $timeText }}</span>
                                        </span>
                                    </span>
                                    @if(($chat['unread_count'] ?? 0) > 0)
                                        <span class="lm-chat-card__dot" title="{{ (int) $chat['unread_count'] }} {{ $lmText('unread', 'មិនទាន់អាន') }}"></span>
                                    @else
                                        <span></span>
                                    @endif
                                </a>
                            @endforeach
                        @else
                            <div class="lm-chat-card__empty">
                                <p style="margin:0 0 12px;">
                                    {{ $dashboardPendingVisits > 0 ? $dashboardPendingVisits . ' ' . $lmText('pending collection visit(s) still need follow-up.', 'ដំណើរចុះជួបប្រមូលប្រាក់កំពុងរង់ចាំការតាមដាន។') : $lmText('No recent customer chats yet. Open the inbox to start a conversation.', 'មិនទាន់មានការជជែកអតិថិជនថ្មីៗនៅឡើយទេ។ បើកប្រអប់សារដើម្បីចាប់ផ្តើមការសន្ទនា។') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="lm-side-stack">
            <div class="lm-dashboard-panel">
                <div class="lm-dashboard-panel__header">
                    <div>
                        <h3 class="lm-dashboard-panel__title">{{ $lmText('Overdue Accounts', 'គណនីហួសកំណត់') }}</h3>
                        <p class="lm-dashboard-panel__hint">{{ $lmText('Need immediate follow-up today.', 'ត្រូវការតាមដានជាបន្ទាន់ថ្ងៃនេះ។') }}</p>
                    </div>
                    <div class="lm-dashboard-panel__actions">
                        <span class="lm-dashboard-panel__badge lm-dashboard-panel__badge--danger" id="loanOverdueCountBadge"><i class="fa fa-exclamation-triangle"></i> {{ $dashboardOverdue ?? count($overdueCustomers ?? []) }} {{ $lmText('Overdue', 'ហួសកំណត់') }}</span>
                        <a href="{{ route('loan-management.collection.page', ['page' => 'overdue-accounts']) }}" class="btn btn-xs btn-default" title="{{ $lmText('View all overdue accounts in Collection', 'មើលគណនីហួសកំណត់ទាំងអស់ក្នុងការប្រមូលប្រាក់') }}">
                            <i class="fa fa-external-link"></i> {{ $lmText('View All', 'មើលទាំងអស់') }}
                        </a>
                    </div>
                </div>
                <div class="lm-dashboard-panel__body">
                    <div class="lm-overdue-search">
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control" id="loanOverdueCustomersSearch" placeholder="{{ $lmText('Search overdue customer, loan #, phone...', 'ស្វែងរកអតិថិជនហួសកំណត់ លេខរំលស់ លេខទូរស័ព្ទ...') }}">
                        </div>
                    </div>
                    <div class="lm-table-wrap lm-overdue-customers-scroll">
                    <table class="table table-condensed lm-dashboard-table lm-mini-table" id="loanOverdueCustomersTable">
                        <thead>
                            <tr>
                                <th>{{ $lmText('Customer', 'អតិថិជន') }}</th>
                                <th>{{ $lmText('Pay Date', 'ថ្ងៃត្រូវបង់') }}</th>
                                <th>{{ $lmText('Days', 'ចំនួនថ្ងៃ') }}</th>
                                <th class="text-right">{{ $lmText('Paid', 'បានបង់') }}</th>
                                <th class="text-right">{{ $lmText('Due', 'ត្រូវបង់') }}</th>
                                <th class="text-right">{{ $lmText('Payoff', 'បង់ផ្ដាច់') }}</th>
                                <th class="text-center">{{ $lmText('Pay', 'បង់ប្រាក់') }}</th>
                            </tr>
                        </thead>
                        <tbody data-loan-table="overdue_customers">
                        @forelse(($overdueCustomers ?? []) as $row)
                            @php
                                $overdueName = trim((string) ($row['customer'] ?? '-'));
                                $overdueInitial = mb_substr($overdueName !== '' && $overdueName !== '-' ? $overdueName : 'C', 0, 1);
                                $loanId = (int) ($row['id'] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <div class="lm-customer-profile">
                                        <span class="lm-customer-profile__avatar">
                                            @if(!empty($row['customer_photo_url']))
                                                <img src="{{ $row['customer_photo_url'] }}" alt="">
                                            @else
                                                {{ $overdueInitial }}
                                            @endif
                                        </span>
                                        <span class="lm-customer-profile__info">
                                            <a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="{{ $lmText('Installment Detail', 'ព័ត៌មានលម្អិតរំលស់') }}" data-url="{{ url('loan-management/loans/'.$loanId.'/view?_lm_modal=1') }}">{{ $overdueName }}</a>
                                            <span class="lm-row-subtitle">{{ $row['loan_number'] ?? '' }}{{ !empty($row['phone']) ? ' · '.$row['phone'] : '' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $row['date_to_pay'] ?? '-' }}</td>
                                <td>{{ (int)($row['overdue_days'] ?? 0) }} {{ $lmText('day(s)', 'ថ្ងៃ') }}</td>
                                <td class="text-right">{{ number_format((float)($row['total_paid'] ?? 0), 2) }}</td>
                                <td class="text-right">{{ number_format((float)($row['total_not_yet_paid'] ?? ($row['overdue_amount'] ?? 0)), 2) }}</td>
                                <td class="text-right">{{ number_format((float)($row['pay_off_now'] ?? 0), 2) }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-success btn-xs btn-modal" data-href="{{ url('loan-management/loans/'.($row['id'] ?? 0).'/payment/create?return_to='.rawurlencode(route('loan-management.dashboard'))) }}" data-container=".view_modal">
                                        <i class="fa fa-money"></i> {{ $lmText('Pay', 'បង់ប្រាក់') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">{{ $lmText('No overdue customers.', 'មិនមានអតិថិជនហួសកំណត់។') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div class="lm-overdue-mobile-list" id="loanOverdueCustomersMobile">
                        @forelse(($overdueCustomers ?? []) as $row)
                            @php
                                $overdueName = trim((string) ($row['customer'] ?? '-'));
                                $overdueInitial = mb_substr($overdueName !== '' && $overdueName !== '-' ? $overdueName : 'C', 0, 1);
                                $overdueDue = (float)($row['total_not_yet_paid'] ?? ($row['overdue_amount'] ?? 0));
                                $overduePayoff = (float)($row['pay_off_now'] ?? 0);
                            @endphp
                            <article class="lm-overdue-mobile-card js-dashboard-card-detail" role="button" tabindex="0" data-title="{{ $lmText('Installment Detail', 'ព័ត៌មានលម្អិតរំលស់') }}" data-url="{{ url('loan-management/loans/'.($row['id'] ?? 0).'/view?_lm_modal=1') }}">
                                <div class="lm-overdue-mobile-card__header">
                                    <div class="lm-customer-profile">
                                        <span class="lm-customer-profile__avatar">
                                            @if(!empty($row['customer_photo_url']))
                                                <img src="{{ $row['customer_photo_url'] }}" alt="">
                                            @else
                                                {{ $overdueInitial }}
                                            @endif
                                        </span>
                                        <span class="lm-customer-profile__info">
                                            <span class="lm-row-title">{{ $overdueName }}</span>
                                            <span class="lm-row-subtitle">{{ $row['loan_number'] ?? '' }}{{ !empty($row['phone']) ? ' · '.$row['phone'] : '' }}</span>
                                        </span>
                                    </div>
                                    <div class="lm-overdue-mobile-main">
                                        <small>{{ $lmText('Amount Due', 'ចំនួនត្រូវបង់') }}</small>
                                        <strong>{{ number_format($overdueDue, 2) }}</strong>
                                    </div>
                                </div>
                                <span class="lm-overdue-mobile-badge">{{ (int)($row['overdue_days'] ?? 0) }}{{ $lmText('d overdue', 'ថ្ងៃហួសកំណត់') }}</span>
                                <div class="lm-overdue-mobile-grid">
                                    <div><small>{{ $lmText('Pay Date', 'ថ្ងៃត្រូវបង់') }}</small><span>{{ $row['date_to_pay'] ?? '-' }}</span></div>
                                    <div><small>{{ $lmText('Paid', 'បានបង់') }}</small><span>{{ number_format((float)($row['total_paid'] ?? 0), 2) }}</span></div>
                                    <div><small>{{ $lmText('Payoff', 'បង់ផ្ដាច់') }}</small><span>{{ number_format($overduePayoff, 2) }}</span></div>
                                    <div><small>{{ $lmText('Status', 'ស្ថានភាព') }}</small><span>{{ $lmText('Overdue', 'ហួសកំណត់') }}</span></div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm btn-block btn-modal" data-href="{{ url('loan-management/loans/'.($row['id'] ?? 0).'/payment/create?return_to='.rawurlencode(route('loan-management.dashboard'))) }}" data-container=".view_modal">
                                    <i class="fa fa-money"></i> {{ $lmText('Collect Payment', 'ប្រមូលប្រាក់បង់') }}
                                </button>
                            </article>
                        @empty
                            <div class="lm-mobile-loan-empty">{{ $lmText('No overdue customers.', 'មិនមានអតិថិជនហួសកំណត់។') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="lm-dashboard-panel">
                <div class="lm-dashboard-panel__header">
                    <div>
                        <h3 class="lm-dashboard-panel__title">{{ $lmText('Installment Status Overview', 'ទិដ្ឋភាពស្ថានភាពរំលស់') }}</h3>
                        <p class="lm-dashboard-panel__hint">{{ $lmText('Installment status distribution.', 'ការបែងចែកស្ថានភាពរំលស់។') }}</p>
                    </div>
                </div>
                <div class="lm-dashboard-panel__body">
                    <div class="lm-chart-shell">
                        <div class="lm-chart-copy">
                            <strong>{{ $lmText('Status Snapshot', 'ទិដ្ឋភាពស្ថានភាពទូទៅ') }}</strong>
                            <small id="loanStatusChartText" data-loan-chart="loan_status">{{ $lmText('Status labels:', 'ស្លាកស្ថានភាព៖') }} {{ implode(', ', $loanStatusChart['labels'] ?? []) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lm-dashboard-grid">
        <div class="lm-dashboard-panel">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">{{ $lmText('Visit Schedule', 'កាលវិភាគចុះជួប') }}</h3>
                    <p class="lm-dashboard-panel__hint">{{ $lmText('Pending fieldwork assignments.', 'កិច្ចការចុះផ្ទាល់កំពុងរង់ចាំ។') }}</p>
                </div>
            </div>
            <div class="lm-dashboard-panel__body lm-table-wrap">
                <table class="table table-bordered table-condensed lm-dashboard-table" id="loanVisitScheduleTable">
                    <thead><tr><th>{{ $lmText('Customer', 'អតិថិជន') }}</th><th>{{ $lmText('Date', 'កាលបរិច្ឆេទ') }}</th><th>{{ $lmText('Status', 'ស្ថានភាព') }}</th><th>{{ $lmText('Staff', 'បុគ្គលិក') }}</th></tr></thead>
                    <tbody data-loan-table="follow_up_customers">
                    @forelse(($visitSchedule ?? []) as $row)
                        <tr>
                            <td><span class="lm-row-title">{{ $row['customer'] ?? '-' }}</span></td>
                            <td>{{ $row['follow_up_date'] ?? '-' }}</td>
                            <td>{{ $row['status'] ?? '-' }}</td>
                            <td>{{ $row['assigned_staff'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">{{ $lmText('No pending visits.', 'មិនមានដំណើរចុះជួបកំពុងរង់ចាំទេ។') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="lm-dashboard-mobile-list" id="loanVisitScheduleMobile">
                    @forelse(($visitSchedule ?? []) as $row)
                        <article class="lm-dashboard-mobile-card lm-dashboard-mobile-card--visit">
                            <div class="lm-dashboard-mobile-card__header">
                                <div>
                                    <span class="lm-dashboard-mobile-card__title">{{ $row['customer'] ?? '-' }}</span>
                                    <span class="lm-dashboard-mobile-card__subtitle">{{ $row['assigned_staff'] ?? '-' }}</span>
                                </div>
                                <span class="lm-dashboard-mobile-card__status">{{ $row['status'] ?? '-' }}</span>
                            </div>
                            <div class="lm-dashboard-mobile-card__grid">
                                <div><small>{{ $lmText('Date', 'កាលបរិច្ឆេទ') }}</small><span>{{ $row['follow_up_date'] ?? '-' }}</span></div>
                                <div><small>{{ $lmText('Staff', 'បុគ្គលិក') }}</small><span>{{ $row['assigned_staff'] ?? '-' }}</span></div>
                            </div>
                        </article>
                    @empty
                        <div class="lm-mobile-loan-empty">{{ $lmText('No pending visits.', 'មិនមានដំណើរចុះជួបកំពុងរង់ចាំទេ។') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="lm-dashboard-panel">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">{{ $lmText('Collector Performance', 'ប្រសិទ្ធភាពអ្នកប្រមូលប្រាក់') }}</h3>
                    <p class="lm-dashboard-panel__hint">{{ $lmText('Output, loans, and visits by collector.', 'លទ្ធផល រំលស់ និងការចុះជួបតាមអ្នកប្រមូល។') }}</p>
                </div>
            </div>
            <div class="lm-dashboard-panel__body lm-table-wrap">
                <table class="table table-striped table-bordered lm-dashboard-table" id="loanCollectorPerformanceTable">
                    <thead><tr><th>{{ $lmText('Collector', 'អ្នកប្រមូល') }}</th><th>{{ $lmText('Assigned Installments', 'រំលស់ដែលបានចាត់តាំង') }}</th><th class="text-right">{{ $lmText('Collected', 'ប្រមូលបាន') }}</th><th>{{ $lmText('Visits', 'ចំនួនចុះជួប') }}</th></tr></thead>
                    <tbody data-loan-table="collector_performance">
                    @forelse(($collectorPerformance ?? []) as $row)
                        <tr>
                            <td><span class="lm-row-title">{{ $row['collector'] ?? '-' }}</span></td>
                            <td>{{ (int)($row['assigned_loans'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format((float)($row['collected_amount'] ?? 0), 2) }}</td>
                            <td>{{ (int)($row['visit_count'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">{{ $lmText('No collector performance data.', 'មិនមានទិន្នន័យប្រសិទ្ធភាពអ្នកប្រមូលទេ។') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="lm-dashboard-mobile-list" id="loanCollectorPerformanceMobile">
                    @forelse(($collectorPerformance ?? []) as $row)
                        <article class="lm-dashboard-mobile-card lm-dashboard-mobile-card--collector">
                            <div class="lm-dashboard-mobile-card__header">
                                <div>
                                    <span class="lm-dashboard-mobile-card__title">{{ $row['collector'] ?? '-' }}</span>
                                    <span class="lm-dashboard-mobile-card__subtitle">{{ (int)($row['assigned_loans'] ?? 0) }} {{ $lmText('assigned loans', 'រំលស់ចាត់តាំង') }}</span>
                                </div>
                                <span class="lm-dashboard-mobile-card__amount">{{ number_format((float)($row['collected_amount'] ?? 0), 2) }}</span>
                            </div>
                            <div class="lm-dashboard-mobile-card__grid">
                                <div><small>{{ $lmText('Assigned', 'ចាត់តាំង') }}</small><span>{{ (int)($row['assigned_loans'] ?? 0) }}</span></div>
                                <div><small>{{ $lmText('Visits', 'ចុះជួប') }}</small><span>{{ (int)($row['visit_count'] ?? 0) }}</span></div>
                            </div>
                        </article>
                    @empty
                        <div class="lm-mobile-loan-empty">{{ $lmText('No collector performance data.', 'មិនមានទិន្នន័យប្រសិទ្ធភាពអ្នកប្រមូលទេ។') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>

    <div class="lm-dashboard-pane" data-dashboard-pane="live">
        @php
            $initialLiveChat = collect($recentChats ?? [])->first();
        @endphp
        <section class="lm-dashboard-panel lm-dashboard-panel--feature">
            <div class="lm-dashboard-panel__header">
                <div>
                    <h3 class="lm-dashboard-panel__title">{{ $lmText('Live Chat', 'ការជជែកផ្ទាល់') }}</h3>
                    <p class="lm-dashboard-panel__hint">{{ $lmText('Conversations, unread queues, and support activity.', 'ការសន្ទនា ជួរមិនទាន់អាន និងសកម្មភាពគាំទ្រ។') }}</p>
                </div>
                <span class="lm-live-badge"><span class="lm-live-badge__dot"></span> {{ $lmText('Auto refresh 30s', 'ផ្ទុកឡើងវិញស្វ័យប្រវត្តិ 30វិ') }}</span>
            </div>
            <div class="lm-dashboard-panel__body">
                <div class="lm-live-chat-shell">
                    <aside class="lm-live-chat-inbox">
                        <div class="lm-live-chat-toolbar">
                            <h4>{{ $lmText('Chats', 'ការជជែក') }}</h4>
                            <input type="text" class="lm-live-chat-search" id="loanDashboardLiveChatSearch" placeholder="{{ $lmText('Search Messenger style inbox', 'ស្វែងរកប្រអប់សារ Messenger') }}">
                        </div>
                        <div class="lm-live-chat-list" id="loanDashboardLiveChatList">
                            <div class="lm-live-chat-empty">{{ $lmText('Loading live chats...', 'កំពុងផ្ទុកការជជែកផ្ទាល់...') }}</div>
                        </div>
                    </aside>

                    <main class="lm-live-chat-main">
                        <div class="lm-live-chat-mainbar">
                            <div>
                                <h4 class="lm-live-chat-main-title" id="loanDashboardLiveChatTitle">{{ $initialLiveChat['display_name'] ?? $lmText('Select a chat', 'ជ្រើសរើសការជជែក') }}</h4>
                                <p class="lm-live-chat-main-subtitle" id="loanDashboardLiveChatSubtitle">{{ $initialLiveChat['display_subtitle'] ?? $lmText('Open a customer conversation from the inbox list.', 'បើកការសន្ទនាអតិថិជនពីបញ្ជីប្រអប់សារ។') }}</p>
                            </div>
                            <div class="lm-live-chat-main-actions">
                            <a href="{{ route('loan-management.live-chat') }}" class="btn btn-default btn-sm">
                                <i class="fa fa-external-link"></i> {{ $lmText('Open Full Inbox', 'បើកប្រអប់សារពេញ') }}
                            </a>
                            @if(!empty($initialLiveChat['id']))
                                <a href="{{ route('loan-management.live-chat.detail', $initialLiveChat['id']) }}" class="btn btn-primary btn-sm" id="loanDashboardLiveChatOpenBtn">
                                    <i class="fa fa-comments"></i> {{ $lmText('Open Conversation', 'បើកការសន្ទនា') }}
                                </a>
                            @else
                                <a href="{{ route('loan-management.live-chat') }}" class="btn btn-primary btn-sm" id="loanDashboardLiveChatOpenBtn">
                                    <i class="fa fa-comments"></i> {{ $lmText('Open Conversation', 'បើកការសន្ទនា') }}
                                </a>
                            @endif
                            </div>
                        </div>
                        <iframe
                            id="loanDashboardLiveChatFrame"
                            class="lm-live-chat-frame"
                            src="about:blank"
                            data-src="{{ !empty($initialLiveChat['id']) ? route('loan-management.live-chat.detail', ['thread' => $initialLiveChat['id'], '_lm_embed' => 1]) : route('loan-management.live-chat', ['_lm_embed' => 1]) }}"
                            title="{{ $lmText('Installment live chat dashboard', 'ផ្ទាំងការជជែកផ្ទាល់រំលស់') }}"></iframe>
                    </main>

                    <aside class="lm-live-chat-side">
                        <div class="lm-live-chat-profile">
                            <div class="lm-live-chat-profile-avatar" id="loanDashboardLiveChatProfileAvatar">
                                @if(!empty($initialLiveChat['avatar_url']))
                                    <img src="{{ $initialLiveChat['avatar_url'] }}" alt="">
                                @else
                                    {{ strtoupper(substr((string) ($initialLiveChat['display_name'] ?? 'C'), 0, 1)) }}
                                @endif
                            </div>
                            <h4 class="lm-live-chat-profile-name" id="loanDashboardLiveChatProfileName">{{ $initialLiveChat['display_name'] ?? $lmText('Customer Chat', 'ជជែកជាមួយអតិថិជន') }}</h4>
                            <p class="lm-live-chat-profile-subtitle" id="loanDashboardLiveChatProfileSubtitle">{{ $initialLiveChat['display_subtitle'] ?? $lmText('Installment support inbox', 'ប្រអប់សារគាំទ្ររំលស់') }}</p>
                            <p class="lm-live-chat-profile-time" id="loanDashboardLiveChatProfileTime">
                                {{ !empty($initialLiveChat['last_message_at']) ? \Carbon\Carbon::parse($initialLiveChat['last_message_at'])->diffForHumans() : $lmText('Waiting for live activity', 'កំពុងរង់ចាំសកម្មភាពជាក់ស្តែង') }}
                            </p>
                        </div>

                        <div class="lm-live-chat-side-section">
                            <h5 class="lm-live-chat-side-title">{{ $lmText('Conversation Summary', 'សង្ខេបការសន្ទនា') }}</h5>
                            <div class="lm-live-chat-side-row"><span>{{ $lmText('Status', 'ស្ថានភាព') }}</span><span id="loanDashboardLiveChatStatus">{{ ucfirst((string) ($initialLiveChat['status'] ?? 'open')) }}</span></div>
                            <div class="lm-live-chat-side-row"><span>{{ $lmText('Priority', 'អាទិភាព') }}</span><span id="loanDashboardLiveChatPriority">{{ ucfirst((string) ($initialLiveChat['priority'] ?? 'normal')) }}</span></div>
                            <div class="lm-live-chat-side-row"><span>{{ $lmText('Assigned Team', 'ក្រុមទទួលបន្ទុក') }}</span><span id="loanDashboardLiveChatTeam">{{ $initialLiveChat['assigned_team'] ?? $lmText('Support', 'ផ្នែកគាំទ្រ') }}</span></div>
                            <div class="lm-live-chat-side-row"><span>{{ $lmText('Unread', 'មិនទាន់អាន') }}</span><span id="loanDashboardLiveChatUnread">{{ (int) ($initialLiveChat['unread_count'] ?? 0) }}</span></div>
                        </div>

                        <div class="lm-live-chat-side-section">
                            <h5 class="lm-live-chat-side-title">{{ $lmText('Last Message', 'សារចុងក្រោយ') }}</h5>
                            <div id="loanDashboardLiveChatLastMessage" style="color:#334155; font-size:13px; line-height:1.6;">
                                {{ $initialLiveChat['last_message'] ?? $lmText('No recent message yet.', 'មិនទាន់មានសារថ្មីៗនៅឡើយទេ។') }}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</div>

@section('loan_js')
@parent
<script>
    (function ($) {
        if (!window.jQuery) {
            return;
        }

        var liveUrl = "{{ route('loan-management.dashboard.data', [], false) }}";
        var quickSearchUrl = "{{ route('loan-management.dashboard.quick-search', [], false) }}";
        var refreshMs = 30000;
        var loading = false;
        var timer = null;
        var quickSearchTimer = null;
        var liveTabLoaded = false;
        var liveChatSearchTimer = null;
        var liveChatThreads = [];
        var activeLiveChatId = {{ (int) ($initialLiveChat['id'] ?? 0) }};
        var liveChatApiUrl = "{{ route('loan-management.chat-api.index', [], false) }}";
        var liveChatFrameBaseUrl = "{{ url('loan-management/live-chat') }}";
        var loanLanguage = "{{ $loanLanguage }}";
        var isKhmer = loanLanguage === 'km';

        var i18n = {
            pay: isKhmer ? 'បង់ប្រាក់' : 'Pay',
            print: isKhmer ? 'បោះពុម្ព' : 'Print',
            view: isKhmer ? 'មើល' : 'View',
            viewInstallment: isKhmer ? 'មើលរំលស់' : 'View Installment',
            edit: isKhmer ? 'កែសម្រួល' : 'Edit',
            addToPos: isKhmer ? 'បន្ថែមទៅ POS' : 'Add to POS',
            copy: isKhmer ? 'ចម្លង' : 'Copy',
            blacklist: isKhmer ? 'បញ្ជីខ្មៅ' : 'Blacklist',
            blacklistConfirm: isKhmer ? 'តើអ្នកពិតជាចង់បញ្ចូល {name} ក្នុងបញ្ជីខ្មៅមែនទេ?' : 'Are you sure you want to add {name} to the blacklist?',
            blacklistReason: isKhmer ? 'មូលហេតុបញ្ចូលក្នុងបញ្ជីខ្មៅ (ស្រេចចិត្ត)' : 'Reason for blacklisting (optional)',
            blacklistSuccess: isKhmer ? 'បានបញ្ចូលអតិថិជនក្នុងបញ្ជីខ្មៅដោយជោគជ័យ។' : 'Customer has been added to the blacklist.',
            blacklistError: isKhmer ? 'មិនអាចបញ្ចូលអតិថិជនក្នុងបញ្ជីខ្មៅបានទេ។' : 'Unable to blacklist customer.',
            collectPayment: isKhmer ? 'ប្រមូលប្រាក់បង់' : 'Collect Payment',
            customer: isKhmer ? 'អតិថិជន' : 'Customer',
            overdue: isKhmer ? 'ហួសកំណត់' : 'Overdue',
            active: isKhmer ? 'សកម្ម' : 'Active',
            pending: isKhmer ? 'រង់ចាំ' : 'Pending',
            amountDue: isKhmer ? 'ចំនួនត្រូវបង់' : 'Amount Due',
            payDate: isKhmer ? 'ថ្ងៃត្រូវបង់' : 'Pay Date',
            nextPayDate: isKhmer ? 'ថ្ងៃបង់បន្ទាប់' : 'Next Pay Date',
            paid: isKhmer ? 'បានបង់' : 'Paid',
            balance: isKhmer ? 'សមតុល្យ' : 'Balance',
            payoff: isKhmer ? 'បង់ផ្ដាច់' : 'Payoff',
            status: isKhmer ? 'ស្ថានភាព' : 'Status',
            assigned: isKhmer ? 'ចាត់តាំង' : 'Assigned',
            visits: isKhmer ? 'ចុះជួប' : 'Visits',
            date: isKhmer ? 'កាលបរិច្ឆេទ' : 'Date',
            staff: isKhmer ? 'បុគ្គលិក' : 'Staff',
            chat: isKhmer ? 'ជជែក' : 'Chat',
            telegramConnected: isKhmer ? 'បានភ្ជាប់ Telegram' : 'Telegram connected',
            telegramNotConnected: isKhmer ? 'មិនទាន់ភ្ជាប់ Telegram' : 'Telegram not connected',
            connectTelegram: isKhmer ? 'ភ្ជាប់ Telegram' : 'Connect Telegram',
            installmentDetail: isKhmer ? 'ព័ត៌មានលម្អិតរំលស់' : 'Installment Detail',
            noOverdueCustomers: isKhmer ? 'មិនមានអតិថិជនហួសកំណត់។' : 'No overdue customers.',
            noOverdueMatch: isKhmer ? 'មិនមានអតិថិជនហួសកំណត់ត្រូវនឹងការស្វែងរករបស់អ្នកទេ។' : 'No overdue customers match your search.',
            typeToSearch: isKhmer ? 'វាយបញ្ចូលដើម្បីស្វែងរកការប្រមូលប្រាក់។' : 'Type to search for payment collection.',
            noInstallmentsFound: isKhmer ? 'មិនមានរំលស់សម្រាប់ស្វែងរកនេះទេ។' : 'No installments found for this search.',
            noLoansFound: isKhmer ? 'មិនមានកម្ចីសម្រាប់ស្វែងរកនេះទេ។' : 'No loans found for this search.',
            searchFailed: isKhmer ? 'ការស្វែងរកបរាជ័យ។' : 'Search failed.',
            noPendingVisits: isKhmer ? 'មិនមានដំណើរចុះជួបកំពុងរង់ចាំទេ។' : 'No pending visits.',
            noCollectorData: isKhmer ? 'មិនមានទិន្នន័យប្រសិទ្ធភាពអ្នកប្រមូលទេ។' : 'No collector performance data.',
            noLiveChats: isKhmer ? 'មិនមានការជជែកផ្ទាល់ទេ។' : 'No live chats found.',
            loadingLiveChats: isKhmer ? 'កំពុងផ្ទុកការជជែកផ្ទាល់...' : 'Loading live chats...',
            unableLoadLiveChats: isKhmer ? 'មិនអាចផ្ទុកការជជែកផ្ទាល់នៅពេលនេះទេ។' : 'Unable to load live chats right now.',
            waitingLiveActivity: isKhmer ? 'កំពុងរង់ចាំសកម្មភាពជាក់ស្តែង' : 'Waiting for live activity',
            noRecentMessage: isKhmer ? 'មិនទាន់មានសារថ្មីៗនៅឡើយទេ។' : 'No recent message yet.',
            assignedLoansText: isKhmer ? 'រំលស់ចាត់តាំង' : 'assigned loans',
            daysSuffix: isKhmer ? 'ថ្ងៃ' : 'day(s)',
            dOverdueSuffix: isKhmer ? 'ថ្ងៃហួសកំណត់' : 'd overdue',
            statusLabelsPrefix: isKhmer ? 'ស្លាកស្ថានភាព៖ ' : 'Status labels: ',
            close: isKhmer ? 'បិទ' : 'Close',
            openLink: isKhmer ? 'បើកតំណ' : 'Open Link',
            shareTgLink: isKhmer ? 'ចែករំលែកតំណនេះជាមួយ ' : 'Share this link with ',
            tgLinkValid: isKhmer ? '។ មានសុពលភាពកំណត់ និងអាចប្រើបានតែម្តងប៉ុណ្ណោះ។' : '. Valid for a limited time and can only be used once.',
            expiresText: isKhmer ? 'ផុតកំណត់៖ ' : 'Expires: ',
            copiedInfo: isKhmer ? 'បានចម្លងព័ត៌មានរំលស់' : 'Copied loan information',
            unableCopyInfo: isKhmer ? 'មិនអាចចម្លងព័ត៌មានរំលស់បានទេ' : 'Unable to copy loan information',
            unableCreateTgLink: isKhmer ? 'មិនអាចបង្កើតតំណ Telegram បានទេ។' : 'Unable to create Telegram link.',
            unread: isKhmer ? 'មិនទាន់អាន' : 'unread',
            refreshScheduleConfirm: isKhmer ? 'តើអ្នកចង់ផ្ទុកកាលវិភាគបង់ប្រាក់ឡើងវិញពីទិន្នន័យរំលស់ និងការទូទាត់?' : 'Refresh this loan payment schedule from the loan data and imported payments?',
            refreshing: isKhmer ? 'កំពុងផ្ទុក...' : 'Refreshing',
            refreshSuccess: isKhmer ? 'បានផ្ទុកកាលវិភាគបង់ប្រាក់ឡើងវិញដោយជោគជ័យ។' : 'Payment schedule refreshed successfully.',
            refreshError: isKhmer ? 'មិនអាចផ្ទុកកាលវិភាគបង់ប្រាក់ឡើងវិញបានទេ។' : 'Unable to refresh payment schedule.',
            installmentCount: isKhmer ? 'ចំនួនរំលស់' : 'Installment Count',
            principal: isKhmer ? 'ប្រាក់ដើម' : 'Principal',
            collectedAmount: isKhmer ? 'ចំនួនប្រមូលបាន' : 'Collected Amount',
            installments: isKhmer ? 'រំលស់' : 'Installments',
            paymentTotal: isKhmer ? 'សរុបការទូទាត់' : 'Payment Total',
            noChartData: isKhmer ? 'មិនមានទិន្នន័យគំនូសតាងផ្ទាល់សម្រាប់ចន្លោះនេះទេ។' : 'No live chart data for this filter range.'
        };

        function money(value) {
            var number = parseFloat(value || 0);
            return Number.isFinite(number) ? number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00';
        }

        function intValue(value) {
            var number = parseInt(value || 0, 10);
            return Number.isFinite(number) ? String(number) : '0';
        }

        function esc(value) {
            if (value == null || value === '') return '-';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function shortDate(value) {
            if (!value) {
                return '-';
            }
            var clean = String(value).trim().split(/[T\s]/)[0];
            var parts = clean.split('-');
            if (parts.length === 3) {
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            return clean;
        }

        function payDateNotice(value) {
            if (!value) {
                return '';
            }
            var clean = String(value).trim().split(/[T\s]/)[0];
            var parts = clean.split('-');
            if (parts.length !== 3) {
                return '';
            }
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10) - 1;
            var d = parseInt(parts[2], 10);
            if (isNaN(y) || isNaN(m) || isNaN(d)) {
                return '';
            }

            var due = new Date(y, m, d, 0, 0, 0, 0);
            var now = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
            var diff = Math.round((today.getTime() - due.getTime()) / 86400000);

            if (diff > 0) {
                return isKhmer ? ('យឺត ' + diff + ' ថ្ងៃ') : ('Overdue ' + diff + 'd');
            }
            if (diff === 0) {
                return isKhmer ? 'ត្រូវបង់ថ្ងៃនេះ' : 'Due today';
            }

            var remaining = Math.abs(diff);
            return isKhmer ? ('នៅសល់ ' + remaining + ' ថ្ងៃ') : ('Remaining ' + remaining + 'd');
        }


        function formatLmExpiry(value) {
            if (!value) {
                return '';
            }
            var date = new Date(value);
            if (isNaN(date.getTime())) {
                return String(value);
            }
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function dashboardDate(value) {
            if (!value) {
                return '-';
            }

            if (typeof moment === 'function') {
                var date = moment(value);
                if (date.isValid()) {
                    return date.format(moment_date_format);
                }
            }

            return esc(value);
        }

        function copyDashboardText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }

            var deferred = $.Deferred();
            var textarea = document.createElement('textarea');
            textarea.value = text || '';
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                deferred.resolve();
            } catch (e) {
                deferred.reject(e);
            }

            document.body.removeChild(textarea);
            return deferred.promise();
        }

        function openDashboardIframeModal(title, url) {
            if (!url || !$('.view_modal').length) {
                return;
            }

            var html = '' +
                '<div class="modal-dialog modal-xl lm-dashboard-iframe-modal" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                            '<h4 class="modal-title">' + esc(title || i18n.installmentDetail) + '</h4>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<iframe src="' + esc(url) + '" title="' + esc(title || i18n.installmentDetail) + '"></iframe>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $('.view_modal')
                .html(html)
                .modal('show');
        }

        function updateCards(cards) {
            $('[data-loan-card]').each(function () {
                var key = $(this).data('loan-card');
                var value = cards && Object.prototype.hasOwnProperty.call(cards, key) ? cards[key] : 0;
                $(this).text($(this).data('format') === 'money' ? money(value) : intValue(value));
            });
            var unreadChats = cards && Object.prototype.hasOwnProperty.call(cards, 'unread_chats') ? intValue(cards.unread_chats) : null;
            if (unreadChats !== null) {
                var $badge = $('#loanUnreadChatBadge');
                if ($badge.length) {
                    $badge.html('<i class="fa fa-comments"></i> ' + unreadChats + ' ' + i18n.unread);
                }
                var $queue = $('#loanUnreadChatSummaryValue');
                if ($queue.length) {
                    $queue.text(unreadChats);
                }
            }
        }

        function renderRecentPayments(rows) {
            var html = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td>'+esc(row.paid_date)+'</td><td>'+esc(row.customer_name_snapshot)+'</td><td>'+esc(row.loan_number)+'</td><td>'+esc(row.payment_method)+'</td><td class="text-right">'+money(row.paid_amount)+'</td></tr>';
            });
            $('[data-loan-table="recent_payments"]').html(html || '<tr><td colspan="5" class="text-center">' + (isKhmer ? 'មិនមានការទូទាត់ថ្មីៗត្រូវបានរកឃើញទេ។' : 'No recent payments found.') + '</td></tr>');
        }

        function renderOverdueCustomers(rows, totalCount) {
            var html = '';
            var mobileHtml = '';
            var list = rows || [];
            list.forEach(function (row) {
                var payUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/create?return_to={{ rawurlencode(route('loan-management.dashboard')) }}";
                var detailUrl = "{{ url('loan-management/loans') }}/" + row.id + "/view?_lm_modal=1";
                var customerName = row.customer || '-';
                var customerInitial = customerName && customerName !== '-' ? String(customerName).charAt(0).toUpperCase() : 'C';
                var customerAvatar = row.customer_photo_url
                    ? '<span class="lm-customer-profile__avatar"><img src="' + esc(row.customer_photo_url) + '" alt=""></span>'
                    : '<span class="lm-customer-profile__avatar">' + esc(customerInitial) + '</span>';
                var customerSub = esc(row.loan_number || '') + (row.phone ? ' &middot; ' + esc(row.phone) : '');
                html += '<tr>'
                    + '<td><div class="lm-customer-profile">' + customerAvatar + '<span class="lm-customer-profile__info"><a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + detailUrl + '">'+esc(customerName)+'</a><span class="lm-row-subtitle">'+customerSub+'</span></span></div></td>'
                    + '<td>'+esc(row.date_to_pay || '-')+'</td>'
                    + '<td>'+intValue(row.overdue_days)+' '+i18n.daysSuffix+'</td>'
                    + '<td class="text-right">'+money(row.total_paid || 0)+'</td>'
                    + '<td class="text-right">'+money(row.total_not_yet_paid || row.overdue_amount || 0)+'</td>'
                    + '<td class="text-right">'+money(row.pay_off_now || 0)+'</td>'
                    + '<td class="text-center"><button type="button" class="btn btn-success btn-xs btn-modal" data-href="'+payUrl+'" data-container=".view_modal"><i class="fa fa-money"></i> '+i18n.pay+'</button></td>'
                    + '</tr>';
                mobileHtml += overdueMobileCardHtml(row, payUrl, customerAvatar, customerSub, customerName);
            });
            $('[data-loan-table="overdue_customers"]').html(html || '<tr><td colspan="7" class="text-center">' + i18n.noOverdueCustomers + '</td></tr>');
            $('#loanOverdueCustomersMobile').html(mobileHtml || '<div class="lm-mobile-loan-empty">' + i18n.noOverdueCustomers + '</div>');
            var badgeTotal = (totalCount !== undefined && totalCount !== null) ? intValue(totalCount) : list.length;
            $('#loanOverdueCountBadge').html('<i class="fa fa-exclamation-triangle"></i> ' + badgeTotal + ' ' + i18n.overdue);
            filterOverdueCustomers();
        }

        function overdueMobileCardHtml(row, payUrl, customerAvatar, customerSub, customerName) {
            var detailUrl = "{{ url('loan-management/loans') }}/" + row.id + "/view?_lm_modal=1";
            return '<article class="lm-overdue-mobile-card js-dashboard-card-detail" role="button" tabindex="0" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + detailUrl + '">'
                + '<div class="lm-overdue-mobile-card__header">'
                + '<div class="lm-customer-profile">' + customerAvatar + '<span class="lm-customer-profile__info"><a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + detailUrl + '">'+esc(customerName)+'</a><span class="lm-row-subtitle">'+customerSub+'</span></span></div>'
                + '<div class="lm-overdue-mobile-main"><small>' + i18n.amountDue + '</small><strong>' + money(row.total_not_yet_paid || row.overdue_amount || 0) + '</strong></div>'
                + '</div>'
                + '<span class="lm-overdue-mobile-badge">' + intValue(row.overdue_days) + i18n.dOverdueSuffix + '</span>'
                + '<div class="lm-overdue-mobile-grid">'
                + '<div><small>' + i18n.payDate + '</small><span>' + esc(row.date_to_pay || '-') + '</span></div>'
                + '<div><small>' + i18n.paid + '</small><span>' + money(row.total_paid || 0) + '</span></div>'
                + '<div><small>' + i18n.payoff + '</small><span>' + money(row.pay_off_now || 0) + '</span></div>'
                + '<div><small>' + i18n.status + '</small><span>' + i18n.overdue + '</span></div>'
                + '</div>'
                + '<button type="button" class="btn btn-success btn-sm btn-block btn-modal" data-href="'+payUrl+'" data-container=".view_modal"><i class="fa fa-money"></i> ' + i18n.collectPayment + '</button>'
                + '</article>';
        }

        function filterOverdueCustomers() {
            var query = String($('#loanOverdueCustomersSearch').val() || '').toLowerCase().trim();
            var visibleCount = 0;
            var $tbody = $('[data-loan-table="overdue_customers"]');
            var $cards = $('#loanOverdueCustomersMobile .lm-overdue-mobile-card');
            var visibleCardCount = 0;

            $tbody.find('tr.lm-overdue-no-results').remove();
            $tbody.find('tr').each(function () {
                var $row = $(this);
                if ($row.find('td').length === 1) {
                    $row.toggle(!query);
                    return;
                }

                var matches = !query || $row.text().toLowerCase().indexOf(query) !== -1;
                $row.toggle(matches);
                if (matches) {
                    visibleCount++;
                }
            });

            if (query && visibleCount === 0) {
                $tbody.append('<tr class="lm-overdue-no-results"><td colspan="7" class="text-center text-muted">' + i18n.noOverdueMatch + '</td></tr>');
            }

            $('#loanOverdueCustomersMobile .lm-overdue-mobile-no-results').remove();
            $cards.each(function () {
                var $card = $(this);
                var matches = !query || $card.text().toLowerCase().indexOf(query) !== -1;
                $card.toggle(matches);
                if (matches) {
                    visibleCardCount++;
                }
            });

            if (query && $cards.length && visibleCardCount === 0) {
                $('#loanOverdueCustomersMobile').append('<div class="lm-mobile-loan-empty lm-overdue-mobile-no-results">' + i18n.noOverdueMatch + '</div>');
            }
        }

        var quickSearchRows = [];

        function quickSearchUrls(row) {
            var detailUrl = "{{ url('loan-management/loans') }}/" + row.id + "/view?_lm_modal=1";
            var editUrl = "{{ url('loan-management/loans') }}/" + row.id + "/edit?_lm_modal=1";
            var printModalUrl = "{{ url('loan-management/loans') }}/" + row.id + "/print-modal";
            var payUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/create?return_to={{ rawurlencode(route('loan-management.dashboard')) }}";
              var collectionUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payments/collection-modal";
              var copyInfoUrl = "{{ url('loan-management/loans') }}/" + row.id + "/payment/copy-info";
              var convertToPosUrl = "{{ url('loan-management/loans') }}/" + row.id + "/convert-to-pos?modal=1";
              var blacklistUrl = "{{ url('loan-management/customers') }}/" + (row.customer_id || '') + "/blacklist";
              return {
                  detail: detailUrl,
                  edit: editUrl,
                  printModal: printModalUrl,
                  pay: payUrl,
                  collection: collectionUrl,
                  copyInfo: copyInfoUrl,
                  convertToPos: convertToPosUrl,
                  blacklist: blacklistUrl
              };
          }

        function quickSearchRowHtml(row) {
            var urls = quickSearchUrls(row);
            var telegramLinkUrl = row.customer_id ? "{{ url('loan-management/customers') }}/" + row.customer_id + "/telegram/link" : '';
            var telegramAction = '';
            var tgStatus = row.telegram_linked
                ? '<span class="lm-customer-hover__status linked"><i class="fa fa-check-circle"></i> ' + i18n.telegramConnected + '</span>'
                : '<span class="lm-customer-hover__status"><i class="fa fa-paper-plane"></i> ' + i18n.telegramNotConnected + '</span>';
            var hoverTelegram = row.customer_id
                ? '<div class="lm-customer-hover__panel">' +
                    tgStatus +
                    '<div class="lm-customer-hover__actions">' +
                        '<button type="button" class="primary js-dashboard-open-telegram" data-customer-id="' + esc(row.customer_id) + '" data-customer-name="' + esc(row.customer_name) + '" data-telegram-linked="' + (row.telegram_linked ? '1' : '0') + '" data-loan-id="' + esc(row.id) + '" data-loan-number="' + esc(row.loan_number) + '" data-balance="' + esc(row.balance_amount) + '"><i class="fa fa-telegram"></i> ' + i18n.chat + '</button>' +
                    '</div>' +
                '</div>'
                : '';
            if (row.telegram_linked) {
                telegramAction = '<li><button type="button" disabled class="text-muted"><i class="fa fa-check-circle"></i> ' + i18n.telegramConnected + '</button></li>';
            } else if (telegramLinkUrl) {
                telegramAction = '<li><button type="button" class="js-dashboard-telegram-link" data-url="' + telegramLinkUrl + '" data-customer="' + esc(row.customer_name) + '"><i class="fa fa-paper-plane"></i> ' + i18n.connectTelegram + '</button></li>';
            }
            var notice = payDateNotice(row.next_due_date);
            var dueLabel = row.next_due_date
                ? '<span class="lm-pay-date">' + esc(shortDate(row.next_due_date)) + '</span>' + (notice ? '<small class="lm-pay-date-note">' + esc(notice) + '</small>' : '')
                : '<span class="text-muted">-</span>';
            var normalizedStatus = row.status ? String(row.status).toLowerCase() : '';
            var isOverdue = normalizedStatus === 'overdue' || normalizedStatus === 'late';
            var isUpcoming = normalizedStatus === 'upcoming';
            var isDue = normalizedStatus === 'due' || normalizedStatus === 'due_today';
            var statusLabel = String(row.status || 'active').toUpperCase();
            if (isKhmer) {
                if (isOverdue) statusLabel = 'ហួសកំណត់';
                else if (isUpcoming) statusLabel = 'ជិតដល់ថ្ងៃបង់';
                else if (isDue) statusLabel = 'ដល់ថ្ងៃត្រូវបង់';
                else if (statusLabel === 'ACTIVE') statusLabel = 'សកម្ម';
                else if (statusLabel === 'PENDING') statusLabel = 'រង់ចាំ';
                else if (statusLabel === 'COMPLETED') statusLabel = 'បញ្ចប់';
            } else if (isUpcoming) {
                statusLabel = 'UPCOMING';
            } else if (isDue) {
                statusLabel = 'DUE TODAY';
            }
            var statusBadge = isOverdue
                ? '<span class="lm-pay-status lm-pay-status--overdue">' + esc(statusLabel) + '</span>'
                : (isUpcoming
                    ? '<span class="lm-pay-status lm-pay-status--upcoming">' + esc(statusLabel) + '</span>'
                    : (isDue
                        ? '<span class="lm-pay-status lm-pay-status--due">' + esc(statusLabel) + '</span>'
                        : (row.status && normalizedStatus !== 'active' ? '<span class="lm-pay-status">' + esc(statusLabel) + '</span>' : '')));
            var customerPhone = row.customer_phone && row.customer_phone !== '-' ? ' &middot; ' + esc(row.customer_phone) : '';
            var customerInitial = (row.customer_name && row.customer_name !== '-' ? String(row.customer_name).charAt(0).toUpperCase() : 'C');
            var customerAvatar = row.customer_photo_url
                ? '<span class="lm-customer-profile__avatar"><img src="' + esc(row.customer_photo_url) + '" alt=""></span>'
                : '<span class="lm-customer-profile__avatar">' + esc(customerInitial) + '</span>';

            return '<tr class="lm-pay-row" data-loan-id="' + esc(row.id) + '">'
                + '<td class="lm-col-customer">'
                + '<div class="lm-customer-profile">'
                + customerAvatar
                + '<div class="lm-customer-profile__info">'
                    + '<div class="lm-customer-cell">'
                    + '<div class="lm-customer-hover">'
                    + '<a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + urls.detail + '">' + esc(row.customer_name) + '</a>'
                    + hoverTelegram
                    + '</div>'
                    + '<span class="lm-row-subtitle">ID ' + esc(row.id) + customerPhone + '</span>'
                    + '</div>'
                + '</div>'
                + '</div>'
                + '</td>'
                + '<td class="lm-col-code"><span class="lm-pay-code">' + esc(row.loan_number || '-') + '</span></td>'
                + '<td class="lm-pay-due lm-col-date">' + dueLabel + '</td>'
                + '<td class="text-right lm-pay-paid lm-col-money">' + money(row.paid_amount || 0) + '</td>'
                + '<td class="text-right lm-pay-balance lm-col-money">' + money(row.balance_amount) + '</td>'
                + '<td class="text-center lm-col-status">' + (statusBadge || '<span class="lm-pay-status lm-pay-status--ok">' + (isKhmer ? 'សកម្ម' : 'ACTIVE') + '</span>') + '</td>'
                + '<td class="text-center lm-pay-action lm-col-action">'
                + '<button type="button" class="btn btn-success btn-xs lm-pay-btn btn-modal" data-href="' + urls.pay + '" data-container=".view_modal" title="' + i18n.collectPayment + ' ' + esc(row.customer_name) + '"><i class="fa fa-money"></i> <span>' + i18n.pay + '</span></button>'
                + '<button type="button" class="btn btn-default btn-xs lm-print-btn btn-modal" data-href="' + urls.printModal + '" data-container=".view_modal" title="' + i18n.print + ' ' + esc(row.customer_name) + '"><i class="fa fa-print"></i> <span>' + i18n.print + '</span></button>'
                + '<div class="lm-pay-more dropdown">'
                + '<button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" title="More actions"><i class="fa fa-ellipsis-h"></i></button>'
                + '<ul class="dropdown-menu dropdown-menu-right lm-action-menu__list">'
                + '<li><button type="button" class="js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + urls.detail + '"><i class="fa fa-eye"></i> ' + i18n.viewInstallment + '</button></li>'
                + '<li class="divider"></li>'
                + '<li><button type="button" class="btn-modal" data-href="' + urls.edit + '" data-container=".view_modal" title="' + esc(i18n.edit) + ' ' + esc(row.customer_name) + '"><i class="fa fa-edit"></i> ' + i18n.edit + '</button></li>'
                + '<li><button type="button" class="btn-modal" data-href="' + urls.convertToPos + '" data-container=".view_modal" title="' + esc(i18n.addToPos) + ' ' + esc(row.customer_name) + '"><i class="fa fa-exchange"></i> ' + i18n.addToPos + '</button></li>'
                + '<li><button type="button" class="js-copy-loan-payment-info" data-url="' + urls.copyInfo + '" title="' + esc(i18n.copy) + ' ' + esc(row.customer_name) + '"><i class="fa fa-copy"></i> ' + i18n.copy + '</button></li>'
                + '<li><button type="button" class="js-blacklist-customer" data-url="' + urls.blacklist + '" data-customer="' + esc(row.customer_name) + '" title="' + esc(i18n.blacklist) + ' ' + esc(row.customer_name) + '" style="color:#dd4b39;"><i class="fa fa-ban"></i> ' + i18n.blacklist + '</button></li>'
                + telegramAction
                + '</ul>'
                + '</div>'
                + '</td>'
                + '</tr>';
        }

        function quickSearchMobileCardHtml(row) {
            var urls = quickSearchUrls(row);
            var notice = payDateNotice(row.next_due_date);
            var dueLabel = row.next_due_date
                ? '<span class="lm-pay-date">' + esc(shortDate(row.next_due_date)) + '</span>' + (notice ? '<small class="lm-pay-date-note">' + esc(notice) + '</small>' : '')
                : '-';
            var loanMeta = esc(row.loan_number || '-') + (row.customer_phone && row.customer_phone !== '-' ? ' &middot; ' + esc(row.customer_phone) : '');
            var customerInitial = (row.customer_name && row.customer_name !== '-' ? String(row.customer_name).charAt(0).toUpperCase() : 'C');
            var customerAvatar = row.customer_photo_url
                ? '<span class="lm-customer-profile__avatar"><img src="' + esc(row.customer_photo_url) + '" alt=""></span>'
                : '<span class="lm-customer-profile__avatar">' + esc(customerInitial) + '</span>';
            var normalizedStatus = row.status ? String(row.status).toLowerCase() : '';
            var isOverdue = normalizedStatus === 'overdue' || normalizedStatus === 'late';
            var isUpcoming = normalizedStatus === 'upcoming';
            var isDue = normalizedStatus === 'due' || normalizedStatus === 'due_today';
            var statusClass = isOverdue ? ' lm-pay-status--overdue' : (isUpcoming ? ' lm-pay-status--upcoming' : (isDue ? ' lm-pay-status--due' : ''));
            var statusLabel = String(row.status || '').toUpperCase();
            if (isKhmer) {
                if (isOverdue) statusLabel = 'ហួសកំណត់';
                else if (isUpcoming) statusLabel = 'ជិតដល់ថ្ងៃបង់';
                else if (isDue) statusLabel = 'ដល់ថ្ងៃត្រូវបង់';
                else if (statusLabel === 'ACTIVE') statusLabel = 'សកម្ម';
                else if (statusLabel === 'PENDING') statusLabel = 'រង់ចាំ';
            } else if (isUpcoming) {
                statusLabel = 'UPCOMING';
            } else if (isDue) {
                statusLabel = 'DUE TODAY';
            }
            var statusBadge = row.status ? '<span class="lm-pay-status' + statusClass + '">' + esc(statusLabel) + '</span>' : '';

            return '<article class="lm-collect-payment-card js-dashboard-card-detail" role="button" tabindex="0" data-loan-id="' + esc(row.id) + '" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + urls.detail + '">'
                + '<div class="lm-collect-payment-card__header">'
                + '<div class="lm-customer-profile">' + customerAvatar
                + '<span class="lm-customer-profile__info">'
                + '<a href="#" class="lm-row-title lm-dashboard-frame-link js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + urls.detail + '">' + esc(row.customer_name || '-') + '</a>'
                + '<span class="lm-row-subtitle">' + loanMeta + '</span>'
                + '</span></div>'
                + statusBadge
                + '</div>'
                + '<div class="lm-collect-payment-card__grid">'
                + '<div><small>' + i18n.nextPayDate + '</small><strong>' + dueLabel + '</strong></div>'
                + '<div><small>' + i18n.paid + '</small><strong>' + money(row.paid_amount || 0) + '</strong></div>'
                + '<div><small>' + i18n.balance + '</small><strong>' + money(row.balance_amount) + '</strong></div>'
                + '</div>'
                + '<div class="lm-collect-payment-card__actions">'
                + '<button type="button" class="btn btn-success btn-sm btn-modal" data-href="' + urls.pay + '" data-container=".view_modal"><i class="fa fa-money"></i> ' + i18n.pay + '</button>'
                + '<button type="button" class="btn btn-default btn-sm btn-modal" data-href="' + urls.printModal + '" data-container=".view_modal"><i class="fa fa-print"></i> ' + i18n.print + '</button>'
                + '<button type="button" class="btn btn-default btn-sm js-loan-detail-modal" data-title="' + esc(i18n.installmentDetail) + '" data-url="' + urls.detail + '"><i class="fa fa-eye"></i> ' + i18n.view + '</button>'
                + '</div>'
                + '</article>';
        }

        function renderQuickSearch(rows) {
            var html = '';
            var mobileHtml = '';
            quickSearchRows = rows || [];
            (rows || []).forEach(function (row) {
                html += quickSearchRowHtml(row);
                mobileHtml += quickSearchMobileCardHtml(row);
            });
            $('[data-loan-table="dashboard_quick_search"]').html(html || '<tr><td colspan="7" class="text-center">' + i18n.noInstallmentsFound + '</td></tr>');
            $('#loanDashboardQuickSearchMobile').html(mobileHtml || '<div class="lm-mobile-loan-empty">' + i18n.noLoansFound + '</div>');
        }

        function refreshQuickSearchRow(loanId) {
            var locationId = $('#loanDashboardQuickLocationFilter').val() || '';
            return fetch(quickSearchUrl + '?loan_id=' + encodeURIComponent(loanId) + '&location_id=' + encodeURIComponent(locationId), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    var row = json && json.data && json.data.length ? json.data[0] : null;
                    var $oldRow = $('[data-loan-table="dashboard_quick_search"] tr[data-loan-id="' + loanId + '"]');
                    if (!row) {
                        $oldRow.remove();
                        return;
                    }

                    var $newRow = $(quickSearchRowHtml(row));
                    if ($oldRow.length) {
                        $oldRow.replaceWith($newRow);
                    } else {
                        $('[data-loan-table="dashboard_quick_search"]').prepend($newRow);
                    }
                    quickSearchRows = quickSearchRows.filter(function (item) {
                        return String(item.id) !== String(loanId);
                    });
                    quickSearchRows.unshift(row);
                    $('#loanDashboardQuickSearchMobile').html(quickSearchRows.map(quickSearchMobileCardHtml).join(''));
                    $newRow.addClass('success');
                    window.setTimeout(function () { $newRow.removeClass('success'); }, 900);
                })
                .catch(function () {});
        }

        $(document).on('click', '.lm-dashboard-refresh-schedule-btn', function (event) {
            event.preventDefault();

            var $button = $(this);
            var url = $button.data('url');
            if (!url) {
                return;
            }

            if (!window.confirm(i18n.refreshScheduleConfirm)) {
                return;
            }

            var originalHtml = $button.html();
            $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <span>' + i18n.refreshing + '</span>');

            $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sections_context: 'show'
                },
                success: function (res) {
                    if (res && res.success) {
                        if (window.toastr) {
                            toastr.success(res.message || i18n.refreshSuccess);
                        }
                        refreshQuickSearchRow($button.data('loan-id') || $button.closest('tr[data-loan-id]').data('loan-id'));
                    } else if (window.toastr) {
                        toastr.error((res && res.message) || i18n.refreshError);
                    }
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.refreshError;
                    if (window.toastr) {
                        toastr.error(message);
                    } else {
                        alert(message);
                    }
                },
                complete: function () {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        });

        function runQuickSearch() {
            var term = $.trim($('#loanDashboardQuickSearchInput').val() || '');
            var locationId = $('#loanDashboardQuickLocationFilter').val() || '';

            fetch(quickSearchUrl + '?q=' + encodeURIComponent(term) + '&location_id=' + encodeURIComponent(locationId), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (res) {
                    renderQuickSearch(res && res.data ? res.data : []);
                })
                .catch(function () {
                    $('[data-loan-table="dashboard_quick_search"]').html('<tr><td colspan="7" class="text-center text-danger">' + i18n.searchFailed + '</td></tr>');
                    $('#loanDashboardQuickSearchMobile').html('<div class="lm-mobile-loan-empty text-danger">' + i18n.searchFailed + '</div>');
                });
        }

        $(document).on('click', '.js-copy-loan-payment-info', function (event) {
            event.preventDefault();

            var $button = $(this);
            var url = $button.data('url');
            if (!url) {
                return;
            }

            $button.prop('disabled', true);
            fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.ok ? response.json() : Promise.reject();
                })
                .then(function (res) {
                    var text = res && res.data ? (res.data.text || '') : '';
                    return copyDashboardText(text);
                })
                .then(function () {
                    if (window.toastr) {
                        toastr.success(i18n.copiedInfo);
                    }
                })
                .catch(function () {
                    if (window.toastr) {
                        toastr.error(i18n.unableCopyInfo);
                    } else {
                        alert(i18n.unableCopyInfo);
                    }
                })
                .finally(function () {
                    $button.prop('disabled', false);
                });
        });

        $(document).on('click', '.js-dashboard-telegram-link', function (event) {
            event.preventDefault();

            var $button = $(this);
            var url = $button.data('url');
            var customer = $button.data('customer') || (isKhmer ? 'អតិថិជន' : 'customer');
            if (!url || !$('.view_modal').length) {
                return;
            }

            $button.prop('disabled', true);
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: '{}'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok) {
                            throw new Error(json.message || i18n.unableCreateTgLink);
                        }
                        return json;
                    });
                })
                .then(function (res) {
                    var link = res && res.link ? res.link : '';
                    var expiresText = res && res.expires_at ? formatLmExpiry(res.expires_at) : '';
                    var qrUrl = link ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(link) : '';

                    $('.view_modal').html(
                        '<div class="modal-dialog modal-sm" role="document">' +
                            '<div class="modal-content">' +
                                '<div class="modal-header">' +
                                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                                    '<h4 class="modal-title"><i class="fa fa-paper-plane"></i> ' + i18n.connectTelegram + '</h4>' +
                                '</div>' +
                                '<div class="modal-body text-center">' +
                                    '<p class="text-muted" style="margin-bottom:12px;">' + i18n.shareTgLink + esc(customer) + i18n.tgLinkValid + '</p>' +
                                    (qrUrl ? '<img src="' + qrUrl + '" alt="Telegram QR code" style="width:220px;height:220px;max-width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:#fff;margin-bottom:12px;">' : '') +
                                    '<input class="form-control text-center" readonly value="' + esc(link) + '" style="margin-bottom:8px;">' +
                                    (expiresText ? '<div class="text-muted small">' + i18n.expiresText + esc(expiresText) + '</div>' : '') +
                                '</div>' +
                                '<div class="modal-footer">' +
                                    '<button type="button" class="btn btn-default" data-dismiss="modal">' + i18n.close + '</button>' +
                                    '<a href="' + esc(link) + '" target="_blank" rel="noopener" class="btn btn-primary">' + i18n.openLink + '</a>' +
                                '</div>' +
                            '</div>' +
                        '</div>'
                    ).modal('show');
                })
                .catch(function (error) {
                    if (window.toastr) {
                        toastr.error(error.message || i18n.unableCreateTgLink);
                    } else {
                        alert(error.message || i18n.unableCreateTgLink);
                    }
                })
                .finally(function () {
                    $button.prop('disabled', false);
                });
        });

        $(document).on('click', '.js-blacklist-customer', function (event) {
            event.preventDefault();

            var $button = $(this);
            var url = $button.data('url');
            var customer = $button.data('customer') || (isKhmer ? 'អតិថិជន' : 'customer');
            if (!url || !/\/\d+\/blacklist$/.test(url)) {
                return;
            }

            var confirmText = i18n.blacklistConfirm.replace('{name}', String(customer));
            if (!window.confirm(confirmText)) {
                return;
            }

            var reason = '';
            var entered = window.prompt(i18n.blacklistReason, '');
            if (entered !== null) {
                reason = entered;
            }

            $button.prop('disabled', true);

            var fd = new FormData();
            fd.append('blacklist_status', '1');
            fd.append('blacklist_reason', reason);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: fd
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        if (!response.ok) {
                            var message = null;
                            try {
                                message = JSON.parse(text).message;
                            } catch (e) {}
                            throw new Error(message || i18n.blacklistError);
                        }
                        return true;
                    });
                })
                .then(function () {
                    if (window.toastr) {
                        toastr.success(i18n.blacklistSuccess);
                    } else {
                        alert(i18n.blacklistSuccess);
                    }
                })
                .catch(function (error) {
                    if (window.toastr) {
                        toastr.error(error.message || i18n.blacklistError);
                    } else {
                        alert(error.message || i18n.blacklistError);
                    }
                })
                .finally(function () {
                    $button.prop('disabled', false);
                });
        });

        function dashboardTelegramContext($button, action) {
            return {
                loan_id: $button.data('loan-id') || '',
                loan_number: $button.data('loan-number') || '',
                balance_amount: $button.data('balance') || '',
                auto_action: action || ''
            };
        }

        $(document).on('click', '.js-dashboard-open-telegram, .js-dashboard-telegram-invoice, .js-dashboard-telegram-pay', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $button = $(this);
            var customerId = $button.data('customer-id');
            if (!customerId || typeof window.loanManagementOpenTelegramCustomer !== 'function') {
                return;
            }

            var action = $button.hasClass('js-dashboard-telegram-invoice')
                ? 'invoice'
                : ($button.hasClass('js-dashboard-telegram-pay') ? 'pay' : '');

            window.loanManagementOpenTelegramCustomer(
                customerId,
                $button.data('customer-name') || (isKhmer ? 'អតិថិជន' : 'Customer'),
                String($button.data('telegram-linked')) === '1',
                dashboardTelegramContext($button, action)
            );
        });

        function renderFollowUps(rows) {
            var html = '';
            var mobileHtml = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td><span class="lm-row-title">'+esc(row.customer)+'</span></td><td>'+esc(row.follow_up_date)+'</td><td>'+esc(row.status)+'</td><td>'+esc(row.assigned_staff)+'</td></tr>';
                mobileHtml += '<article class="lm-dashboard-mobile-card lm-dashboard-mobile-card--visit">'
                    + '<div class="lm-dashboard-mobile-card__header">'
                    + '<div><span class="lm-dashboard-mobile-card__title">' + esc(row.customer || '-') + '</span><span class="lm-dashboard-mobile-card__subtitle">' + esc(row.assigned_staff || '-') + '</span></div>'
                    + '<span class="lm-dashboard-mobile-card__status">' + esc(row.status || '-') + '</span>'
                    + '</div>'
                    + '<div class="lm-dashboard-mobile-card__grid">'
                    + '<div><small>' + i18n.date + '</small><span>' + esc(row.follow_up_date || '-') + '</span></div>'
                    + '<div><small>' + i18n.staff + '</small><span>' + esc(row.assigned_staff || '-') + '</span></div>'
                    + '</div>'
                    + '</article>';
            });
            $('[data-loan-table="follow_up_customers"]').html(html || '<tr><td colspan="4" class="text-center">' + i18n.noPendingVisits + '</td></tr>');
            $('#loanVisitScheduleMobile').html(mobileHtml || '<div class="lm-mobile-loan-empty">' + i18n.noPendingVisits + '</div>');
        }

        function renderCollectorPerformance(rows) {
            var html = '';
            var mobileHtml = '';
            (rows || []).forEach(function (row) {
                html += '<tr><td><span class="lm-row-title">'+esc(row.collector)+'</span></td><td>'+intValue(row.assigned_loans)+'</td><td class="text-right">'+money(row.collected_amount)+'</td><td>'+intValue(row.visit_count)+'</td></tr>';
                mobileHtml += '<article class="lm-dashboard-mobile-card lm-dashboard-mobile-card--collector">'
                    + '<div class="lm-dashboard-mobile-card__header">'
                    + '<div><span class="lm-dashboard-mobile-card__title">' + esc(row.collector || '-') + '</span><span class="lm-dashboard-mobile-card__subtitle">' + intValue(row.assigned_loans) + ' ' + i18n.assignedLoansText + '</span></div>'
                    + '<span class="lm-dashboard-mobile-card__amount">' + money(row.collected_amount) + '</span>'
                    + '</div>'
                    + '<div class="lm-dashboard-mobile-card__grid">'
                    + '<div><small>' + i18n.assigned + '</small><span>' + intValue(row.assigned_loans) + '</span></div>'
                    + '<div><small>' + i18n.visits + '</small><span>' + intValue(row.visit_count) + '</span></div>'
                    + '</div>'
                    + '</article>';
            });
            $('[data-loan-table="collector_performance"]').html(html || '<tr><td colspan="4" class="text-center">' + i18n.noCollectorData + '</td></tr>');
            $('#loanCollectorPerformanceMobile').html(mobileHtml || '<div class="lm-mobile-loan-empty">' + i18n.noCollectorData + '</div>');
        }

        function updateChartText(chart) {
            if (!chart || !chart.labels) {
                return;
            }
            $('#loanStatusChartText').text(i18n.statusLabelsPrefix + chart.labels.join(', '));
        }

        function compactLabel(value) {
            var raw = String(value == null ? '' : value);
            return raw.length > 10 ? raw.slice(2) : raw;
        }

        function renderLiveBarChart(containerSelector, config) {
            var container = $(containerSelector);
            if (!container.length) {
                return;
            }

            var labels = (config && config.labels) ? config.labels : [];
            var series = (config && config.series) ? config.series : [];
            var legends = (config && config.legends) ? config.legends : [];
            var maxValue = 0;

            series.forEach(function (row) {
                (row && row.values ? row.values : []).forEach(function (value) {
                    maxValue = Math.max(maxValue, Number(value || 0));
                });
            });

            if (!labels.length || !series.length || maxValue <= 0) {
                container.html('<div class="lm-live-chart__empty">' + i18n.noChartData + '</div>');
                return;
            }

            var canvas = '<div class="lm-live-chart__canvas">';
            labels.forEach(function (label, index) {
                canvas += '<div class="lm-live-chart__bar-group">';
                canvas += '<div class="lm-live-chart__value">';
                series.forEach(function (row, rowIndex) {
                    var rawValue = Number((row.values || [])[index] || 0);
                    canvas += (rowIndex > 0 ? '<br>' : '') + esc(row.format === 'money' ? money(rawValue) : intValue(rawValue));
                });
                canvas += '</div>';
                canvas += '<div class="lm-live-chart__bar-stack">';
                series.forEach(function (row) {
                    var rawValue = Number((row.values || [])[index] || 0);
                    var percent = maxValue > 0 ? Math.max(4, (rawValue / maxValue) * 100) : 4;
                    canvas += '<span class="lm-live-chart__bar ' + esc(row.className || '') + '" style="height:' + percent + '%"></span>';
                });
                canvas += '</div>';
                canvas += '<div class="lm-live-chart__label">' + esc(compactLabel(label)) + '</div>';
                canvas += '</div>';
            });
            canvas += '</div>';

            var legendHtml = '';
            if (legends.length) {
                legendHtml += '<div class="lm-live-chart__legend">';
                legends.forEach(function (legend) {
                    legendHtml += '<span class="lm-live-chart__legend-item"><span class="lm-live-chart__legend-swatch ' + esc(legend.className || '') + '"></span>' + esc(legend.label || '') + '</span>';
                });
                legendHtml += '</div>';
            }

            container.html(canvas + legendHtml);
        }

        function renderLiveCharts(charts) {
            charts = charts || {};

            renderLiveBarChart('#loanLiveMonthlyLoanChart', {
                labels: charts.monthly_loan ? charts.monthly_loan.labels : [],
                series: [
                    { values: charts.monthly_loan ? charts.monthly_loan.count : [], format: 'int', className: '' },
                    { values: charts.monthly_loan ? charts.monthly_loan.principal : [], format: 'money', className: 'lm-live-chart__bar--accent' }
                ],
                legends: [
                    { label: i18n.installmentCount, className: '' },
                    { label: i18n.principal, className: 'lm-live-chart__legend-swatch--accent' }
                ]
            });

            renderLiveBarChart('#loanLiveDailyCollectionChart', {
                labels: charts.daily_collection ? charts.daily_collection.labels : [],
                series: [
                    { values: charts.daily_collection ? charts.daily_collection.amount : [], format: 'money', className: 'lm-live-chart__bar--accent' }
                ],
                legends: [
                    { label: i18n.collectedAmount, className: 'lm-live-chart__legend-swatch--accent' }
                ]
            });

            renderLiveBarChart('#loanLiveStatusChart', {
                labels: charts.loan_status ? charts.loan_status.labels : [],
                series: [
                    { values: charts.loan_status ? charts.loan_status.series : [], format: 'int', className: 'lm-live-chart__bar--warn' }
                ],
                legends: [
                    { label: i18n.installments, className: 'lm-live-chart__legend-swatch--warn' }
                ]
            });

            renderLiveBarChart('#loanLivePaymentMethodChart', {
                labels: charts.payment_method ? charts.payment_method.labels : [],
                series: [
                    { values: charts.payment_method ? charts.payment_method.amount : [], format: 'money', className: '' }
                ],
                legends: [
                    { label: i18n.paymentTotal, className: '' }
                ]
            });
        }

        function activateDashboardTab(tabKey) {
            $('[data-dashboard-tab]').removeClass('is-active').attr('aria-pressed', 'false');
            $('[data-dashboard-pane]').removeClass('is-active');
            $('[data-dashboard-tab="' + tabKey + '"]').addClass('is-active').attr('aria-pressed', 'true');
            $('[data-dashboard-pane="' + tabKey + '"]').addClass('is-active');

            if (tabKey === 'live') {
                var $frame = $('#loanDashboardLiveChatFrame');
                if ($frame.length && ($frame.attr('src') === 'about:blank' || !$frame.attr('src'))) {
                    $frame.attr('src', $frame.data('src') || (liveChatFrameBaseUrl + '?_lm_embed=1'));
                }
                if (!liveTabLoaded) {
                    liveTabLoaded = true;
                    loadLiveChatThreads();
                    refreshLoanDashboard();
                }
            }
        }

        function formatLiveChatTime(value) {
            if (!value) {
                return '';
            }

            var date = new Date(value);
            if (isNaN(date.getTime())) {
                return String(value);
            }

            return date.toLocaleString();
        }

        function setLiveChatProfile(thread) {
            thread = thread || {};
            var name = thread.display_name || (isKhmer ? 'ជជែកជាមួយអតិថិជន' : 'Customer Chat');
            $('#loanDashboardLiveChatTitle, #loanDashboardLiveChatProfileName').text(name);
            $('#loanDashboardLiveChatSubtitle, #loanDashboardLiveChatProfileSubtitle').text(thread.display_subtitle || (isKhmer ? 'ប្រអប់សារគាំទ្ររំលស់' : 'Installment support inbox'));
            $('#loanDashboardLiveChatProfileAvatar').html(thread.avatar_url ? '<img src="' + esc(thread.avatar_url) + '" alt="">' : esc((name.charAt(0) || 'C').toUpperCase()));
            $('#loanDashboardLiveChatProfileTime').text(thread.last_message_at ? formatLiveChatTime(thread.last_message_at) : i18n.waitingLiveActivity);
            $('#loanDashboardLiveChatStatus').text(thread.status ? String(thread.status).replace(/_/g, ' ') : (isKhmer ? 'បើក' : 'open'));
            $('#loanDashboardLiveChatPriority').text(thread.priority ? String(thread.priority).replace(/_/g, ' ') : (isKhmer ? 'ធម្មតា' : 'normal'));
            $('#loanDashboardLiveChatTeam').text(thread.assigned_team || (isKhmer ? 'ផ្នែកគាំទ្រ' : 'Support'));
            $('#loanDashboardLiveChatUnread').text(intValue(thread.unread_count || 0));
            $('#loanDashboardLiveChatLastMessage').text(thread.last_message || i18n.noRecentMessage);

            var openUrl = thread.id ? (liveChatFrameBaseUrl + '/' + encodeURIComponent(thread.id)) : liveChatFrameBaseUrl;
            var embedUrl = openUrl + (openUrl.indexOf('?') === -1 ? '?' : '&') + '_lm_embed=1';
            $('#loanDashboardLiveChatOpenBtn').attr('href', openUrl);
            var $frame = $('#loanDashboardLiveChatFrame');
            $frame.data('src', embedUrl);
            if ($('[data-dashboard-pane="live"]').hasClass('is-active')) {
                $frame.attr('src', embedUrl);
            }
        }

        function renderLiveChatThreads() {
            var list = $('#loanDashboardLiveChatList');
            if (!list.length) {
                return;
            }

            var term = $.trim($('#loanDashboardLiveChatSearch').val() || '').toLowerCase();
            var rows = liveChatThreads.filter(function (thread) {
                if (!term) {
                    return true;
                }

                var hay = [
                    thread.display_name,
                    thread.display_subtitle,
                    thread.last_message,
                    thread.assigned_team,
                    thread.priority
                ].join(' ').toLowerCase();

                return hay.indexOf(term) !== -1;
            });

            if (!rows.length) {
                list.html('<div class="lm-live-chat-empty">' + i18n.noLiveChats + '</div>');
                return;
            }

            var html = '';
            rows.forEach(function (thread) {
                var activeClass = String(activeLiveChatId || '') === String(thread.id || '') ? ' is-active' : '';
                var unread = Number(thread.unread_count || 0);
                var avatar = thread.avatar_url
                    ? '<img src="' + esc(thread.avatar_url) + '" alt="">'
                    : esc((thread.display_name || 'C').charAt(0).toUpperCase());
                html += '<button type="button" class="lm-live-chat-item' + activeClass + '" data-live-chat-id="' + esc(thread.id || '') + '">'
                    + '<span class="lm-live-chat-avatar">' + avatar + '</span>'
                    + '<span>'
                    + '<span class="lm-live-chat-name">' + esc(thread.display_name || (isKhmer ? 'ជជែកជាមួយអតិថិជន' : 'Customer Chat')) + '</span>'
                    + '<span class="lm-live-chat-preview">' + esc(thread.last_message || thread.display_subtitle || (isKhmer ? 'បើកការសន្ទនា' : 'Open conversation')) + '</span>'
                    + '</span>'
                    + '<span class="lm-live-chat-meta">'
                    + '<span class="lm-live-chat-time">' + esc(thread.last_message_time || '') + '</span>'
                    + (unread > 0 ? '<span class="lm-live-chat-badge">' + esc(intValue(unread)) + '</span>' : '')
                    + '</span>'
                    + '</button>';
            });

            list.html(html);
        }

        function loadLiveChatThreads() {
            fetch(liveChatApiUrl + '?view=all', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (res) {
                    liveChatThreads = res && res.data ? res.data : [];
                    renderLiveChatThreads();

                    if (!liveChatThreads.length) {
                        setLiveChatProfile({});
                        return;
                    }

                    var selected = liveChatThreads.find(function (thread) {
                        return String(thread.id || '') === String(activeLiveChatId || '');
                    }) || liveChatThreads[0];

                    activeLiveChatId = selected.id || 0;
                    setLiveChatProfile(selected);
                    renderLiveChatThreads();
                })
                .catch(function () {
                    $('#loanDashboardLiveChatList').html('<div class="lm-live-chat-empty">' + i18n.unableLoadLiveChats + '</div>');
                });
        }

        function refreshLoanDashboard() {
            if (loading || document.hidden) {
                return;
            }

            loading = true;
            fetch(liveUrl + window.location.search + (window.location.search ? '&' : '?') + 'realtime=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    var contentType = response.headers.get('content-type') || '';
                    if (!response.ok || contentType.indexOf('application/json') === -1) {
                        if (timer) {
                            window.clearInterval(timer);
                            timer = null;
                        }
                        return null;
                    }

                    return response.json();
                })
                .then(function (res) {
                    if (!res) {
                        return;
                    }

                    var data = res && res.data ? res.data : {};
                    var cards = data.quick_cards || data.cards || {};
                    updateCards(cards);
                    var overdueTotal = cards.overdue_accounts !== undefined ? cards.overdue_accounts : (cards.overdue_loans !== undefined ? cards.overdue_loans : null);
                    renderOverdueCustomers(data.tables ? data.tables.overdue_customers : [], overdueTotal);
                    renderFollowUps(data.tables ? data.tables.follow_up_customers : []);
                    renderCollectorPerformance(data.charts ? data.charts.collector_performance : []);
                    updateChartText(data.charts ? data.charts.loan_status : null);
                    renderLiveCharts(data.charts || {});
                    if ($('[data-dashboard-pane="live"]').hasClass('is-active')) {
                        loadLiveChatThreads();
                    }
                })
                .catch(function () {})
                .finally(function () {
                    loading = false;
                });
        }

        $(function () {
            if (!$('#loanManagementApp').length) {
                return;
            }

            if (window.loanDashboardRealtimeTimer) {
                window.clearInterval(window.loanDashboardRealtimeTimer);
            }

            $('#loanDashboardQuickSearchInput').on('input', function () {
                window.clearTimeout(quickSearchTimer);
                quickSearchTimer = window.setTimeout(runQuickSearch, 250);
            });
            $('#loanDashboardQuickLocationFilter').on('change', function () {
                runQuickSearch();
            });
            $('#loanDashboardLiveChatSearch').on('input', function () {
                window.clearTimeout(liveChatSearchTimer);
                liveChatSearchTimer = window.setTimeout(renderLiveChatThreads, 160);
            });
            $(document).on('click', '[data-dashboard-tab]', function () {
                activateDashboardTab($(this).data('dashboard-tab'));
            });
            $(document).on('input', '#loanOverdueCustomersSearch', filterOverdueCustomers);
            $(document).on('click', '[data-live-chat-id]', function () {
                var threadId = $(this).data('live-chat-id');
                var selected = liveChatThreads.find(function (thread) {
                    return String(thread.id || '') === String(threadId || '');
                });
                if (!selected) {
                    return;
                }

                activeLiveChatId = selected.id || 0;
                setLiveChatProfile(selected);
                renderLiveChatThreads();
            });
            runQuickSearch();
            renderLiveCharts({});
            $(document).on('click', '.js-loan-detail-modal', function (event) {
                event.preventDefault();
                openDashboardIframeModal($(this).data('title') || i18n.installmentDetail, $(this).data('url'));
            });
            $(document).on('click', '.js-dashboard-card-detail', function (event) {
                if ($(event.target).closest('a, button, input, select, textarea, .dropdown-menu').length) {
                    return;
                }
                openDashboardIframeModal($(this).data('title') || i18n.installmentDetail, $(this).data('url'));
            });
            $(document).on('keydown', '.js-dashboard-card-detail', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                if ($(event.target).closest('a, button, input, select, textarea, .dropdown-menu').length) {
                    return;
                }
                event.preventDefault();
                openDashboardIframeModal($(this).data('title') || i18n.installmentDetail, $(this).data('url'));
            });

            timer = window.setInterval(refreshLoanDashboard, refreshMs);
            window.loanDashboardRealtimeTimer = timer;
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refreshLoanDashboard();
                }
            });
        });
    })(jQuery);
</script>
@endsection
