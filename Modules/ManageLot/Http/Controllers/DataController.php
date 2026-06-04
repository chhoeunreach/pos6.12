<?php

namespace Modules\ManageLot\Http\Controllers;

use Illuminate\Routing\Controller;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'manage_lot.view',
                'label' => 'Manage Lot (view)',
                'default' => false,
            ],
        ];
    }
}
