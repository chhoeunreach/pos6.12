<?php
// Clean up double-wrapped accessory_pos_url(accessory_pos_url(...)) that the
// generic rewrite produced when both regexes matched the same line.

$acc = 'Modules/Accessory/Public/v7/js/pos.js';
$svc = 'Modules/Service/Public/v7/js/pos.js';

function cleanDoubleWrap($path, $helper) {
    $src = file_get_contents($path);
    $orig = $src;
    // Reduce accessory_pos_url(accessory_pos_url(...)) -> accessory_pos_url(...)
    $src = preg_replace(
        '/' . preg_quote($helper, '/') . '\(\s*' . preg_quote($helper, '/') . '\(/',
        $helper . '(',
        $src
    );
    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "Cleaned double-wraps in $path\n";
    }
}

cleanDoubleWrap($acc, 'accessory_pos_url');
cleanDoubleWrap($svc, 'service_pos_url');
