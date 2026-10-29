<?php

$view = 'loan_management.view';
$reports = 'loan_management.reports.view|loan_management.view';
$loans = 'loan_management.loans.view|loan_management.view';

return [
    'sections' => [
        ['key' => 'overview', 'label' => 'Overview', 'km' => 'ទិដ្ឋភាពទូទៅ', 'items' => [
            ['label' => 'Dashboard', 'km' => 'ផ្ទាំងគ្រប់គ្រង', 'icon' => 'fa fa-home', 'route' => 'loan-management.dashboard', 'active_routes' => ['loan-management.dashboard', 'loan-management.dashboard.index'], 'can' => 'loan_management.dashboard.view|loan_management.view'],
            ['label' => 'Admin Installment', 'km' => 'រដ្ឋបាលកម្ចីរំលស់', 'icon' => 'fa fa-table', 'route' => 'loan-management.admin-loan', 'active_routes' => ['loan-management.admin-loan*'], 'can' => 'loan_management.admin_loan.view|loan_management.view'],
            ['label' => 'Dashboard Reports', 'km' => 'របាយការណ៍ផ្ទាំងគ្រប់គ្រង', 'icon' => 'fa fa-line-chart', 'route' => 'loan-management.reports.dashboard', 'can' => 'loan_management.reports.view|loan_management.view'],
        ]],
        ['key' => 'installments', 'label' => 'Installments', 'km' => 'កម្ចីរំលស់', 'items' => [
            ['label' => 'All Installments', 'km' => 'បញ្ជីកម្ចីទាំងអស់', 'icon' => 'fa fa-file-text-o', 'route' => 'loan-management.loans', 'active_routes' => ['loan-management.loans', 'loan-management.loans.index', 'loan-management.loans.view', 'loan-management.loans.edit'], 'can' => 'loan_management.loans.view|loan_management.view'],
            ['label' => 'Quotations & Proposals', 'km' => 'សម្រង់តម្លៃ', 'icon' => 'fa fa-file-o', 'route' => 'loan-management.quotations.index', 'active_routes' => ['loan-management.quotations.*'], 'can' => $loans],
            ['label' => 'Repayment Schedule', 'km' => 'កាលវិភាគបង់ប្រាក់', 'icon' => 'fa fa-calendar', 'route' => 'loan-management.schedules.index', 'active_routes' => ['loan-management.schedules.*'], 'can' => 'loan_management.schedules.view|loan_management.view'],
            ['label' => 'Products', 'km' => 'ទំនិញ', 'icon' => 'fa fa-cubes', 'route' => 'loan-management.products.index', 'active_routes' => ['loan-management.products.*'], 'can' => 'loan_management.products.view|loan_management.view'],
        ]],
        ['key' => 'tools', 'label' => 'Installment Tools', 'km' => 'ឧបករណ៍កម្ចី', 'items' => [
            ['label' => 'New Installment', 'km' => 'កម្ចីថ្មី', 'icon' => 'fa fa-plus-circle', 'route' => 'loan-management.loans.create', 'can' => 'loan_management.loans.create|loan_management.create'],
            ['label' => 'Installment Calculator', 'km' => 'ម៉ាស៊ីនគណនាកម្ចី', 'icon' => 'fa fa-calculator', 'route' => 'loan-management.loans.calculator', 'can' => 'loan_management.loans.create|loan_management.create'],
            ['label' => 'Installment Calendar', 'km' => 'ប្រតិទិនបង់ប្រាក់', 'icon' => 'fa fa-calendar', 'route' => 'loan-management.schedules.calendar', 'can' => 'loan_management.schedules.view|loan_management.view'],
            ['label' => 'Closed Accounts', 'km' => 'គណនីបិទរួច', 'icon' => 'fa fa-check-circle-o', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'closed-accounts'], 'can' => 'loan_management.collection.view|loan_management.view'],
        ]],
        ['key' => 'customers', 'label' => 'Customers', 'km' => 'អតិថិជន', 'items' => [
            ['label' => 'Customers', 'km' => 'អតិថិជន', 'icon' => 'fa fa-users', 'route' => 'loan-management.customers', 'active_routes' => ['loan-management.customers*'], 'can' => 'loan_management.customers.view|loan_management.view'],
            ['label' => 'Guarantors', 'km' => 'អ្នកធានា', 'icon' => 'fa fa-shield', 'route' => 'loan-management.guarantors.index', 'can' => 'loan_management.guarantors.view|loan_management.view'],
            ['label' => 'Blacklist', 'km' => 'បញ្ជីខ្មៅ', 'icon' => 'fa fa-user-times', 'route' => 'loan-management.blacklist.index', 'active_routes' => ['loan-management.blacklist.*'], 'can' => 'loan_management.blacklist.view|loan_management.view'],
        ]],
        ['key' => 'collections', 'label' => 'Collections', 'km' => 'ការប្រមូលប្រាក់', 'items' => [
            ['label' => "Today's Collection", 'km' => 'ប្រមូលប្រាក់ថ្ងៃនេះ', 'icon' => 'fa fa-clock-o', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'active_pages' => ['due-today', 'today-collection', 'partial-payments'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Payments', 'km' => 'ការបង់ប្រាក់', 'icon' => 'fa fa-money', 'route' => 'loan-management.payments.index', 'active_routes' => ['loan-management.payments.*', 'loan-management.monthly-payments.index'], 'can' => 'loan_management.payments.view|loan_management.payment|loan_management.view'],
            ['label' => 'Overdue Accounts', 'km' => 'គណនីហួសកំណត់', 'icon' => 'fa fa-exclamation-triangle', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'overdue-accounts'], 'active_pages' => ['overdue-accounts', 'delinquent-accounts'], 'active_routes' => ['loan-management.collection.page', 'loan-management.overdue.index'], 'badge_key' => 'overdue', 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Collection Cases', 'km' => 'ករណីប្រមូលប្រាក់', 'icon' => 'fa fa-briefcase', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'promise-to-pay'], 'active_pages' => ['promise-to-pay', 'broken-promise', 'recovery-management', 'debt-collection'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Field Visits', 'km' => 'ចុះជួបអតិថិជន', 'icon' => 'fa fa-map-marker', 'route' => 'loan-management.collection-visits.index', 'active_routes' => ['loan-management.collection-visits.*'], 'badge_key' => 'pending_visits', 'can' => 'loan_management.collection_visits.view|loan_management.collection.view|loan_management.view'],
        ]],
        ['key' => 'risk', 'label' => 'Risk & Recovery', 'km' => 'ហានិភ័យ និងស្ដារបំណុល', 'items' => [
            ['label' => 'High-Risk Customers', 'km' => 'អតិថិជនហានិភ័យខ្ពស់', 'icon' => 'fa fa-exclamation-triangle', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'high-risk-customers'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Fraud Risk', 'km' => 'ហានិភ័យក្លែងបន្លំ', 'icon' => 'fa fa-shield', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'fraud-risk'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Legal Cases', 'km' => 'ករណីផ្លូវច្បាប់', 'icon' => 'fa fa-balance-scale', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'legal-cases'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Repossessions', 'km' => 'ការដកហូតទ្រព្យ', 'icon' => 'fa fa-briefcase', 'route' => 'loan-management.risk.page', 'params' => ['page' => 'repossessions'], 'can' => 'loan_management.collection.view|loan_management.view'],
        ]],
        ['key' => 'communication', 'label' => 'Communication & Tracking', 'km' => 'ទំនាក់ទំនង និងតាមដាន', 'items' => [
            ['label' => 'Telegram Chats', 'km' => 'ការជជែក', 'icon' => 'fa fa-comments', 'route' => 'loan-management.chat.index', 'active_routes' => ['loan-management.chat.*'], 'badge_key' => 'unread_chat', 'can' => 'loan_management.chat.view'],
            ['label' => 'Live Chats', 'km' => 'ជជែកផ្ទាល់', 'icon' => 'fa fa-comments-o', 'route' => 'loan-management.live-chat', 'active_routes' => ['loan-management.live-chat*'], 'can' => 'loan_management.chat.view'],
            ['label' => 'Voice Calls', 'km' => 'ការហៅទូរស័ព្ទ', 'icon' => 'fa fa-phone', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'voice-calls'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Notifications', 'km' => 'ការជូនដំណឹង', 'icon' => 'fa fa-bell-o', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'notifications'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'SMS & Telegram Logs', 'km' => 'កំណត់ហេតុ SMS និង Telegram', 'icon' => 'fa fa-history', 'route' => 'loan-management.communication.page', 'params' => ['page' => 'sms-telegram-logs'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Customer GPS Tracking', 'km' => 'តាមដានទីតាំងអតិថិជន', 'icon' => 'fa fa-map-marker', 'route' => 'loan-management.gps.index', 'active_routes' => ['loan-management.gps.*', 'loan-management.customer-tracking*'], 'can' => 'loan_management.gps.view|loan_management.customer_gps.manage'],
        ]],
        ['key' => 'reports', 'label' => 'Reports', 'km' => 'របាយការណ៍', 'items' => [
            ['label' => 'Installment Summary', 'km' => 'សង្ខេបកម្ចី', 'icon' => 'fa fa-bar-chart', 'route' => 'loan-management.reports.index', 'active_routes' => ['loan-management.reports.index'], 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Daily Summary', 'km' => 'សង្ខេបប្រចាំថ្ងៃ', 'icon' => 'fa fa-calendar', 'route' => 'loan-management.reports.daily-loan-summary', 'can' => $reports],
            ['label' => 'Monthly Summary', 'km' => 'សង្ខេបប្រចាំខែ', 'icon' => 'fa fa-calendar', 'route' => 'loan-management.reports.monthly-loan-summary', 'can' => $reports],
            ['label' => 'Yearly Summary', 'km' => 'សង្ខេបប្រចាំឆ្នាំ', 'icon' => 'fa fa-calendar', 'route' => 'loan-management.reports.yearly-loan-summary', 'can' => $reports],
            ['label' => 'Collection Performance', 'km' => 'លទ្ធផលប្រមូលប្រាក់', 'icon' => 'fa fa-line-chart', 'route' => 'loan-management.collection.reports', 'active_routes' => ['loan-management.collection.report*'], 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Payment Analysis', 'km' => 'វិភាគការបង់ប្រាក់', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.reports.payments', 'active_routes' => ['loan-management.reports.payments', 'loan-management.reports.payment-summary-by-type', 'loan-management.aba*'], 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Portfolio at Risk', 'km' => 'ហានិភ័យផលប័ត្រ', 'icon' => 'fa fa-pie-chart', 'route' => 'loan-management.reports.portfolio-at-risk', 'active_routes' => ['loan-management.reports.portfolio-at-risk', 'loan-management.reports.par'], 'can' => $reports],
            ['label' => 'CBC Export', 'km' => 'នាំចេញទិន្នន័យ CBC', 'icon' => 'fa fa-download', 'route' => 'loan-management.reports.cbc-export', 'can' => $reports],
        ]],
        ['key' => 'administration', 'label' => 'Administration', 'km' => 'រដ្ឋបាល', 'items' => [
            ['label' => 'Users', 'km' => 'អ្នកប្រើប្រាស់', 'icon' => 'fa fa-user-o', 'route' => 'loan-management.users.index', 'active_routes' => ['loan-management.users.*'], 'can' => 'user.view|user.create'],
            ['label' => 'Roles & Permissions', 'km' => 'តួនាទី និងសិទ្ធិ', 'icon' => 'fa fa-key', 'route' => 'loan-management.roles.index', 'active_routes' => ['loan-management.roles.*'], 'can' => 'roles.view|roles.create'],
            ['label' => 'Branches', 'km' => 'សាខា', 'icon' => 'fa fa-building-o', 'route' => 'loan-management.locations.index', 'active_routes' => ['loan-management.locations.*'], 'can' => 'loan_management.settings.view|loan_management.setting|loan_management.view'],
            ['label' => 'Import & Export', 'km' => 'នាំចូល និងនាំចេញ', 'icon' => 'fa fa-exchange', 'route' => 'loan-management.import.index', 'can' => 'loan_management.import.view|loan_management.export.view|loan_management.view'],
            ['label' => 'System Status', 'km' => 'ស្ថានភាពប្រព័ន្ធ', 'icon' => 'fa fa-heartbeat', 'route' => 'loan-management.system.status', 'active_routes' => ['loan-management.system.*'], 'can' => 'loan_management.setting'],
            ['label' => 'Payment Methods & Currencies', 'km' => 'វិធីទូទាត់ និងរូបិយប័ណ្ណ', 'icon' => 'fa fa-credit-card', 'route' => 'loan-management.settings.payment-methods', 'can' => 'loan_management.settings.view|loan_management.setting|loan_management.view'],
            ['label' => 'Customer Portal & CMS', 'km' => 'ទំព័រអតិថិជន និង CMS', 'icon' => 'fa fa-globe', 'route' => 'loan-management.settings.cms', 'can' => 'loan_management.settings.view|loan_management.setting|loan_management.view'],
            ['label' => 'Audit Logs', 'km' => 'កំណត់ហេតុសវនកម្ម', 'icon' => 'fa fa-history', 'route' => 'loan-management.activity-logs.index', 'can' => 'loan_management.setting|loan_management.view'],
            ['label' => 'Settings', 'km' => 'ការកំណត់', 'icon' => 'fa fa-cog', 'route' => 'loan-management.settings.business', 'active_routes' => ['loan-management.settings', 'loan-management.settings.business'], 'can' => 'loan_management.settings.view|loan_management.setting|loan_management.view'],
        ]],
    ],
    'workspaces' => [
        'summary' => [
            ['label' => 'Overview', 'km' => 'ទិដ្ឋភាពទូទៅ', 'route' => 'loan-management.reports.index', 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Daily', 'km' => 'ប្រចាំថ្ងៃ', 'route' => 'loan-management.reports.daily-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Monthly', 'km' => 'ប្រចាំខែ', 'route' => 'loan-management.reports.monthly-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
            ['label' => 'Yearly', 'km' => 'ប្រចាំឆ្នាំ', 'route' => 'loan-management.reports.yearly-loan-summary', 'can' => 'loan_management.reports.view|loan_management.view'],
        ],
        'daily-collection' => [
            ['label' => 'Due Today', 'km' => 'ត្រូវបង់ថ្ងៃនេះ', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'due-today'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Collected Today', 'km' => 'បានប្រមូលថ្ងៃនេះ', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'today-collection'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Partial Payments', 'km' => 'ការបង់ប្រាក់មិនពេញ', 'route' => 'loan-management.operations.page', 'params' => ['page' => 'partial-payments'], 'can' => 'loan_management.collection.view|loan_management.view'],
        ],
        'overdue' => [
            ['label' => 'Overdue Accounts', 'km' => 'គណនីហួសកំណត់', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'overdue-accounts'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Delinquent Accounts', 'km' => 'គណនីយឺតយ៉ាវ', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'delinquent-accounts'], 'can' => 'loan_management.collection.view|loan_management.view'],
        ],
        'cases' => [
            ['label' => 'Promise To Pay', 'km' => 'សន្យាបង់ប្រាក់', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'promise-to-pay'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Broken Promise', 'km' => 'ខកខានសន្យា', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'broken-promise'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Recovery', 'km' => 'ការស្ដារបំណុល', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'recovery-management'], 'can' => 'loan_management.collection.view|loan_management.view'],
            ['label' => 'Debt Collection', 'km' => 'ប្រមូលបំណុល', 'route' => 'loan-management.collection.page', 'params' => ['page' => 'debt-collection'], 'can' => 'loan_management.collection.view|loan_management.view'],
        ],
        'users' => [
            ['label' => 'Users', 'km' => 'អ្នកប្រើប្រាស់', 'route' => 'loan-management.users.index', 'can' => 'user.view|user.create'],
            ['label' => 'Roles', 'km' => 'តួនាទី', 'route' => 'loan-management.roles.index', 'can' => 'roles.view|roles.create'],
        ],
        'settings' => [
            ['label' => 'Business', 'km' => 'អាជីវកម្ម', 'route' => 'loan-management.settings.business', 'can' => 'loan_management.settings.view|loan_management.setting|loan_management.view'],
            ['label' => 'Payment Methods', 'km' => 'វិធីបង់ប្រាក់', 'route' => 'loan-management.settings.payment-methods', 'can' => $view],
            ['label' => 'Customer Portal', 'km' => 'ទំព័រអតិថិជន', 'route' => 'loan-management.settings.cms', 'can' => $view],
            ['label' => 'Social Media', 'km' => 'បណ្ដាញសង្គម', 'route' => 'loan-management.settings.social', 'can' => $view],
        ],
    ],
];
