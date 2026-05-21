<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEnterpriseExport;
use App\Repositories\ExportRepository;
use App\Services\Exports\EnterpriseExportManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EnterpriseExportController extends Controller
{
    public function index(ExportRepository $exports)
    {
        $businessId = (int) request()->session()->get('user.business_id');

        return response()->json([
            'data' => $exports->recent($businessId),
        ]);
    }

    public function store(Request $request, EnterpriseExportManager $manager, ExportRepository $exports)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', $manager->supportedTypes()),
            'format' => 'nullable|string|in:'.implode(',', $manager->supportedFormats()),
            'filters' => 'nullable|array',
        ]);

        $typeConfig = config('async_export.types.'.$validated['type']);
        abort_unless($typeConfig, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unsupported export type.');
        $this->authorizeExport($typeConfig['permission'] ?? null);

        $businessId = (int) $request->session()->get('user.business_id');
        $userId = (int) $request->user()->id;
        $format = $validated['format'] ?? 'csv';

        $export = $exports->create(
            $businessId,
            $userId,
            $validated['type'],
            $validated['filters'] ?? $request->except(['_token', 'type', 'format']),
            $format
        );

        ProcessEnterpriseExport::dispatch($export->id);

        return response()->json([
            'success' => true,
            'data' => $export->fresh(),
            'msg' => __('lang_v1.success'),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(int $export, ExportRepository $exports)
    {
        $businessId = (int) request()->session()->get('user.business_id');

        return response()->json([
            'data' => $exports->forBusiness($export, $businessId),
        ]);
    }

    public function download(int $export, ExportRepository $exports)
    {
        $businessId = (int) request()->session()->get('user.business_id');
        $export = $exports->forBusiness($export, $businessId);

        abort_unless($export->status === 'completed', Response::HTTP_NOT_FOUND);
        abort_unless(! empty($export->path), Response::HTTP_NOT_FOUND);
        abort_if($export->download_expires_at && $export->download_expires_at->isPast(), Response::HTTP_GONE);

        $disk = config('async_export.disk', 'local');
        abort_unless(Storage::disk($disk)->exists($export->path), Response::HTTP_NOT_FOUND);

        return Storage::disk($disk)->download($export->path, $export->filename);
    }

    protected function authorizeExport(?string $permission): void
    {
        if (auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.session('business.id'))) {
            return;
        }

        if ($permission) {
            $this->middleware('can:'.$permission);
            abort_unless(auth()->user()->can($permission), Response::HTTP_FORBIDDEN);
        }
    }
}
