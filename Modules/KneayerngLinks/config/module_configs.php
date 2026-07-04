<?php

use Modules\Service\Providers\ServiceServiceProvider as ServiceProvider;
use Modules\Service\Http\Middleware\InjectServiceTopNav;
use Modules\Service\Http\Middleware\UseServiceDatabase;
use Modules\Service\Providers\RouteServiceProvider;
use Modules\Accessory\Providers\AccessoryServiceProvider as AccessoryProvider;
use Modules\Accessory\Http\Middleware\InjectAccessoryTopNav;
use Modules\Accessory\Http\Middleware\UseAccessoryDatabase;
use Modules\Accessory\Providers\RouteServiceProvider as AccessoryRouteServiceProvider;

return [
    /**
     * Module aliases. Return the study name and lower name used to refer to the module.
     |
     * - 'module.name' => 'Module Name', // Display name in admin
     * - 'module.alias' => 'module_alias' // URL prefix and config key
     */
    'KneayerngLinks' => [
        'name' => 'KneayerngLinks',
        'alias' => 'kneayernglinks',
        'description' => 'Module to add external links to top navigation bar',
        'keywords' => 'POS,accessory,services',
        'providers' => [
            'Modules\\KneayerngLinks\\Providers\\KneayerngLinksServiceProvider',
            'Modules\\KneayerngLinks\\Providers\\RouteServiceProvider',
            'Modules\\KneayerngLinks\\Providers\\InjectKneayerngLinksTopNav',
            'Modules\\KneayerngLinks\\Providers\\UseKneayerngLinksDatabase',
        ],
        'middleware' => [
            'kneayernglinks.database' => 'Modules\\KneayerngLinks\\Http\\Middleware\\UseKneayerngLinksDatabase',
            'web' => [
                'InjectKneayerngLinksTopNav',
            ],
        ],
        'routes' => [
            'web' => 'Routes/web.php',
        ],
        'publish' => [
            'views' => 'Resources/views',
            'lang' => 'Resources/lang',
        ],
    ],
];