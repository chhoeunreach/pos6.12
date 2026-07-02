<?php
// Convert all main-app /sells/pos/hr-* and other related routes in module pos.js files
// to use the module-specific route prefix (accessory/pos/* or service/pos/*).
// Also remap main /products/list, /sells/pos/get-payment-row, /sells/pos/get-product-suggestion,
// /sells/pos/get-featured-products/, /sells/pos/get-recent-transactions, /sells/pos/get_product_row/,
// /sells/pos/get-reward-details, /sells/pos/get-types-of-service-details calls to use module URL helper.

$acc = 'Modules/Accessory/Public/v7/js/pos.js';
$svc = 'Modules/Service/Public/v7/js/pos.js';

// Routes to rewrite (main -> module).
$routes = [
    "'/sells/pos/" => "'/{module}/pos/", // placeholder, replaced per file
    "'/products/list'" => "accessory_pos_url('/products/list')",
    "'/sells/pos/get-product-suggestion'" => "accessory_pos_url('/sells/pos/get-product-suggestion')",
    "'/sells/pos/get_payment_row'" => "accessory_pos_url('/sells/pos/get_payment_row')",
    "'/sells/pos/get-recent-transactions'" => "accessory_pos_url('/sells/pos/get-recent-transactions')",
    "'/sells/pos/get-reward-details'" => "accessory_pos_url('/sells/pos/get-reward-details')",
    "'/sells/pos/get-types-of-service-details'" => "accessory_pos_url('/sells/pos/get-types-of-service-details')",
];

function rewriteModuleUrls($path, $module, $prefix) {
    $src = file_get_contents($path);
    $orig = $src;

    // 1) Replace string-literal '/sells/pos/...' with a call to the module URL helper.
    //    But keep ones that already start with the module prefix intact.
    $re = "/'(?!\s*\{\\\$|\{\\\$prefix\}|\/accessory|\/service|\{prefix\}|\{\\\$module\})(\/sells\/pos\/[a-zA-Z0-9_\-\/]+)'/";
    // Simpler: directly replace '"/sells/pos/' and "'/sells/pos/" with the prefixed call.
    // We do this in two passes: URL inside string literal.
    $src = preg_replace_callback(
        "/'(?<!:)(\\/sells\\/pos\\/[a-zA-Z0-9_\\-\\/]+)'/",
        function ($m) use ($prefix) {
            $url = $m[1];
            // strip leading slash
            $rest = ltrim($url, '/');
            return $prefix . "('" . $url . "')";
        },
        $src
    );

    // 2) Concatenated strings like "'/sells/pos/" + $('#location_id').val()
    $src = preg_replace_callback(
        "/'\\/sells\\/pos\\/hr-sell-list-service-types\\/'\s*\+/",
        function ($m) use ($prefix) {
            return $prefix . "('/sells/pos/hr-sell-list-service-types/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/'\\/sells\\/pos\\/hr-sell-list-detail\\/'\s*\+/",
        function ($m) use ($prefix) {
            return $prefix . "('/sells/pos/hr-sell-list-detail/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/'\\/sells\\/pos\\/get-hr-sell-list\\/'\s*\+/",
        function ($m) use ($prefix) {
            return $prefix . "('/sells/pos/get-hr-sell-list/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/'\\/sells\\/pos\\/get-featured-products\\/'\s*\+/",
        function ($m) use ($prefix) {
            return $prefix . "('/sells/pos/get-featured-products/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/'\\/sells\\/pos\\/get_product_row\\/'\s*\+/",
        function ($m) use ($prefix) {
            return $prefix . "('/sells/pos/get_product_row/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/hr-sell-list-update-service-type'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/hr-sell-list-update-service-type')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/add-hr-sell-list-line'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/add-hr-sell-list-line')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/release-hr-sell-list-line'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/release-hr-sell-list-line')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/resolve-hr-line-product'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/resolve-hr-line-product')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/\\.getJSON\\(\\s*'\\/products\\/list'/",
        function ($m) use ($prefix) {
            return ".getJSON(" . $prefix . "('/products/list')";
        },
        $src
    );

    // Also catch inline jQuery $.ajax({ method:'POST', url: '/sells/pos/...'
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get_payment_row'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get_payment_row')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get-product-suggestion'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get-product-suggestion')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get-recent-transactions'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get-recent-transactions')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get-reward-details'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get-reward-details')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get-types-of-service-details'/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get-types-of-service-details')";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get-featured-products\\/'\s*\+/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get-featured-products/') + ";
        },
        $src
    );
    $src = preg_replace_callback(
        "/url:\s*'\\/sells\\/pos\\/get_product_row\\/'\s*\+/",
        function ($m) use ($prefix) {
            return "url: " . $prefix . "('/sells/pos/get_product_row/') + ";
        },
        $src
    );

    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "Rewrote URLs in $path\n";
    } else {
        echo "No URL changes for $path\n";
    }
}

rewriteModuleUrls($acc, 'accessory', 'accessory_pos_url');
rewriteModuleUrls($svc, 'service', 'service_pos_url');
