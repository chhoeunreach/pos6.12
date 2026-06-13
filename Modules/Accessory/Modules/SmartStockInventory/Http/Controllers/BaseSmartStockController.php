<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Utils\Util;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class BaseSmartStockController extends Controller
{
    public function __construct(protected Util $util)
    {
    }

    protected function businessId(): int
    {
        return (int) session('user.business_id');
    }

    protected function defaultDateRange(array $input): array
    {
        $start = ! empty($input['start_date']) ? Carbon::parse($input['start_date']) : Carbon::today();
        $end = ! empty($input['end_date']) ? Carbon::parse($input['end_date']) : Carbon::today();

        return [$start->startOfDay(), $end->endOfDay()];
    }

    protected function permittedLocationIds(int $businessId): array
    {
        $permitted = auth()->user()->permitted_locations($businessId);
        if ($permitted === 'all') {
            return DB::table('business_locations')->where('business_id', $businessId)->pluck('id')->all();
        }

        return array_map('intval', (array) $permitted);
    }

    protected function locationOptions(int $businessId)
    {
        return DB::table('business_locations')->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);
    }
}
