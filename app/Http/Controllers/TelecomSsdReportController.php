<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\Services\Reports\TelecomSsdReport;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class TelecomSsdReportController extends Controller
{
    public function index(Request $request, TelecomSsdReport $report)
    {
        abort_unless(auth()->user()->can('purchase_n_sell_report.view'), 403);
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d' . ($request->filled('start_date') ? '|after_or_equal:start_date' : ''),
            'location_id' => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'brand_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'search_text' => 'nullable|string|max:255',
            'export' => 'nullable|in:csv',
            'order' => 'sometimes|array|max:13',
            'order.*.column' => 'required|integer|min:0|max:12',
            'order.*.dir' => 'required|in:asc,desc',
        ]);
        $businessId = (int) $request->session()->get('user.business_id');
        $query = $report->query($businessId, auth()->user()->permitted_locations(), $filters);

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($query, $report, $request) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, TelecomSsdReport::HEADINGS, ',', '"', '');
                $number = 0;
                $report->applyOrder($query, $request->input('order', []))->chunk(500, function ($rows) use ($output, $report, &$number) {
                    foreach ($rows as $row) {
                        fputcsv($output, $report->csvRow($row, ++$number), ',', '"', '');
                    }
                });
                fclose($output);
            }, 'telecom-ssd-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->order(function ($query) use ($report, $request) {
                    $report->applyOrder($query, $request->input('order', []));
                })
                ->make(true);
        }

        return view('report.telecom_ssd_report', [
            'headings' => TelecomSsdReport::HEADINGS,
            'business_locations' => BusinessLocation::forDropdown($businessId),
            'suppliers' => Contact::suppliersDropdown($businessId),
            'brands' => Brands::forDropdown($businessId),
            'categories' => Category::forDropdown($businessId, 'product'),
        ]);
    }
}
