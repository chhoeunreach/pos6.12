<?php

namespace Modules\LoanManagement\Http\Controllers;

trait ApiResponseTrait
{
    protected function ok(string $message = 'OK', $data = [])
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function fail(string $message = 'Error', int $status = 400, $data = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}

