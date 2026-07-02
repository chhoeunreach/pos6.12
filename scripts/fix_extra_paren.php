<?php
// Fix accessory_pos_url('...')), patterns (extra closing paren) introduced by the
// initial double-wrap cleanup.

$acc = 'Modules/Accessory/Public/v7/js/pos.js';
$svc = 'Modules/Service/Public/v7/js/pos.js';

function fixExtraParen($path, $helper) {
    $src = file_get_contents($path);
    $orig = $src;

    // Match patterns like `accessory_pos_url('...')),` on a single line (or `))` followed by anything).
    $pattern = '/' . preg_quote($helper, '/') . "\\(\\s*'([^']+)'\\s*\\)\\)\\)/";
    $src = preg_replace_callback($pattern, function ($m) use ($helper) {
        return $helper . "('" . $m[1] . "')";
    }, $src);

    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "Fixed extra paren in $path\n";
    } else {
        echo "No extra paren fix needed for $path\n";
    }
}

fixExtraParen($acc, 'accessory_pos_url');
fixExtraParen($svc, 'service_pos_url');
