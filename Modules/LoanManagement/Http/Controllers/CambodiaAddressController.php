<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\LoanManagement\Services\KhmerNationalIdCard\CambodiaAddressResolverService;

class CambodiaAddressController extends Controller
{
    public function __construct(private CambodiaAddressResolverService $addressResolver)
    {
    }

    public function provinces(): JsonResponse
    {
        return $this->respondWithItems(function () {
            return $this->addressResolver->provinces();
        }, $this->addressResolver->needsSync());
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        try {
            return response()->json([
                'success' => true,
                'sync' => $this->addressResolver->syncBatch(
                    (int) $request->input('page') ?: null,
                    (int) config('loanmanagement.cambodia_address.pages_per_request', 25)
                ),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('LoanManagement Cambodia address sync failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Unable to prepare Cambodia address list.',
            ], 422);
        }
    }

    public function districts(Request $request): JsonResponse
    {
        $request->validate([
            'province_code' => 'required|string',
        ]);

        return $this->respondWithItems(function () use ($request) {
            return $this->addressResolver->districts($request->input('province_code'));
        });
    }

    public function communes(Request $request): JsonResponse
    {
        $request->validate([
            'district_code' => 'required|string',
        ]);

        return $this->respondWithItems(function () use ($request) {
            return $this->addressResolver->communes($request->input('district_code'));
        });
    }

    public function villages(Request $request): JsonResponse
    {
        $request->validate([
            'commune_code' => 'required|string',
        ]);

        return $this->respondWithItems(function () use ($request) {
            return $this->addressResolver->villages($request->input('commune_code'));
        });
    }

    private function respondWithItems(callable $callback, bool $needsSync = false): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'items' => $callback(),
                'needs_sync' => $needsSync,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('LoanManagement Cambodia address options failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Unable to load Cambodia address list.',
                'items' => [],
            ], 422);
        }
    }
}
