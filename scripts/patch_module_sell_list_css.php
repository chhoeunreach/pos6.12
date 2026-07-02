<?php
/**
 * Splice the HR Sell List CSS block from scripts/hr_sell_list_css_block.txt
 * into the module app.css files (source + public mirror) so the feature
 * styles render in Accessory and Service module POS screens.
 *
 * The block was lifted from public/css/app.css (lines 1562-1863) and
 * contains every .sell-list-* rule plus #sell_list_staff_box. It is
 * inserted between the #featured_products_box block and the
 * "/* Drawer panel */" section, matching the main app layout.
 *
 * Re-runnable; will be a no-op if the marker is already present.
 *
 * Usage: php scripts/patch_module_sell_list_css.php
 */

$root = realpath(__DIR__ . '/..');

$blockPath   = $root . '/scripts/hr_sell_list_css_block.txt';
$marker      = '/* Drawer panel */';
$beginMarker = '/* HR sell list staff box */';

if (! is_file($blockPath)) {
    fwrite(STDERR, "Block file not found: $blockPath\n");
    exit(1);
}
$block = file_get_contents($blockPath);
if ($block === false || trim($block) === '') {
    fwrite(STDERR, "Block file is empty\n");
    exit(1);
}

$sourceAccessoryCss = $root . '/Modules/Accessory/Public/v7/css/app.css';
$sourceServiceCss   = $root . '/Modules/Service/Public/v7/css/app.css';
$mirrorAccessoryCss = $root . '/public/modules/accessory/v7/css/app.css';
$mirrorServiceCss   = $root . '/public/modules/service/v7/css/app.css';

$targets = [
    'Accessory source' => $sourceAccessoryCss,
    'Accessory mirror' => $mirrorAccessoryCss,
    'Service source'   => $sourceServiceCss,
    'Service mirror'   => $mirrorServiceCss,
];

$seedFrom = [
    $mirrorAccessoryCss => $sourceAccessoryCss,
    $mirrorServiceCss   => $sourceServiceCss,
];

foreach ($targets as $label => $path) {
    if (! is_file($path)) {
        if (isset($seedFrom[$path]) && is_file($seedFrom[$path])) {
            $dir = dirname($path);
            if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
                fwrite(STDERR, "Failed to create dir: $dir\n");
                continue;
            }
            if (! copy($seedFrom[$path], $path)) {
                fwrite(STDERR, "Failed to seed $label from source\n");
                continue;
            }
            echo "Seeded $label: $path\n";
        } else {
            echo "Skip (source missing): $label ($path)\n";
            continue;
        }
    }

    $css = file_get_contents($path);
    if ($css === false) {
        fwrite(STDERR, "Could not read $label ($path)\n");
        continue;
    }
    if (strpos($css, $beginMarker) !== false) {
        echo "Already patched: $label\n";
        continue;
    }
    $pos = strpos($css, $marker);
    if ($pos === false) {
        fwrite(STDERR, "Marker not found in $label ($path)\n");
        continue;
    }
    $patched = substr($css, 0, $pos) . $block . "\n\n" . substr($css, $pos);
    if (file_put_contents($path, $patched) === false) {
        fwrite(STDERR, "Failed to write $label ($path)\n");
        continue;
    }
    echo "Patched $label (+" . strlen($block) . " bytes)\n";
}