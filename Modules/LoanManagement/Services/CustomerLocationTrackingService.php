<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanCustomer;

class CustomerLocationTrackingService
{
    protected string $connection = 'mysql_loan';

    public function updateRealtimeLocation(LoanCustomer $customer, array $data): void
    {
        DB::connection($this->connection)->table('loan_customer_locations_realtime')->insert([
            'customer_id' => $customer->id,
            'loan_id' => $data['loan_id'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'speed' => $data['speed'] ?? null,
            'heading' => $data['heading'] ?? null,
            'battery_level' => $data['battery_level'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateLatestLocation(LoanCustomer $customer, array $data): void
    {
        DB::connection($this->connection)->table('loan_customer_location_latest')->updateOrInsert(
            ['customer_id' => $customer->id],
            [
                'loan_id' => $data['loan_id'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy' => $data['accuracy'] ?? null,
                'speed' => $data['speed'] ?? null,
                'heading' => $data['heading'] ?? null,
                'battery_level' => $data['battery_level'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'recorded_at' => $data['recorded_at'] ?? now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function enableTracking(LoanCustomer $customer, ?string $note = null): void
    {
        $payload = $this->filterCustomerColumns([
            'allow_gps_tracking' => true,
            'gps_tracking_started_at' => now(),
            'gps_tracking_note' => $note,
            'updated_at' => now(),
        ]);
        if (! empty($payload)) {
            DB::connection($this->connection)->table('loan_customers')->where('id', $customer->id)->update($payload);
            $customer->refresh();
        }
    }

    public function disableTracking(LoanCustomer $customer, ?string $note = null): void
    {
        $payload = $this->filterCustomerColumns([
            'allow_gps_tracking' => false,
            'gps_tracking_stopped_at' => now(),
            'gps_tracking_note' => $note,
            'updated_at' => now(),
        ]);
        if (! empty($payload)) {
            DB::connection($this->connection)->table('loan_customers')->where('id', $customer->id)->update($payload);
            $customer->refresh();
        }
    }

    public function getLatestLocation(int $customerId)
    {
        return DB::connection($this->connection)->table('loan_customer_location_latest')->where('customer_id', $customerId)->first();
    }

    public function getLocationHistory(int $customerId, $from = null, $to = null)
    {
        $q = DB::connection($this->connection)->table('loan_customer_locations_realtime')
            ->where('customer_id', $customerId)
            ->orderByDesc('recorded_at');
        if ($from) {
            $q->where('recorded_at', '>=', $from);
        }
        if ($to) {
            $q->where('recorded_at', '<=', $to);
        }
        return $q->limit(500)->get();
    }

    protected function filterCustomerColumns(array $payload): array
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return [];
        }
        $columns = Schema::connection($this->connection)->getColumnListing('loan_customers');
        return array_intersect_key($payload, array_flip($columns));
    }
}
