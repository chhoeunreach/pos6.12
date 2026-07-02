<?php
$main = 'public/js/pos.js';
$acc = 'Modules/Accessory/Public/v7/js/pos.js';
$svc = 'Modules/Service/Public/v7/js/pos.js';

$mainLines = file($main);

// HR block in main pos.js: lines 1917..2401 (1-based) — the show_sell_list_staff click handler through the auto-refresh setInterval.
$hrStart = 1916; // 0-based
$hrEnd = 2400;   // 0-based (inclusive) - the last `}, 30000);` line for the auto-refresh
$hrCount = $hrEnd - $hrStart + 1;
$hrBlock = array_slice($mainLines, $hrStart, $hrCount);

file_put_contents('scripts/hr_block_main.txt', implode('', $hrBlock));
echo "Extracted HR block: {$hrCount} lines (0-based {$hrStart}-{$hrEnd})\n";

// Find a good insertion line in each module pos.js: just before the $(document).on('click', '#show_featured_products', function() { (line 1897 in main, may differ in modules).
function insertHrBlock($path, $prefix, $hrBlock) {
    $lines = file($path);
    $out = [];
    $inserted = false;
    foreach ($lines as $i => $line) {
        // Insert just BEFORE the `$(document).on('click', '#show_featured_products', function(){` line
        if (!$inserted && strpos($line, "show_featured_products'") !== false && strpos($line, "$(document).on('click'") !== false) {
            // We want to insert 2 lines before to maintain blank line spacing: prepend the HR block now.
            $out = array_merge($out, $hrBlock);
            $inserted = true;
        }
        $out[] = $line;
    }
    if (!$inserted) {
        fwrite(STDERR, "ERROR: show_featured_products click handler not found in $path\n");
        return;
    }
    file_put_contents($path, implode('', $out));
    echo "Inserted HR block into $path (" . count($hrBlock) . " lines)\n";
}

insertHrBlock($acc, 'accessory', $hrBlock);
insertHrBlock($svc, 'service', $hrBlock);
