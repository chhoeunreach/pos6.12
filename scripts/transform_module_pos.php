<?php
/**
 * Transforms the main SellPosController into module-specific copies.
 * MAIN FILE IS NEVER MODIFIED - we only read from it.
 *
 * Transformations for each module controller:
 * - namespace: App\Http\Controllers -> Modules\<Name>\Http\Controllers
 * - view('sale_pos.X') -> view('<name>::sale_pos.X')
 * - $this->moduleUtil->getModuleData('hook', [...]) -> $this->get<Name>ModuleData($this->moduleUtil, 'hook', [...])
 * - \App\Http\Controllers\CashRegisterController/SellController/SalesOrderController/HomeController
 *     -> \Modules\<Name>\Http\Controllers\...
 * - \App\Http\Controllers\SellPosController self-refs -> \Modules\<Name>\Http\Controllers\SellPosController
 * - Service module keeps its isServiceRepairModuleEnabled + Repair module logic
 */

$mainFile = __DIR__ . '/../app/Http/Controllers/SellPosController.php';
$content = file_get_contents($mainFile);

if ($content === false) {
    fwrite(STDERR, "Could not read main controller at $mainFile\n");
    exit(1);
}

// Helper: convert absolute path to POSIX-style relative path so namespaces stay portable.
$repoRoot = realpath(__DIR__ . '/..');
$toRelative = function ($abs) use ($repoRoot) {
    $abs = realpath($abs);
    if ($abs === false) {
        return $abs;
    }
    $rel = str_replace('\\', '/', $abs);
    $root = str_replace('\\', '/', $repoRoot);
    if (strpos($rel, $root) === 0) {
        $rel = substr($rel, strlen($root));
        $rel = ltrim($rel, '/');
    }
    return $rel;
};

$targets = [
    [
        'out'       => __DIR__ . '/../Modules/Accessory/Http/Controllers/SellPosController.php',
        'view_prefix' => 'accessory::',
        'wrapper'   => 'getAccessoryModuleData',
        'name'      => 'Accessory',
    ],
    [
        'out'       => __DIR__ . '/../Modules/Service/Http/Controllers/SellPosController.php',
        'view_prefix' => 'service::',
        'wrapper'   => 'getServiceModuleData',
        'name'      => 'Service',
    ],
];

foreach ($targets as $t) {
    $c = $content;
    $module = $t['name'];

    // 1. Namespace swap - build the target namespace from the relative path
    $rel = $toRelative($t['out']);
    $parts = explode('/', $rel);
    array_pop($parts); // drop filename
    $ns = implode('\\', $parts);
    // Escape backslashes for the regex replacement string
    $nsEscaped = str_replace('\\', '\\\\', $ns);
    $c = preg_replace('/namespace App\\\\Http\\\\Controllers;/', 'namespace ' . $nsEscaped . ';', $c);

    // 2. View prefix: 'sale_pos.X' -> '<module>::sale_pos.X'
    $c = str_replace("view('sale_pos.", "view('" . $t['view_prefix'] . "sale_pos.", $c);
    $c = str_replace('view("sale_pos.', 'view("' . $t['view_prefix'] . 'sale_pos.', $c);

    // 3. $this->moduleUtil->getModuleData('hook', [...]) -> $this->get<Name>ModuleData($this->moduleUtil, 'hook', [...])
    // Supports nested brackets in the array argument.
    $wrapper = $t['wrapper'];
    $patternArgs = '/\$this->moduleUtil->getModuleData\(\s*(\'[^\']+\'|"[^"]+")\s*,\s*(\[[^\]]*(?:\[[^\]]*\][^\]]*)*\])\s*\)/s';
    $c = preg_replace_callback($patternArgs, function ($m) use ($wrapper) {
        return '$this->' . $wrapper . '($this->moduleUtil, ' . $m[1] . ', ' . $m[2] . ')';
    }, $c);

    // Bare getModuleData('name') with no args
    $c = preg_replace_callback(
        '/\$this->moduleUtil->getModuleData\(\s*(\'[^\']+\'|"[^"]+")\s*\)/',
        function ($m) use ($wrapper) {
            return '$this->' . $wrapper . '($this->moduleUtil, ' . $m[1] . ')';
        },
        $c
    );

    // 4. App-level controller class references -> module equivalents
    $replacements = [
        'App\\Http\\Controllers\\CashRegisterController' => "Modules\\$module\\Http\\Controllers\\CashRegisterController",
        'App\\Http\\Controllers\\HomeController'         => "Modules\\$module\\Http\\Controllers\\HomeController",
        'App\\Http\\Controllers\\SellController'         => "Modules\\$module\\Http\\Controllers\\SellController",
        'App\\Http\\Controllers\\SalesOrderController'   => "Modules\\$module\\Http\\Controllers\\SalesOrderController",
        'App\\Http\\Controllers\\SellPosController'      => "Modules\\$module\\Http\\Controllers\\SellPosController",
    ];
    foreach ($replacements as $from => $to) {
        $c = str_replace($from, $to, $c);
    }

    file_put_contents($t['out'], $c);
    echo "Wrote: " . $t['out'] . " (" . strlen($c) . " bytes)\n";
}
