<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('loan_management.view'), 403, 'Unauthorized action.');

        return view('loanmanagement::dashboard.index');
    }

    public function placeholder(Request $request, string $page)
    {
        abort_unless(auth()->user()->can('loan_management.view'), 403, 'Unauthorized action.');

        return view('loanmanagement::dashboard.placeholder', ['page' => $page]);
    }
}
