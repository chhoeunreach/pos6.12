<?php
// Stray extra closing paren issue: the original regex added `+` after the url string in
// concatenation, and our wrap introduced `))` where `)` was needed.
// Examples: `url: accessory_pos_url('/...')),` and `url: accessory_pos_url('/...') + location_id),`.
// We need to remove the extra `)` after the helper call's closing paren.

$acc = 'Modules/Accessory/Public/v7/js/pos.js';
$svc = 'Modules/Service/Public/v7/js/pos.js';

function fixStrayParen($path, $helper) {
    $src = file_get_contents($path);
    $orig = $src;

    // Case 1: ... accessory_pos_url('...')), ...
    $src = preg_replace_callback(
        '/' . preg_quote($helper, '/') . "\\(\\s*'([^']+)'\\s*\\)\\)\\s*([,\\)\\;])/",
        function ($m) use ($helper) {
            return $helper . "('" . $m[1] . "')" . $m[2];
        },
        $src
    );

    // Case 2: ... accessory_pos_url('...') + <expr>), ...  (where the extra `)` was added before the next `,` or `;`)
    $src = preg_replace_callback(
        '/' . preg_quote($helper, '/') . "\\(\\s*'([^']+)'\\s*\\)\\s*\\+\\s*([^\\)]+)\\)\\s*([,\\)\\;])/",
        function ($m) use ($helper) {
            return $helper . "('" . $m[1] . "') + " . $m[2] . $m[3];
        },
        $src
    );

    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "Fixed stray paren in $path\n";
    } else {
        echo "No stray paren fix in $path\n";
    }
}

fixStrayParen($acc, 'accessory_pos_url');
fixStrayParen($svc, 'service_pos_url');
