<?php

namespace App\Services\Exports;

use App\Services\Exports\Contracts\EnterpriseExporter;
use App\Services\Exports\ReportExporters\AuditReportExporter;
use App\Services\Exports\ReportExporters\CustomerReportExporter;
use App\Services\Exports\ReportExporters\ImeiReportExporter;
use App\Services\Exports\ReportExporters\InventoryReportExporter;
use App\Services\Exports\ReportExporters\LoanReportExporter;
use App\Services\Exports\ReportExporters\ProductReportExporter;
use App\Services\Exports\ReportExporters\RepaymentReportExporter;
use App\Services\Exports\ReportExporters\SalesReportExporter;
use App\Services\Exports\ReportExporters\TransferReportExporter;
use App\Services\Exports\Writers\CsvExportWriter;
use App\Services\Exports\Writers\ExportWriter;
use App\Services\Exports\Writers\XlsxExportWriter;
use InvalidArgumentException;

class EnterpriseExportManager
{
    protected array $exporters = [
        'sales_reports' => SalesReportExporter::class,
        'product_reports' => ProductReportExporter::class,
        'loan_reports' => LoanReportExporter::class,
        'repayment_reports' => RepaymentReportExporter::class,
        'customer_reports' => CustomerReportExporter::class,
        'imei_reports' => ImeiReportExporter::class,
        'inventory_reports' => InventoryReportExporter::class,
        'transfer_reports' => TransferReportExporter::class,
        'audit_reports' => AuditReportExporter::class,
    ];

    public function exporter(string $type): EnterpriseExporter
    {
        if (! isset($this->exporters[$type])) {
            throw new InvalidArgumentException("Unsupported export type: {$type}");
        }

        return app($this->exporters[$type]);
    }

    public function writer(string $format): ExportWriter
    {
        return match (strtolower($format)) {
            'csv' => new CsvExportWriter(),
            'xlsx' => new XlsxExportWriter(),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    public function supportedTypes(): array
    {
        return array_keys($this->exporters);
    }

    public function supportedFormats(): array
    {
        return ['csv', 'xlsx'];
    }
}
