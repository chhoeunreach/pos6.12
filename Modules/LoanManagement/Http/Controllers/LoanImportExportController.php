<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\LoanImportExportService;

class LoanImportExportController extends Controller
{
    public function index(Request $request, LoanImportExportService $service)
    {
        if ($request->filled('download_invalid')) {
            $result = $service->invalidRowsCsv((int) $request->input('download_invalid'));

            return Response::make($result['content'], 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            ]);
        }

        $type = $request->input('type', 'loans');
        $type = $service->normalizeType($type);

        return view('loanmanagement::tools.import_export', [
            'type' => $type,
            'importTypes' => $service->importTypes(),
            'exportTypes' => $service->exportTypes(),
            'templateDetails' => $service->templateDetails($type),
            'recentBatches' => $service->recentBatches(20, $type),
            'recentExports' => $service->recentExports(20, $type),
        ]);
    }

    public function loans(LoanImportExportService $service)
    {
        return view('loanmanagement::tools.import_export', [
            'type' => 'loans',
            'importTypes' => $service->importTypes(),
            'exportTypes' => $service->exportTypes(),
            'templateDetails' => $service->templateDetails('loans'),
            'recentBatches' => $service->recentBatches(20, 'loans'),
            'recentExports' => $service->recentExports(20, 'loans'),
        ]);
    }

    public function payments(LoanImportExportService $service)
    {
        return view('loanmanagement::tools.import_export', [
            'type' => 'payments',
            'typeLabelOverride' => 'Monthly Payments',
            'exportType' => 'monthly_collections',
            'importTypes' => $service->importTypes(),
            'exportTypes' => $service->exportTypes(),
            'templateDetails' => $service->templateDetails('payments'),
            'recentBatches' => $service->recentBatches(20, 'payments'),
            'recentExports' => $service->recentExports(20, 'monthly_collections'),
        ]);
    }

    public function import(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'type' => 'required|string|max:80',
            'duplicate_mode' => 'nullable|in:skip,replace',
            'file' => 'required|file|max:20480|mimes:csv,txt,xlsx',
        ]);

        if (! array_key_exists($service->normalizeType($data['type']), $service->importTypes())) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Import failed: unsupported import type.',
            ]);
        }

        try {
            $result = $service->import($data['type'], $request->file('file'), auth()->id(), $data['duplicate_mode'] ?? 'skip');

            return redirect()->back()->with('status', [
                'success' => 1,
                'msg' => 'Import completed. Imported: '.$result['imported_rows'].', Skipped: '.$result['skipped_rows'].', Invalid: '.$result['invalid_rows'].'.',
                'batch_id' => $result['batch_id'] ?? null,
                'invalid_rows' => $result['invalid_rows'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Import failed: '.$e->getMessage(),
            ]);
        }
    }

    public function startImport(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'type' => 'required|string|max:80',
            'file' => 'required|file|max:20480|mimes:csv,txt,xlsx',
        ]);

        if (! array_key_exists($service->normalizeType($data['type']), $service->importTypes())) {
            return response()->json([
                'success' => 0,
                'msg' => 'Import failed: unsupported import type.',
            ], 422);
        }

        try {
            return response()->json([
                'success' => 1,
                'progress' => $service->startImport($data['type'], $request->file('file'), auth()->id()),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => 0,
                'msg' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function processImport(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'batch_id' => 'required|integer',
            'duplicate_mode' => 'nullable|in:skip,replace',
        ]);

        try {
            return response()->json([
                'success' => 1,
                'progress' => $service->processImportBatch((int) $data['batch_id'], $data['duplicate_mode'] ?? 'skip'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => 0,
                'msg' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function importProgress(int $batch, LoanImportExportService $service)
    {
        try {
            return response()->json([
                'success' => 1,
                'progress' => $service->batchProgress($batch),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => 0,
                'msg' => $e->getMessage(),
            ], 404);
        }
    }

    public function export(Request $request, LoanImportExportService $service)
    {
        $data = $request->validate([
            'type' => 'required|string|max:80',
            'status' => 'nullable|string|max:60',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if (! array_key_exists($service->normalizeType($data['type']), $service->exportTypes())) {
            abort(422, 'Unsupported export type.');
        }

        $result = $service->export($data['type'], $request->only(['status', 'date_from', 'date_to']), auth()->id());

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(false);
    }

    public function template(string $type, LoanImportExportService $service)
    {
        $template = $service->template($type);
        $content = (string) $template['content'];
        $filename = (string) $template['filename'];
        $mime = $template['mime'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        while (ob_get_level() > 0) {
            $status = ob_get_status();
            if (empty($status['del']) && empty($status['flags'])) {
                break;
            }

            if (isset($status['flags']) && ! ($status['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE)) {
                break;
            }

            ob_end_clean();
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => $mime,
            'Content-Length' => strlen($content),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function invalidRows(int $batch, LoanImportExportService $service)
    {
        $result = $service->invalidRowsCsv($batch);

        return Response::make($result['content'], 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }
}
