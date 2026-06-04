<?php

namespace Modules\Accessory\Http\Controllers;

use Illuminate\Routing\Controller;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [[
            'name' => 'accessory_module',
            'label' => 'Accessory Module',
            'default' => false,
        ]];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value' => 'accessory.access',
                'label' => 'Accessory Module (access)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        return;
    }
}
