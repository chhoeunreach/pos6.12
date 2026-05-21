<?php

namespace App\Services\Exports\ReportExporters;

use App\Services\Exports\Concerns\FiltersExportQueries;
use App\Services\Exports\Contracts\EnterpriseExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditReportExporter implements EnterpriseExporter
{
    use FiltersExportQueries;

    public function headings(): array
    {
        return ['ID', 'Log Name', 'Description', 'Subject Type', 'Subject ID', 'Causer ID', 'Created At'];
    }

    public function query(int $businessId, array $filters): Builder
    {
        $query = DB::table('activity_log')
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)->orWhere('properties', 'like', '%"business_id":'.$businessId.'%');
            })
            ->select('id', 'log_name', 'description', 'subject_type', 'subject_id', 'causer_id', 'created_at')
            ->orderBy('id');

        $this->applyDateFilter($query, $filters, 'created_at');

        return $query;
    }

    public function map($row): array
    {
        return [$row->id, $row->log_name, $row->description, $row->subject_type, $row->subject_id, $row->causer_id, $row->created_at];
    }

    public function chunkColumn(): string
    {
        return 'id';
    }
}
