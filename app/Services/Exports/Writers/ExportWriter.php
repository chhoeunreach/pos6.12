<?php

namespace App\Services\Exports\Writers;

interface ExportWriter
{
    public function open(string $path, array $headings): void;

    public function addRows(array $rows): void;

    public function close(): void;
}
