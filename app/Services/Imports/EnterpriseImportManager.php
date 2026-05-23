<?php

namespace App\Services\Imports;

use InvalidArgumentException;

class EnterpriseImportManager
{
    public function supportedTypes(): array
    {
        return array_keys($this->loanTypeMap());
    }

    public function loanType(string $type): string
    {
        $loanTypeMap = $this->loanTypeMap();

        if (! isset($loanTypeMap[$type])) {
            throw new InvalidArgumentException("Unsupported async import type: {$type}");
        }

        return $loanTypeMap[$type];
    }

    protected function loanTypeMap(): array
    {
        $configuredMap = config('async_import.loan_type_map', []);

        return is_array($configuredMap) && ! empty($configuredMap)
            ? $configuredMap
            : [
                'loans' => 'loans',
                'repayments' => 'payments',
                'imei' => 'imei',
            ];
    }
}
