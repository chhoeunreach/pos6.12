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
        $user = auth('api')->user()
            ?: request()->user('api')
            ?: request()->user()
            ?: auth()->user();

        return $user ? $user->business_id : (session('user.business_id') ?: 1);
    }

    protected function getUserId()
    {
        return auth('api')->id()
            ?: request()->user('api')?->id
            ?: request()->user()?->id
            ?: auth()->id()
            ?: session('user.id')
            ?: 1;
    }

    protected function checkLocationAccess($location_id)
    {
        return \App\BusinessLocation::where('business_id', $this->getBusinessId())->where('id', $location_id)->exists()
            && \App\User::can_access_this_location($location_id);
    }

    protected function getPermittedLocations()
    {
        $user = auth('api')->user() ?: auth()->user();

        return $user ? $user->permitted_locations() : [];
    }

    protected function scopeToBusiness($query, $business_id = null)
    {
        $business_id = $business_id ?: $this->getBusinessId();
        return $query->where('business_id', $business_id);
    }
}
