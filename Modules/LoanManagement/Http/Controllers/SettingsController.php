<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function invoicePrefix()
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $columns = ['id', 'name', 'location_id'];
        $optionalColumns = ['invoice_prefix', 'invoice_scheme_id', 'receipt_printer_type', 'mobile', 'alternate_number'];
        foreach ($optionalColumns as $col) {
            if (Schema::hasColumn('business_locations', $col)) {
                $columns[] = $col;
            }
        }
        $locations = BusinessLocation::where('business_id', $business_id)->orderBy('name')->get($columns);
        $hasInvoicePrefix = Schema::hasColumn('business_locations', 'invoice_prefix');

        return view('loanmanagement::settings.invoice_prefix', compact('locations', 'hasInvoicePrefix'));
    }

    public function updateInvoicePrefix(Request $request)
    {
        if (! auth()->user()->can('loan_management.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $prefixes = (array) $request->input('invoice_prefixes', []);
        $hasInvoicePrefix = Schema::hasColumn('business_locations', 'invoice_prefix');

        if (! $hasInvoicePrefix) {
            return redirect()
                ->route('loan-management.settings')
                ->with('status', ['success' => 1, 'msg' => 'Your POS version does not support invoice_prefix column. No updates were applied.']);
        }

        foreach ($prefixes as $location_id => $prefix) {
            $clean = trim((string) $prefix);
            $clean = $clean !== '' ? mb_substr($clean, 0, 50) : null;

            BusinessLocation::where('business_id', $business_id)
                ->where('id', (int) $location_id)
                ->update(['invoice_prefix' => $clean]);
        }

        return redirect()
            ->route('loan-management.settings')
            ->with('status', ['success' => 1, 'msg' => 'Invoice prefix settings updated successfully.']);
    }
}
