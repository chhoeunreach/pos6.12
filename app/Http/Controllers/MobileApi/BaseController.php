<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Controllers\Controller;

class BaseController extends Controller
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function unauthorized(string $message = 'Unauthorized action.')
    {
        return $this->error($message, 403);
    }

    protected function notFound(string $message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    protected function getBusinessId()
    {
        return auth()->user()->business_id;
    }

    protected function getUserId()
    {
        return auth()->id();
    }

    protected function checkLocationAccess($location_id)
    {
        return \App\User::can_access_this_location($location_id);
    }

    protected function getPermittedLocations()
    {
        return auth()->user()->permitted_locations();
    }

    protected function scopeToBusiness($query, $business_id = null)
    {
        $business_id = $business_id ?: $this->getBusinessId();
        return $query->where('business_id', $business_id);
    }
}
