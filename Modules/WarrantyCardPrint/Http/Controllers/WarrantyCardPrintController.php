<?php

namespace Modules\WarrantyCardPrint\Http\Controllers;

use Illuminate\Routing\Controller;

class WarrantyCardPrintController extends Controller
{
    public function create()
    {
        if (request()->ajax() || request()->boolean('modal')) {
            return view('warrantycardprint::modal');
        }

        return view('warrantycardprint::create');
    }
}
