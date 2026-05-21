<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CustomerReportExporter implements EnterpriseExporter
{
    public function headings(): array
    {
        return ['ID', 'Contact ID', 'Name', 'Mobile', 'Email', 'Type', 'Status', 'City', 'Created At'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $type = ($filters['contact_type'] ?? 'customer') === 'supplier' ? 'supplier' : 'customer';

        return DB::table('contacts')
            ->where('business_id', $businessId)
            ->whereIn('type', [$type, 'both'])
            ->select('id', 'contact_id', 'name', 'mobile', 'email', 'type', 'contact_status', 'city', 'created_at')
            ->orderBy('id');
    }

    public function map($row): array
    {
        return [$row->id, $row->contact_id, $row->name, $row->mobile, $row->email, $row->type, $row->contact_status, $row->city, $row->created_at];
    }

    public function chunkColumn(): string
    {
        return 'id';
    }
}
