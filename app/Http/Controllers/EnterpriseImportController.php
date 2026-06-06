<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEnterpriseImport;
use App\Repositories\ImportRepository;
use App\Services\Imports\EnterpriseImportManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnterpriseImportController extends Controller
{
    public function index(ImportRepository $imports)
    {
        $businessId = (int) request()->session()->get('user.business_id');

        return response()->json([
            'data' => $imports->recent($businessId),
        ]);
    }

    public function store(Request $request, EnterpriseImportManager $manager, ImportRepository $imports)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', $manager->supportedTypes()),
            'duplicate_mode' => 'nullable|string|in:skip,replace',
            'file' => 'required|file|max:'.config('async_import.max_upload_kb', 102400).'|mimes:csv,txt,xlsx',
            'metadata' => 'nullable|array',
        ]);

        $typeConfig = config('async_import.types.'.$validated['type']);
        abort_unless($typeConfig, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unsupported import type.');
        abort_unless(
            in_array($validated['type'], $manager->supportedTypes(), true),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'This import type is not supported by the async importer.'
        );

        $this->authorizeImport($typeConfig['permission'] ?? null);

        $file = $request->file('file');
        $storedPath = $file->store('imports/'.date('Y/m/d'), config('async_import.disk', 'local'));

        $import = $imports->create(
            (int) $request->session()->get('user.business_id'),
            (int) $request->user()->id,
            $validated['type'],
            $file->getClientOriginalName(),
            $storedPath,
            $validated['duplicate_mode'] ?? 'skip',
            $validated['metadata'] ?? []
        );

        ProcessEnterpriseImport::dispatch($import->id);

        return response()->json([
            'success' => true,
            'data' => $import->fresh(),
            'msg' => __('lang_v1.success'),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(int $import, ImportRepository $imports)
    {
        $businessId = (int) request()->session()->get('user.business_id');

        return response()->json([
            'data' => $imports->forBusiness($import, $businessId)->load('failures'),
        ]);
    }

    protected function authorizeImport(?string $permission): void
    {
        $businessId = (int) request()->session()->get('user.business_id');

        if (auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.$businessId)) {
            return;
        }

        if ($permission) {
            abort_unless(auth()->user()->can($permission), Response::HTTP_FORBIDDEN);
        }
    }
}
