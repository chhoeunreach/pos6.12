<?php

namespace App\Services\Concerns;

/** Grants bulk-operation headroom for full-business scans that load the whole ledger into memory. */
trait RaisesBulkMemoryLimits
{
    private function raiseLimits(): void
    {
        @set_time_limit(0);
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '') {
            return;
        }
        $bytes = $this->memoryLimitInBytes((string) $limit);
        if ($bytes !== -1 && $bytes < 1073741824) {
            @ini_set('memory_limit', '1024M');
        }
    }

    private function memoryLimitInBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }
        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
