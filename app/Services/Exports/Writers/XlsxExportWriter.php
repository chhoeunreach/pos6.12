<?php

namespace App\Services\Exports\Writers;

use ZipArchive;

class XlsxExportWriter implements ExportWriter
{
    protected string $path;
    protected string $workDir;
    protected $sheet;

    public function open(string $path, array $headings): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP Zip extension is required for XLSX exports.');
        }

        $this->path = $path;
        $this->workDir = storage_path('app/exports/tmp/'.uniqid('xlsx_', true));
        mkdir($this->workDir.'/xl/worksheets', 0775, true);
        mkdir($this->workDir.'/xl/_rels', 0775, true);
        mkdir($this->workDir.'/_rels', 0775, true);

        $this->writePackageFiles();
        $this->sheet = fopen($this->workDir.'/xl/worksheets/sheet1.xml', 'w');
        fwrite($this->sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        $this->writeRow(1, $headings);
        $this->rowNumber = 2;
    }

    protected int $rowNumber = 1;

    public function addRows(array $rows): void
    {
        foreach ($rows as $row) {
            $this->writeRow($this->rowNumber++, $row);
        }
    }

    public function close(): void
    {
        fwrite($this->sheet, '</sheetData></worksheet>');
        fclose($this->sheet);

        $zip = new ZipArchive();
        if ($zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to package XLSX export.');
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->workDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), str_replace('\\', '/', substr($file->getRealPath(), strlen($this->workDir) + 1)));
        }
        $zip->close();
        $this->deleteDirectory($this->workDir);
    }

    protected function writeRow(int $rowNumber, array $values): void
    {
        fwrite($this->sheet, '<row r="'.$rowNumber.'">');
        foreach (array_values($values) as $index => $value) {
            $cell = $this->columnName($index + 1).$rowNumber;
            fwrite($this->sheet, '<c r="'.$cell.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>');
        }
        fwrite($this->sheet, '</row>');
    }

    protected function writePackageFiles(): void
    {
        file_put_contents($this->workDir.'/[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        file_put_contents($this->workDir.'/_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        file_put_contents($this->workDir.'/xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>');
        file_put_contents($this->workDir.'/xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    }

    protected function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    protected function xml($value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function deleteDirectory(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
