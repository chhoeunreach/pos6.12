<?php

namespace App\Services\Exports\Writers;

class CsvExportWriter implements ExportWriter
{
    protected $handle;

    public function open(string $path, array $headings): void
    {
        $this->handle = fopen($path, 'w');
        if (! $this->handle) {
            throw new \RuntimeException('Unable to create export file.');
        }

        fwrite($this->handle, chr(239).chr(187).chr(191));
        fputcsv($this->handle, $headings);
    }

    public function addRows(array $rows): void
    {
        foreach ($rows as $row) {
            fputcsv($this->handle, $row);
        }
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
