<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanImportExportService;

class LoanImportExportController extends Controller
{
    public function index(Request $request, LoanImportExportService $service)
    {
        $type = $request->input('type', 'loans');

        return view('loanmanagement::tools.import_export', [
            'type' => in_array($type, ['payments', 'payment', 'monthly_payments'], true) ? 'payments' : 'loans',
            'recentBatches' => $service->recentBatches(),
            'recentExports' => $service->recentExports(),
        ]);
    }

    public function loans(LoanImportExportService $service)
    {
        return view('loanmanagement::tools.import_export', [
            'type' => 'loans',
            'recentBatches' => $service->recentBatches(),
            'recentExports' => $service->recentExports(),
        ]);
    }

    public function payments(LoanImportExportService $service)
    {
        return view('loanmanagement::tools.import_export', [
            'type' => 'payments',
            'recentBatches' => $service->recentBatches(),
            'recentExports' => $service->recentExports(),
        ]);
    }

    public function import(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'type' => 'required|in:loans,payments',
            'file' => 'required|file|max:10240|mimes:csv,txt',
        ]);

        try {
            $result = $service->import($data['type'], $request->file('file'), auth()->id());

            return redirect()->back()->with('status', [
                'success' => 1,
                'msg' => 'Import completed. Imported: '.$result['imported_rows'].', Invalid: '.$result['invalid_rows'].'.',
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Import failed: '.$e->getMessage(),
            ]);
        }
    }

    public function export(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'type' => 'required|in:loans,payments',
            'status' => 'nullable|string|max:60',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $result = $service->export($data['type'], $request->only(['status', 'date_from', 'date_to']), auth()->id());

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(false);
    }

    public function template(string $type, LoanImportExportService $service)
    {
        $template = $service->template($type);

        return response($template['content'], 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$template['filename'].'"',
        ]);
    }
}
