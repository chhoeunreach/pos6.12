<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ArrayExport implements FromArray
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private array $rows)
    {
    }

    public function array(): array
    {
        $headings = array_keys($this->rows[0] ?? []);
        $data = [];
        if (! empty($headings)) {
            $data[] = $headings;
        }

        foreach ($this->rows as $row) {
            $data[] = array_map(fn ($k) => $row[$k] ?? null, $headings);
        }

        return $data;
    }
}

