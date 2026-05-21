<?php

namespace App\Services\Exports\Contracts;

use Illuminate\Database\Query\Builder;

interface EnterpriseExporter
{
    public function headings(): array;

    public function query(int $businessId, array $filters): Builder;

    public function map($row): array;

    public function chunkColumn(): string;
}
