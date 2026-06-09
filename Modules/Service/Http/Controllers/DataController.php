<?php

namespace Modules\Service\Http\Controllers;

use Illuminate\Routing\Controller;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [[
            'name' => 'service_module',
            'label' => 'Service Module',
            'default' => false,
        ]];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value' => 'service.access',
                'label' => 'Service Module (access)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        return;
    }
}
