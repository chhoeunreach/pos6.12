<?php

return [
    'name' => 'SmartStockInventory',
    'module_version' => '1.0.0',
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    'critical_pending_transfer_days' => 7,
    'severity_levels' => ['low', 'medium', 'high', 'critical'],
];
