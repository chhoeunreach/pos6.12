<?php

return [
    'name' => 'SmartStockInventory',
    'module_version' => '1.0.0',
    'reach_username' => 'Reach',
    'admin_roles' => ['Admin', 'Administrator', 'Super Admin', 'Reach Admin'],
    'enable_super_admin_override' => true,
    'staff_allowed_routes' => [
        'ssi.count.enterprise.mobile',
        'ssi.count.enterprise.line',
    ],
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    'critical_pending_transfer_days' => 7,
    'severity_levels' => ['low', 'medium', 'high', 'critical'],
];
