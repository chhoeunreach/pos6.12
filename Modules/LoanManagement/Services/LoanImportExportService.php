<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

class LoanImportExportService
{
    protected $connection = 'mysql_loan';
    protected ?int $currentImportUserId = null;

    public function importTypes(): array
    {
        return [
            'customers' => [
                'label' => 'Customers',
                'description' => 'Create or update loan customers before importing loans.',
            ],
            'loans' => [
                'label' => 'Full Loan Information',
                'description' => 'Import full loan information, including customer details, loan records, product snapshots, and monthly schedules.',
            ],
            'full_loan_update' => [
                'label' => 'Full Loan Update',
                'description' => 'One-file update for invoice, customer, product, customer deposit payment, and payment collection fields.',
            ],
            'active_loans' => [
                'label' => 'Active / Ongoing Installments',
                'description' => 'Import or update exported active/ongoing loan rows, including status, paid amount, balance, and collection fields.',
            ],
            'schedules' => [
                'label' => 'Payment Schedules',
                'description' => 'Import or update installment due schedules for existing loans.',
            ],
            'payments' => [
                'label' => 'Monthly Payments',
                'description' => 'Import customer monthly installment collections with method-aware duplicate checks.',
            ],
            'customer_deposit_payments' => [
                'label' => 'Customer Deposit Payments',
                'description' => 'Import customer deposit/down-payment loan payments with method-aware duplicate checks.',
            ],
            'loan_payments' => [
                'label' => 'Loan Payments',
                'description' => 'Import loan/down-payment records with payment_type = loan.',
            ],
            'guarantors' => [
                'label' => 'Guarantors',
                'description' => 'Attach guarantor details to an existing loan or customer.',
            ],
            'imei' => [
                'label' => 'IMEI / Serial Items',
                'description' => 'Import product item IMEI or serial numbers for loan products.',
            ],
            'collection_assignments' => [
                'label' => 'Collection Assignments',
                'description' => 'Update collector, collection status, risk level, and follow-up fields on loans.',
            ],
            'customer_import' => [
                'label' => 'Customer Import',
                'description' => 'Create or update customer master data used by loan migration.',
            ],
        ];
    }

    public function exportTypes(): array
    {
        return [
            'customers' => ['label' => 'Customers', 'description' => 'Loan customer master list.'],
            'loans' => ['label' => 'All Loans', 'description' => 'All loan account records.'],
            'full_loan_update' => ['label' => 'Full Loan Update', 'description' => 'One export file containing invoice, customer, product, deposit payment, and collection payment fields for correction/import.'],
            'active_loans' => ['label' => 'Active Loans', 'description' => 'Loans filtered to active/open status where available.'],
            'active_loan_deposit_template' => ['label' => 'Active Loan Deposit Fill Rows', 'description' => 'Upload-ready Customer Deposit Payment rows generated from active/ongoing loans.'],
            'active_loan_schedule_template' => ['label' => 'Active Loan Schedule Fill Rows', 'description' => 'Upload-ready Payment Schedule rows generated from active/ongoing loans, including placeholder rows when schedules are missing.'],
            'closed_loans' => ['label' => 'Closed Loans', 'description' => 'Loans filtered to closed/completed/paid status.'],
            'overdue_loans' => ['label' => 'Overdue Loans', 'description' => 'Loans marked overdue or with overdue workflow fields.'],
            'collection_report' => ['label' => 'Collection Report', 'description' => 'Collection workflow, follow-up, overdue, and collector fields.'],
            'customer_loan_history' => ['label' => 'Customer Loan History', 'description' => 'Loan history grouped by customer fields.'],
            'payments' => ['label' => 'Payment History', 'description' => 'All loan payment records.'],
            'payment_history' => ['label' => 'Payment History', 'description' => 'All loan payment records.'],
            'customer_deposit_payments' => ['label' => 'Customer Deposit Payments', 'description' => 'Customer deposit/down-payment loan payment records.'],
            'loan_payments' => ['label' => 'Loan Payments', 'description' => 'Loan/down-payment records stored with payment_type = loan.'],
            'repossessed_assets' => ['label' => 'Repossessed Assets', 'description' => 'Loans and product assets marked for repossession.'],
            'monthly_loan_summary' => ['label' => 'Monthly Loan Summary', 'description' => 'Loans grouped/exported for monthly summary analysis.'],
            'monthly_collections' => ['label' => 'Monthly Collections', 'description' => 'Payment collections for the selected date range.'],
            'schedules' => ['label' => 'Payment Schedules', 'description' => 'Installment schedule rows.'],
            'guarantors' => ['label' => 'Guarantors', 'description' => 'Guarantor master/export data.'],
            'imei' => ['label' => 'IMEI / Serial Items', 'description' => 'Loan product item IMEI or serial numbers.'],
            'collection_assignments' => ['label' => 'Collection Assignments', 'description' => 'Collector and collection workflow fields from loans.'],
        ];
    }

    public function templateDetails(string $type): array
    {
        $type = $this->normalizeType($type);
        $templates = $this->templateDefinitions();

        if ($type === 'active_loans') {
            return [
                'columns' => $this->exportColumns('active_loans'),
                'required' => ['loan_number or id'],
                'optional' => ['customer fields', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'installment_count', 'loan_date', 'status', 'collection_status', 'assigned_collector_id'],
                'example' => ['', 'KY-000001', '', 'Sok Dara', '012345678', '500.00', '550.00', '550.00', '0.00', '12', now()->toDateString(), 'closed', 'paid_off', ''],
                'notes' => 'Export Active / Ongoing Installments, update status/paid/balance/customer fields, then import with Replace existing. Duplicate rows match by loan number or id.',
            ];
        }
        if ($type === 'full_loan_update') {
            return [
                'columns' => $this->exportColumns('full_loan_update'),
                'required' => ['loan_invoice or loan_id'],
                'optional' => ['invoice fields', 'customer fields', 'product fields', 'deposit payment fields', 'collection payment fields', 'paid_amount', 'balance_amount', 'status'],
                'example' => ['KY-000001', '', now()->toDateString(), 'closed', 'Sok Dara', '012345678', '', 'iPhone', 'IMEI001', 'SN001', '550.00', '550.00', '0.00', '200.00', now()->toDateString(), 'Cash', 'DEP-KY-000001', 'confirmed', '350.00', now()->toDateString(), 'Cash', 'COL-KY-000001', 'confirmed', 'Full correction row'],
                'notes' => 'Export, fill missing customer/product/deposit/collection data, then import with Replace existing. Deposit columns create payment_type=loan. Collection columns create payment_type=monthly.',
            ];
        }
        if ($type === 'loan_payments') {
            return $templates['customer_deposit_payments'];
        }

        return $templates[$type] ?? $templates['loans'];
    }

    public function import(string $type, UploadedFile $file, ?int $userId = null, string $duplicateMode = 'skip'): array
    {
        $type = $this->normalizeType($type);
        $this->currentImportUserId = $userId;
        $this->ensurePaymentTypeColumn();
        $this->ensureImportBatchColumns();
        $duplicateMode = in_array($duplicateMode, ['skip', 'replace'], true) ? $duplicateMode : 'skip';
        $rows = $this->readImportFile($file, $type);
        $headers = array_shift($rows) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        if (empty($headers)) {
            throw new \RuntimeException('Import file does not contain a header row.');
        }

        $rows = $this->cleanImportDataRows($rows);
        $batchId = $this->createBatch($type, $file, $userId, count($rows), $headers);
        $valid = 0;
        $invalid = 0;
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $raw = $this->combineRow($headers, $row);
            $normalized = $this->normalizeImportRow($type, $raw);
            $errors = $this->validateImportRow($type, $normalized, $duplicateMode);

            $rowId = $this->createImportRow($batchId, $index + 2, $raw, $normalized, $errors);

            if (! empty($errors)) {
                $invalid++;
                continue;
            }

            $duplicateId = $this->existingImportRowId($type, $normalized);
            if ($duplicateId && $duplicateMode === 'skip') {
                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'skipped',
                    'loan_id' => in_array($type, array_merge($this->loanImportTypes(), ['full_loan_update', 'schedules'], $this->paymentImportTypes()), true) ? (($this->isLoanImportType($type) || $this->isFullLoanUpdateType($type)) ? $duplicateId : ($normalized['loan_id'] ?? null)) : null,
                    'error_message' => 'Skipped duplicate existing record.',
                    'updated_at' => now(),
                ]));
                $skipped++;
                continue;
            }

            try {
                $id = DB::connection($this->connection)->transaction(function () use ($type, $normalized, $duplicateMode, $duplicateId) {
                    return $this->isPaymentImportType($type)
                        ? $this->storePayment($normalized, $duplicateMode, $duplicateId)
                        : ($this->isFullLoanUpdateType($type) ? $this->storeFullLoanUpdate($normalized, $duplicateMode, $duplicateId) : ($this->isLoanImportType($type) ? $this->storeLoanImportRow($type, $normalized, $duplicateMode, $duplicateId) : $this->storeGenericImport($type, $normalized, $duplicateMode, $duplicateId)));
                });

                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => $duplicateId && $duplicateMode === 'replace' ? 'replaced' : 'imported',
                    'loan_id' => ($this->isLoanImportType($type) || $this->isFullLoanUpdateType($type)) ? $id : ($normalized['loan_id'] ?? null),
                    'updated_at' => now(),
                ]));
                $valid++;
                $imported++;
            } catch (\Throwable $e) {
                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]));
                $invalid++;
            }
        }

        DB::connection($this->connection)->table('loan_import_batches')->where('id', $batchId)->update($this->safeColumns('loan_import_batches', [
            'status' => $invalid > 0 ? 'completed_with_errors' : 'completed',
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'imported_rows' => $imported,
            'updated_at' => now(),
        ]));

        return [
            'batch_id' => $batchId,
            'total_rows' => $valid + $invalid + $skipped,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
        ];
    }

    public function startImport(string $type, UploadedFile $file, ?int $userId = null): array
    {
        $type = $this->normalizeType($type);
        $this->ensurePaymentTypeColumn();
        $this->ensureImportBatchColumns();
        $rows = $this->readImportFile($file, $type);
        $headers = array_shift($rows) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        if (empty($headers)) {
            throw new \RuntimeException('Import file does not contain a header row.');
        }

        $rows = $this->cleanImportDataRows($rows);
        $batchId = $this->createBatch($type, $file, $userId, count($rows), $headers);

        return $this->batchProgress($batchId);
    }

    public function processImportBatch(int $batchId, string $duplicateMode = 'skip', int $limit = 50): array
    {
        $duplicateMode = in_array($duplicateMode, ['skip', 'replace'], true) ? $duplicateMode : 'skip';
        $limit = max(1, min(200, $limit));
        $this->ensureImportBatchColumns();

        $batch = $this->importBatch($batchId);
        if (! $batch) {
            throw new \RuntimeException('Import batch was not found.');
        }
        $this->currentImportUserId = (int) ($batch->uploaded_by ?? 0) ?: null;

        if (in_array((string) ($batch->status ?? ''), ['completed', 'completed_with_errors'], true)) {
            return $this->batchProgress($batchId);
        }

        $headers = json_decode((string) ($batch->column_mapping_json ?? '[]'), true) ?: [];
        if (empty($headers)) {
            throw new \RuntimeException('Import batch does not contain header mapping.');
        }

        $rows = $this->readStoredImportFile((string) $batch->file_path, (string) $batch->file_type);
        array_shift($rows);
        $rows = $this->cleanImportDataRows($rows);

        $lastRowNo = (int) DB::connection($this->connection)
            ->table('loan_import_rows')
            ->where('batch_id', $batchId)
            ->max('row_no');
        $startIndex = $lastRowNo > 1 ? $lastRowNo - 1 : 0;

        $valid = 0;
        $invalid = 0;
        $imported = 0;
        $skipped = 0;
        $processed = 0;
        $type = $this->normalizeType((string) $batch->file_type);

        for ($index = $startIndex; $index < count($rows) && $processed < $limit; $index++, $processed++) {
            $row = $rows[$index];
            $raw = $this->combineRow($headers, $row);

            if ($this->isEmptyRow($row)) {
                $rowId = $this->createImportRow($batchId, $index + 2, $raw, [], []);
                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'skipped',
                    'error_message' => 'Skipped empty row.',
                    'updated_at' => now(),
                ]));
                $skipped++;
                continue;
            }

            $normalized = $this->normalizeImportRow($type, $raw);
            $errors = $this->validateImportRow($type, $normalized, $duplicateMode);
            $rowId = $this->createImportRow($batchId, $index + 2, $raw, $normalized, $errors);

            if (! empty($errors)) {
                $invalid++;
                continue;
            }

            $duplicateId = $this->existingImportRowId($type, $normalized);
            if ($duplicateId && $duplicateMode === 'skip') {
                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'skipped',
                    'loan_id' => in_array($type, array_merge($this->loanImportTypes(), ['full_loan_update', 'schedules'], $this->paymentImportTypes()), true) ? (($this->isLoanImportType($type) || $this->isFullLoanUpdateType($type)) ? $duplicateId : ($normalized['loan_id'] ?? null)) : null,
                    'error_message' => 'Skipped duplicate existing record.',
                    'updated_at' => now(),
                ]));
                $skipped++;
                continue;
            }

            try {
                $id = DB::connection($this->connection)->transaction(function () use ($type, $normalized, $duplicateMode, $duplicateId) {
                    return $this->isPaymentImportType($type)
                        ? $this->storePayment($normalized, $duplicateMode, $duplicateId)
                        : ($this->isFullLoanUpdateType($type) ? $this->storeFullLoanUpdate($normalized, $duplicateMode, $duplicateId) : ($this->isLoanImportType($type) ? $this->storeLoanImportRow($type, $normalized, $duplicateMode, $duplicateId) : $this->storeGenericImport($type, $normalized, $duplicateMode, $duplicateId)));
                });

                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => $duplicateId && $duplicateMode === 'replace' ? 'replaced' : 'imported',
                    'loan_id' => ($this->isLoanImportType($type) || $this->isFullLoanUpdateType($type)) ? $id : ($normalized['loan_id'] ?? null),
                    'updated_at' => now(),
                ]));
                $valid++;
                $imported++;
            } catch (\Throwable $e) {
                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]));
                $invalid++;
            }
        }

        $progress = $this->batchProgress($batchId);
        $finished = (int) $progress['processed_rows'] >= (int) $progress['total_rows'];
        DB::connection($this->connection)->table('loan_import_batches')->where('id', $batchId)->update($this->safeColumns('loan_import_batches', [
            'status' => $finished ? ((int) $progress['invalid_rows'] > 0 ? 'completed_with_errors' : 'completed') : 'processing',
            'valid_rows' => (int) ($progress['valid_rows'] ?? 0),
            'invalid_rows' => (int) ($progress['invalid_rows'] ?? 0),
            'imported_rows' => (int) ($progress['imported_rows'] ?? 0),
            'updated_at' => now(),
        ]));

        return $this->batchProgress($batchId);
    }

    public function batchProgress(int $batchId): array
    {
        $batch = $this->importBatch($batchId);
        if (! $batch) {
            throw new \RuntimeException('Import batch was not found.');
        }

        $counts = Schema::connection($this->connection)->hasTable('loan_import_rows')
            ? DB::connection($this->connection)->table('loan_import_rows')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->where('batch_id', $batchId)
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all()
            : [];

        $total = max(0, (int) ($batch->total_rows ?? 0));
        $processed = array_sum(array_map('intval', $counts));
        $imported = (int) ($counts['imported'] ?? 0) + (int) ($counts['replaced'] ?? 0);
        $invalid = (int) ($counts['invalid'] ?? 0) + (int) ($counts['failed'] ?? 0);
        $skipped = (int) ($counts['skipped'] ?? 0);
        $percent = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 100;

        return [
            'batch_id' => $batchId,
            'status' => $batch->status ?? 'processing',
            'total_rows' => $total,
            'processed_rows' => $processed,
            'valid_rows' => $imported,
            'invalid_rows' => $invalid,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'percent' => $percent,
            'done' => in_array((string) ($batch->status ?? ''), ['completed', 'completed_with_errors'], true) || ($total > 0 && $processed >= $total),
        ];
    }

    public function export(string $type, array $filters = [], ?int $userId = null): array
    {
        $type = $this->normalizeType($type);
        $columns = $this->exportColumns($type);
        $rows = $this->exportRows($type, $filters);
        $format = $type === 'active_loans' ? 'xlsx' : 'csv';

        $logId = $this->createExportLog($type, $filters, $userId, $rows->count(), $format);
        $filename = 'loan-management-'.$type.'-'.now()->format('Ymd-His').'.'.$format;
        $relativePath = 'storage/exports/'.$filename;
        $absolutePath = $this->moduleStoragePath('exports', $filename);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        if ($format === 'xlsx') {
            $data = [$columns];
            foreach ($rows as $row) {
                $data[] = array_map(fn ($column) => $row->{$column} ?? '', $columns);
            }
            file_put_contents($absolutePath, $this->xlsxContentFromRows($type, $data));
        } else {
            $handle = fopen($absolutePath, 'w');
            fputcsv($handle, $columns);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($column) => $row->{$column} ?? '', $columns));
            }
            fclose($handle);
        }

        if ($logId && Schema::connection($this->connection)->hasTable('loan_export_logs')) {
            DB::connection($this->connection)->table('loan_export_logs')->where('id', $logId)->update($this->safeColumns('loan_export_logs', [
                'status' => 'completed',
                'file_path' => $relativePath,
                'finished_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return [
            'path' => $absolutePath,
            'filename' => $filename,
            'rows_count' => $rows->count(),
        ];
    }

    protected function xlsxContentFromRows(string $type, array $rows): string
    {
        $definition = [
            'columns' => $rows[0] ?? [],
            'example' => $rows[1] ?? [],
        ];

        return $this->xlsxTemplateContent($type, $definition + ['_export_rows' => $rows]);
    }

    public function template(string $type): array
    {
        $type = $this->normalizeType($type);
        $definition = $this->templateDetails($type);

        return [
            'filename' => $this->templateFilename($type),
            'content' => $this->xlsxTemplateContent($type, $definition),
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    protected function templateFilename(string $type): string
    {
        $type = $this->normalizeType($type);

        return 'loan-management-'.Str::slug(str_replace('_', '-', $type)).'-import-template.xlsx';
    }

    protected function xlsxTemplateContent(string $type, array $definition): string
    {
        if (isset($definition['_export_rows']) && is_array($definition['_export_rows'])) {
            $rows = $definition['_export_rows'];
        } else {
            $columns = array_values($definition['columns'] ?? []);
            $example = array_values($definition['example'] ?? []);
            $required = implode(', ', array_values($definition['required'] ?? []));
            $optional = implode(', ', array_values($definition['optional'] ?? []));
            $notes = (string) ($definition['notes'] ?? '');

            $rows = [
                $columns,
                array_pad($example, count($columns), ''),
            ];

            if ($required !== '' || $optional !== '' || $notes !== '') {
                $rows[] = [];
                $rows[] = ['Required', $required];
                $rows[] = ['Optional', $optional];
                $rows[] = ['Notes', $notes];
            }
        }

        $sheetName = $this->xlsxSheetName((string) ($this->importTypes()[$type]['label'] ?? ucwords(str_replace('_', ' ', $type))));
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->xlsxTemplateContentWithPhpSpreadsheet($sheetName, $rows);
        }

        $sheetXml = $this->xlsxWorksheetXml($rows);
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="23426"/>'
            .'<workbookPr defaultThemeVersion="164011"/>'
            .'<sheets><sheet name="'.$this->xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<calcPr calcId="191029"/>'
            .'</workbook>';

        $path = tempnam(sys_get_temp_dir(), 'loan-template-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create import template.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new \RuntimeException('Unable to create import template.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/theme/theme1.xml', $this->xlsxThemeXml());
        $zip->addFromString('docProps/core.xml', $this->xlsxCorePropertiesXml());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppPropertiesXml($sheetName));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if ($content === false) {
            throw new \RuntimeException('Unable to create import template.');
        }

        return $content;
    }

    protected function xlsxTemplateContentWithPhpSpreadsheet(string $sheetName, array $rows): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $columnIndex + 1,
                    $rowIndex + 1,
                    (string) $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
        }

        $columnCount = max(1, count($rows[0] ?? []));
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        for ($column = 1; $column <= $columnCount; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'loan-template-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create import template.');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($path);
        @unlink($path);

        if ($content === false) {
            throw new \RuntimeException('Unable to create import template.');
        }

        return $content;
    }

    protected function xlsxSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', ' ', $name) ?: 'Sheet1';
        $name = trim(preg_replace('/\\s+/', ' ', $name) ?: 'Sheet1');
        $name = trim($name, "'");

        return mb_substr($name !== '' ? $name : 'Sheet1', 0, 31);
    }

    public function recentBatches(int $limit = 20, ?string $type = null)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return collect();
        }

        $query = DB::connection($this->connection)->table('loan_import_batches');
        if ($type !== null) {
            $query->where('file_type', $this->importBatchStorageType($this->normalizeType($type)));
        }

        return $query->orderByDesc('id')->limit($limit)->get();
    }

    public function recentExports(int $limit = 20, ?string $type = null)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_export_logs')) {
            return collect();
        }

        $query = DB::connection($this->connection)->table('loan_export_logs');
        if ($type !== null) {
            $query->where('export_type', $this->normalizeType($type));
        }

        return $query->orderByDesc('id')->limit($limit)->get();
    }

    public function invalidRowsCsv(int $batchId): array
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_rows')) {
            throw new \RuntimeException('loan_import_rows table is not available.');
        }

        $batch = Schema::connection($this->connection)->hasTable('loan_import_batches')
            ? DB::connection($this->connection)->table('loan_import_batches')->where('id', $batchId)->first()
            : null;

        $rows = DB::connection($this->connection)->table('loan_import_rows')
            ->where('batch_id', $batchId)
            ->whereIn('status', ['invalid', 'failed'])
            ->orderBy('row_no')
            ->get(['row_no', 'status', 'error_message', 'raw_row_json', 'normalized_json']);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['row_no', 'status', 'error_message', 'raw_row_json', 'normalized_json']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->row_no,
                $row->status,
                $row->error_message,
                $row->raw_row_json,
                $row->normalized_json,
            ]);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'filename' => 'loan-import-invalid-'.($batch->batch_code ?? $batchId).'.csv',
            'content' => (string) $content,
        ];
    }

    public function normalizeType(string $type): string
    {
        $type = strtolower(trim(str_replace('-', '_', $type)));
        $aliases = [
            'payment' => 'payments',
            'monthly_payment' => 'payments',
            'monthly_payments' => 'payments',
            'monthly_collections' => 'monthly_collections',
            'payment_import' => 'payments',
            'payment_import_template' => 'payments',
            'payment_history' => 'payments',
            'loan_payment' => 'customer_deposit_payments',
            'loan_payments' => 'loan_payments',
            'deposit_payment' => 'customer_deposit_payments',
            'deposit_payments' => 'customer_deposit_payments',
            'customer_deposit' => 'customer_deposit_payments',
            'customer_deposit_payment' => 'customer_deposit_payments',
            'customer_deposit_payments' => 'customer_deposit_payments',
            'customer' => 'customers',
            'customer_import' => 'customers',
            'customer_import_template' => 'customers',
            'loan' => 'loans',
            'loan_information' => 'loans',
            'loan_info' => 'loans',
            'full_loan_information' => 'loans',
            'full_loan_info' => 'loans',
            'full_update' => 'full_loan_update',
            'full_loan_update' => 'full_loan_update',
            'full_customer_invoice_product_payment' => 'full_loan_update',
            'all_loans' => 'loans',
            'active_loan' => 'active_loans',
            'active_loans' => 'active_loans',
            'active_installments' => 'active_loans',
            'active_ongoing_installments' => 'active_loans',
            'ongoing_installments' => 'active_loans',
            'active_deposit_template' => 'active_loan_deposit_template',
            'active_deposit_rows' => 'active_loan_deposit_template',
            'active_loan_deposit_rows' => 'active_loan_deposit_template',
            'active_schedule_template' => 'active_loan_schedule_template',
            'active_schedule_rows' => 'active_loan_schedule_template',
            'active_loan_schedule_rows' => 'active_loan_schedule_template',
            'loan_import' => 'loans',
            'loan_import_template' => 'loans',
            'schedule' => 'schedules',
            'payment_schedules' => 'schedules',
            'schedule_import' => 'schedules',
            'schedule_import_template' => 'schedules',
            'closed_accounts' => 'closed_loans',
            'closed_loan' => 'closed_loans',
            'collection' => 'collection_report',
            'customer_history' => 'customer_loan_history',
            'repossessions' => 'repossessed_assets',
            'repossessed' => 'repossessed_assets',
            'guarantor' => 'guarantors',
            'imei_serial' => 'imei',
            'imei_serial_items' => 'imei',
            'collection_assignment' => 'collection_assignments',
        ];

        return $aliases[$type] ?? $type;
    }

    protected function paymentImportTypes(): array
    {
        return ['payments', 'customer_deposit_payments', 'loan_payments'];
    }

    protected function loanImportTypes(): array
    {
        return ['loans', 'active_loans'];
    }

    protected function isLoanImportType(string $type): bool
    {
        return in_array($type, $this->loanImportTypes(), true);
    }

    protected function isFullLoanUpdateType(string $type): bool
    {
        return $type === 'full_loan_update';
    }

    protected function isPaymentImportType(string $type): bool
    {
        return in_array($type, $this->paymentImportTypes(), true);
    }

    protected function importBatchStorageType(string $type): string
    {
        return $type === 'customer_deposit_payments' ? 'loan_payments' : $type;
    }

    protected function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    protected function readImportFile(UploadedFile $file, string $type): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return $extension === 'xlsx'
            ? $this->readXlsx($file->getRealPath(), $type)
            : $this->readCsv($file->getRealPath());
    }

    protected function readXlsx(string $path, string $type): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to read XLSX file.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetPath = $this->xlsxSheetPath($zip, $type);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Unable to locate worksheet for import type.');
        }

        $reader = new \XMLReader();
        $reader->XML($xml);
        $rows = [];
        $currentRow = [];
        $currentCellRef = null;
        $currentCellType = null;
        $currentValue = '';

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $currentRow = [];
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'c') {
                $currentCellRef = $reader->getAttribute('r');
                $currentCellType = $reader->getAttribute('t');
                $currentValue = '';
            } elseif ($reader->nodeType === \XMLReader::ELEMENT && in_array($reader->localName, ['v', 't'], true)) {
                $currentValue .= $reader->readString();
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'c') {
                $columnIndex = $this->xlsxColumnIndex((string) $currentCellRef);
                $value = $currentValue;
                if ($currentCellType === 's' && $value !== '') {
                    $value = $sharedStrings[(int) $value] ?? $value;
                }
                $currentRow[$columnIndex] = trim((string) $value);
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'row') {
                if (! empty($currentRow)) {
                    ksort($currentRow);
                    $maxColumn = max(array_keys($currentRow));
                    $rows[] = array_map(
                        fn ($index) => $currentRow[$index] ?? '',
                        range(0, $maxColumn)
                    );
                }
            }
        }
        $reader->close();

        return $rows;
    }

    protected function xlsxWorksheetXml(array $rows): string
    {
        $sheetData = '';
        $maxColumns = 1;

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';
            $values = array_values($row);
            $maxColumns = max($maxColumns, count($values));
            foreach ($values as $columnIndex => $value) {
                $cellRef = $this->xlsxColumnName($columnIndex + 1).$rowNumber;
                $cells .= '<c r="'.$cellRef.'" s="'.($rowIndex === 0 ? '1' : '0').'" t="inlineStr"><is><t>'.$this->xml((string) $value).'</t></is></c>';
            }
            $sheetData .= '<row r="'.$rowNumber.'">'.$cells.'</row>';
        }

        $dimension = 'A1:'.$this->xlsxColumnName($maxColumns).max(1, count($rows));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<sheetData>'.$sheetData.'</sheetData>'
            .'<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';
    }

    protected function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            .'</styleSheet>';
    }

    protected function xlsxThemeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">'
            .'<a:themeElements><a:clrScheme name="Office"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F497D"/></a:dk2><a:lt2><a:srgbClr val="EEECE1"/></a:lt2><a:accent1><a:srgbClr val="4F81BD"/></a:accent1><a:accent2><a:srgbClr val="C0504D"/></a:accent2><a:accent3><a:srgbClr val="9BBB59"/></a:accent3><a:accent4><a:srgbClr val="8064A2"/></a:accent4><a:accent5><a:srgbClr val="4BACC6"/></a:accent5><a:accent6><a:srgbClr val="F79646"/></a:accent6><a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme>'
            .'<a:fontScheme name="Office"><a:majorFont><a:latin typeface="Cambria"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme>'
            .'<a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements>'
            .'<a:objectDefaults/><a:extraClrSchemeLst/></a:theme>';
    }

    protected function xlsxCorePropertiesXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>LoanManagement</dc:creator><cp:lastModifiedBy>LoanManagement</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    protected function xlsxAppPropertiesXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Microsoft Excel</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            .'<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            .'<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>'.$this->xml($sheetName).'</vt:lpstr></vt:vector></TitlesOfParts>'
            .'<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion>'
            .'</Properties>';
    }

    protected function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $reader = new \XMLReader();
        $reader->XML($xml);
        $strings = [];
        $text = '';
        $inside = false;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $inside = true;
                $text = '';
            } elseif ($inside && $reader->nodeType === \XMLReader::TEXT) {
                $text .= $reader->value;
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $text;
                $inside = false;
            }
        }
        $reader->close();

        return $strings;
    }

    protected function xlsxSheetPath(ZipArchive $zip, string $type): string
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $preferred = $this->isPaymentImportType($type)
            ? ['customer deposit payment', 'loan payment', 'monthly payment', 'monthly payments', 'payment']
            : ['full loan information', 'loan information', 'loan'];
        foreach ($workbook->sheets->sheet as $sheet) {
            $name = strtolower((string) $sheet['name']);
            foreach ($preferred as $needle) {
                if (strpos($name, $needle) !== false) {
                    $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $target = $relMap[(string) $attributes['id']] ?? '';
                    $path = $this->xlsxNormalizeTargetPath($target);
                    if ($zip->locateName($path) !== false) {
                        return $path;
                    }
                }
            }
        }

        $first = $workbook->sheets->sheet[0];
        $attributes = $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $target = $relMap[(string) $attributes['id']] ?? 'worksheets/sheet1.xml';
        $path = $this->xlsxNormalizeTargetPath($target);

        return $zip->locateName($path) !== false ? $path : $this->xlsxFirstWorksheetPath($zip);
    }

    protected function xlsxNormalizeTargetPath(string $target): string
    {
        $target = str_replace('\\', '/', trim($target));
        $target = ltrim($target, '/');
        $path = strpos($target, 'xl/') === 0 ? $target : 'xl/'.$target;
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    protected function xlsxFirstWorksheetPath(ZipArchive $zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                return $name;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    protected function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/([A-Z]+)/', $cellRef, $matches);
        $letters = $matches[1] ?? 'A';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function csvContent(array $columns, array $example): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        fputcsv($handle, $example);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    protected function normalizeHeader($header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = trim($header);
        $aliases = [
            'សាខា' => 'location_name',
            'លេខវិក័យបត្រ' => 'loan_number',
            'វិក័យប័ត្រ' => 'loan_number',
            'ថ្ងៃខែខ្ចីប្រាក់' => 'loan_date',
            'កាលបរិច្ឆេទ' => 'paid_date',
            'ឈ្មោះ' => 'customer_name',
            'ឈ្មោះអតិថិជន' => 'customer_name',
            'លេខទូរស័ព្ទ' => 'customer_phone',
            'លេខអត្តសញ្ញាណ' => 'id_number',
            'ភូមិ/បុរី' => 'village',
            'ឃុំ/សង្កាត់' => 'commune',
            'ស្រុក/ខណ្ឌ' => 'district',
            'ខេត្ត/ក្រុង' => 'province',
            'ឈ្មោះទំនិញ' => 'product_name',
            'ឈ្មោះទំនិញ-Products' => 'product_name',
            'ចំនួន' => 'qty',
            'តម្លៃរាយ' => 'unit_price',
            'សរុប' => 'amount',
            'ជាលុយសុទ្ធ' => 'down_payment_cash',
            'តាមធានាគា' => 'down_payment_bank',
            'បង់ផ្ដាច់' => 'payoff_amount',
            'payoff' => 'payoff_amount',
            'payoff_amount' => 'payoff_amount',
            'បង់-លុយសុទ្ធ' => 'cash_amount',
            'cash_amount' => 'cash_amount',
            'bank_amount' => 'bank_amount',
            'បង់-តាមធនាគា' => 'bank_amount',
            'តាមរយៈ' => 'payment_method',
            'ប្រាក់កម្ចី' => 'principal_amount',
            'ការប្រាក់សរុប' => 'interest_amount',
            'លុយសរុប' => 'total_amount',
            'ថ្ងៃខែសងប្រាក់
លើកទី1' => 'first_due_date',
            'ចំនួនខែ' => 'installment_count',
            'ចំនួនខែត្រូវបង់' => 'installment_count',
            'Number of Month' => 'installment_no',
            'ការប្រាក់' => 'interest_rate',
            ' ប្រាក់ដើម
ប្រចាំខែ' => 'monthly_principal',
            ' ការប្រាក់
ប្រចាំខែ' => 'monthly_interest',
            'បង់ប្រចាំខែ' => 'monthly_payment',
            'ពិន័យ' => 'penalty_amount',
            'ផ្សេងៗ' => 'note',
            'ឈ្មោះអ្នកធានា' => 'guarantor_name',
            'អត្តសញ្ញាណធានា' => 'guarantor_id_number',
            'លេខទូរស័ព្ទធានា' => 'guarantor_phone',
            'លេខប្រតិបត្តិ' => 'reference_number',
            'Name' => 'collector_name',
            'Key' => 'external_key',
            'loan_id' => 'loan_id',
            'loan_invoice' => 'loan_number',
            'loan_number' => 'loan_number',
            'sale_id' => 'sale_id',
            'invoice_no' => 'invoice_no',
            'sale_date' => 'sale_date',
            'location_id' => 'location_id',
            'location_name' => 'location_name',
            'customer_id' => 'customer_id',
            'customer_code' => 'customer_code',
            'customer_name' => 'customer_name',
            'customer_group' => 'customer_group',
            'phone' => 'customer_phone',
            'alternate_phone' => 'alternate_phone',
            'national_id' => 'national_id',
            'occupation' => 'occupation',
            'employer_name' => 'employer_name',
            'employer_phone' => 'employer_phone',
            'guarantor_national_id' => 'guarantor_national_id',
            'guarantor_address' => 'guarantor_address',
            'guarantor_relationship' => 'guarantor_relationship',
            'product_id' => 'product_id',
            'sku' => 'sku',
            'brand' => 'brand',
            'category' => 'category',
            'imei' => 'imei_or_serial',
            'serial_number' => 'serial_number',
            'quantity' => 'qty',
            'total_price' => 'product_price',
            'duration_months' => 'duration_months',
            'payment_date' => 'paid_date',
            'payment_method' => 'payment_method',
            'reference_no' => 'reference_number',
            'received_by' => 'received_by',
            'installment_no' => 'installment_no',
            'principal' => 'principal_amount',
            'interest' => 'interest_amount',
            'total' => 'schedule_amount',
            'paid_date' => 'paid_date',
        ];

        if (isset($aliases[$header])) {
            return $aliases[$header];
        }

        return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $header), '_'));
    }

    protected function combineRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    protected function isEmptyRow(array $row): bool
    {
        return trim(implode('', $row)) === '';
    }

    protected function cleanImportDataRows(array $rows): array
    {
        return array_values(array_filter($rows, function ($row) {
            $row = (array) $row;
            if ($this->isEmptyRow($row)) {
                return false;
            }

            $firstCell = strtolower(trim((string) ($row[0] ?? '')));

            return ! in_array($firstCell, ['required', 'optional', 'notes'], true);
        }));
    }

    protected function templateDefinitions(): array
    {
        return [
            'customers' => [
                'columns' => ['id', 'customer_code', 'customer_name', 'khmer_name', 'customer_group', 'phone', 'alternate_phone', 'email', 'telegram', 'facebook', 'national_id', 'id_number', 'address', 'province', 'district', 'commune', 'village', 'gender', 'date_of_birth', 'family_contact_name', 'family_contact_phone', 'spouse_name', 'spouse_phone', 'occupation', 'workplace', 'employer_name', 'employer_phone', 'monthly_income', 'customer_type', 'status', 'blacklist_status', 'blacklist_reason', 'note'],
                'required' => ['customer_name or phone'],
                'optional' => ['id', 'customer_code', 'khmer_name', 'customer_group', 'alternate_phone', 'email', 'telegram', 'facebook', 'national_id', 'id_number', 'address', 'province', 'district', 'commune', 'village', 'gender', 'date_of_birth', 'family_contact_name', 'family_contact_phone', 'spouse_name', 'spouse_phone', 'occupation', 'workplace', 'employer_name', 'employer_phone', 'monthly_income', 'customer_type', 'status', 'blacklist_status', 'blacklist_reason', 'note'],
                'example' => ['', 'CUS-0001', 'Sok Dara', 'សុខ ដារ៉ា', 'VIP', '012345678', '011222333', 'sok@example.com', '', '', '010101234', '010101234', 'Phnom Penh', '', '', '', '', '', '', '', '', '', '', 'Seller', 'ABC Shop', 'ABC Shop', '023555111', '500.00', 'retail', 'active', '0', '', 'Imported customer'],
                'notes' => 'If customer_code or phone already exists, the importer updates that customer. Otherwise it creates a new one.',
            ],
            'loans' => [
                'columns' => [
                    'loan_invoice', 'sale_id', 'invoice_no', 'sale_date', 'location_id', 'location_name',
                    'customer_id', 'customer_code', 'customer_name', 'customer_group', 'phone',
                    'alternate_phone', 'email', 'national_id', 'address', 'occupation', 'employer_name',
                    'employer_phone', 'guarantor_name', 'guarantor_phone', 'guarantor_national_id',
                    'guarantor_address', 'guarantor_relationship', 'product_id', 'product_name', 'sku',
                    'brand', 'category', 'imei', 'serial_number', 'quantity', 'unit_price', 'total_price',
                    'principal_amount', 'down_payment', 'payment_type', 'financed_amount', 'interest_rate', 'interest_type',
                    'duration_months', 'payment_frequency', 'first_due_date', 'penalty_type',
                    'penalty_amount', 'paid_amount', 'balance_amount', 'status', 'collection_status',
                    'collector_name', 'note',
                ],
                'required' => ['customer_name', 'sale_date', 'principal_amount', 'duration_months', 'interest_rate', 'payment_frequency', 'first_due_date'],
                'optional' => ['loan_invoice', 'phone', 'product_name', 'imei', 'customer_group', 'national_id', 'payment_type', 'guarantor fields', 'penalty_type', 'penalty_amount', 'paid_amount', 'balance_amount', 'status', 'collection_status', 'collector_name', 'note'],
                'example' => ['LN-0001', '1001', 'SALE-0001', now()->toDateString(), 'BL0001', 'Phnom Penh', '', 'CUS-0001', 'Sok Dara', 'VIP', '012345678', '011222333', 'sok@example.com', '010101234', 'Phnom Penh', 'Seller', 'ABC Shop', '023555111', 'Chan Sophea', '011222333', '020202345', 'Phnom Penh', 'Brother', '101', 'iPhone 12 Pro Max', 'SKU-IPH12', 'Apple', 'Phone', '356789123456789', 'SN123456', '1', '500.00', '500.00', '500.00', '200.00', 'loan', '300.00', '4.00', 'flat', '12', 'monthly', now()->addMonth()->toDateString(), 'fixed', '0.00', 'Collector One', 'Imported loan migration row'],
                'notes' => 'Imports or updates a complete loan with customer, guarantor, product snapshot, schedules, and down-payment summary. Use paid_amount, balance_amount, and status to correct paid-off/closed accounts. payment_type controls the imported initial/down payment; use loan for Customer Deposit Payments. The importer accepts loan_invoice as loan_number and duration_months as installment_count.',
            ],
            'schedules' => [
                'columns' => ['loan_invoice', 'loan_id', 'installment_no', 'due_date', 'principal', 'interest', 'total', 'paid_amount', 'balance_amount', 'status', 'paid_date'],
                'required' => ['loan_invoice or loan_id', 'installment_no', 'due_date', 'principal', 'interest', 'total'],
                'optional' => ['paid_amount', 'balance_amount', 'status', 'paid_date'],
                'example' => ['LN-0001', '', '1', now()->addMonth()->toDateString(), '50.00', '5.00', '55.00', '0.00', '55.00', 'unpaid', ''],
                'notes' => 'Updates an existing loan schedule with same loan and installment_no when found, otherwise creates one.',
            ],
            'payments' => [
                'columns' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
                'required' => ['loan_invoice', 'payment_date', 'amount'],
                'optional' => ['cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
                'example' => ['KY-000001', now()->toDateString(), '55.00', '0.00', '50.00', '5.00', 'Bank', 'monthly', '1', '', 'USD', '1', '0.00', '0.00', 'PAY-KY-000001-'.now()->format('Ymd').'-M1-CASH', 'confirmed', 'Admin', 'Monthly installment payment'],
                'notes' => 'If schedule_id is empty, payment is applied to the oldest unpaid schedule for the loan. If amount is blank, it is calculated from cash_amount + bank_amount. payment_type: monthly or loan (payoff). installment_no targets a specific schedule row. penalty_amount and discount_amount adjust the schedule balance. Duplicate payments match by reference_number when provided, otherwise by loan + schedule + payment_type + paid_date + amount + payment_method.',
            ],
            'customer_deposit_payments' => [
                'columns' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
                'required' => ['loan_invoice', 'payment_date', 'amount'],
                'optional' => ['cash_amount', 'bank_amount', 'payment_method', 'payment_type', 'currency', 'exchange_rate', 'reference_no', 'status', 'received_by', 'note'],
                'example' => ['KY-000001', now()->toDateString(), '200.00', '200.00', '0.00', '0.00', 'Cash', 'loan', '', '', 'USD', '1', '0.00', '0.00', 'DEP-KY-000001-'.now()->format('Ymd'), 'confirmed', 'Admin', 'Customer deposit payment'],
                'notes' => 'Imports Customer Deposit Payments. payment_type defaults to loan for this template. These payments are stored as loan/down-payment records, not monthly collection rows.',
            ],
            'guarantors' => [
                'columns' => ['loan_number', 'loan_id', 'customer_id', 'name', 'phone', 'relation', 'address', 'id_number', 'status', 'note'],
                'required' => ['loan_number or loan_id or customer_id', 'name'],
                'optional' => ['phone', 'relation', 'address', 'id_number', 'status', 'note'],
                'example' => ['LN-0001', '', '', 'Chan Sophea', '011222333', 'Brother', 'Phnom Penh', '020202345', 'active', 'Imported guarantor'],
                'notes' => 'Links guarantor data to a loan/customer when the matching columns exist in your loan database.',
            ],
            'imei' => [
                'columns' => ['loan_number', 'loan_id', 'product_name', 'imei', 'serial_no', 'qty', 'unit_price', 'status', 'note'],
                'required' => ['loan_number or loan_id', 'imei or serial_no'],
                'optional' => ['product_name', 'qty', 'unit_price', 'status', 'note'],
                'example' => ['LN-0001', '', 'iPhone 12 Pro Max', '356789123456789', 'SN123456', '1', '500.00', 'active', 'Imported item'],
                'notes' => 'Stores product item identifiers in loan_product_items when available, falling back to loan_items if needed.',
            ],
            'collection_assignments' => [
                'columns' => ['loan_number', 'loan_id', 'assigned_collector_id', 'assigned_collection_team', 'collection_status', 'risk_level', 'next_followup_at', 'ptp_date', 'ptp_amount', 'note'],
                'required' => ['loan_number or loan_id'],
                'optional' => ['assigned_collector_id', 'assigned_collection_team', 'collection_status', 'risk_level', 'next_followup_at', 'ptp_date', 'ptp_amount', 'note'],
                'example' => ['LN-0001', '', '5', 'Team A', 'due_today', 'medium', now()->addDays(2)->toDateString(), now()->addDays(5)->toDateString(), '55.00', 'Imported assignment'],
                'notes' => 'Updates collection workflow fields on the matching loan only; it does not create new loans.',
            ],
        ];
    }

    protected function normalizeImportRow(string $type, array $row): array
    {
        if ($this->isPaymentImportType($type)) {
            return $this->normalizePaymentRow($row, $type);
        }
        if ($this->isFullLoanUpdateType($type)) {
            return $this->normalizeFullLoanUpdateRow($row);
        }
        if ($this->isLoanImportType($type)) {
            return $this->normalizeLoanRow($row);
        }

        $loanId = $this->resolveLoanId($row);
        $normalized = $row;
        $normalized['loan_id'] = $loanId ?: (int) ($row['loan_id'] ?? 0);

        if ($type === 'customers') {
            $normalized['id'] = (int) ($row['id'] ?? $row['customer_id'] ?? 0) ?: null;
            $normalized['name'] = $row['name'] ?? $row['customer_name'] ?? '';
            $normalized['khmer_name'] = $row['khmer_name'] ?? null;
            $normalized['phone'] = $row['phone'] ?? $row['customer_phone'] ?? '';
            $normalized['alternate_phone'] = $row['alternate_phone'] ?? null;
            $normalized['customer_group'] = $row['customer_group'] ?? null;
            $normalized['status'] = $row['status'] ?? 'active';
            $normalized['date_of_birth'] = $this->date($row['date_of_birth'] ?? $row['dob'] ?? null);
            $normalized['id_number'] = $row['id_number'] ?? $row['id_card_number'] ?? $row['national_id'] ?? null;
            $normalized['national_id'] = $normalized['id_number'];
            $normalized['telegram'] = $row['telegram'] ?? null;
            $normalized['facebook'] = $row['facebook'] ?? null;
            $normalized['province'] = $row['province'] ?? null;
            $normalized['district'] = $row['district'] ?? null;
            $normalized['commune'] = $row['commune'] ?? null;
            $normalized['village'] = $row['village'] ?? null;
            $normalized['family_contact_name'] = $row['family_contact_name'] ?? null;
            $normalized['family_contact_phone'] = $row['family_contact_phone'] ?? null;
            $normalized['spouse_name'] = $row['spouse_name'] ?? null;
            $normalized['spouse_phone'] = $row['spouse_phone'] ?? null;
            $normalized['occupation'] = $row['occupation'] ?? null;
            $normalized['workplace'] = $row['workplace'] ?? null;
            $normalized['employer_name'] = $row['employer_name'] ?? null;
            $normalized['employer_phone'] = $row['employer_phone'] ?? null;
            $normalized['monthly_income'] = $this->decimal($row['monthly_income'] ?? 0) ?: null;
            $normalized['customer_type'] = $row['customer_type'] ?? null;
            $normalized['blacklist_status'] = $row['blacklist_status'] ?? null;
            $normalized['blacklist_reason'] = $row['blacklist_reason'] ?? null;
        } elseif ($type === 'schedules') {
            $principal = $this->decimal($row['principal_amount'] ?? $row['principal_due'] ?? $row['principal'] ?? 0);
            $interest = $this->decimal($row['interest_amount'] ?? $row['interest_due'] ?? $row['interest'] ?? 0);
            $penalty = $this->decimal($row['penalty_amount'] ?? $row['penalty_due'] ?? 0);
            $amount = $this->decimal($row['schedule_amount'] ?? $row['amount_due'] ?? $row['total'] ?? ($principal + $interest + $penalty));
            $paid = $this->decimal($row['paid_amount'] ?? $row['amount_paid'] ?? 0);
            $balance = array_key_exists('balance_amount', $row) && $row['balance_amount'] !== ''
                ? $this->decimal($row['balance_amount'])
                : $this->decimal($row['amount_balance'] ?? max(0, $amount - $paid));
            $normalized = array_merge($normalized, [
                'installment_no' => (int) ($row['installment_no'] ?? 0),
                'due_date' => $this->date($row['due_date'] ?? null),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'penalty_amount' => $penalty,
                'schedule_amount' => $amount,
                'paid_amount' => $paid,
                'balance_amount' => max(0, $balance),
                'status' => $row['status'] ?? ($balance <= 0 && $amount > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')),
                'paid_date' => $this->date($row['paid_date'] ?? null),
            ]);
        } elseif ($type === 'guarantors') {
            $normalized['customer_id'] = (int) ($row['customer_id'] ?? 0);
            $normalized['name'] = $row['name'] ?? $row['guarantor_name'] ?? '';
            $normalized['phone'] = $row['phone'] ?? $row['guarantor_phone'] ?? '';
            $normalized['status'] = $row['status'] ?? 'active';
        } elseif ($type === 'imei') {
            $normalized['product_name'] = $row['product_name'] ?? '';
            $normalized['imei'] = $row['imei'] ?? $row['imei_or_serial'] ?? '';
            $normalized['serial_no'] = $row['serial_no'] ?? $row['serial'] ?? $row['imei_or_serial'] ?? '';
            $normalized['qty'] = max(1, (float) ($row['qty'] ?? $row['quantity'] ?? 1));
            $normalized['unit_price'] = $this->decimal($row['unit_price'] ?? 0);
            $normalized['line_total'] = $this->decimal($row['line_total'] ?? ($normalized['qty'] * $normalized['unit_price']));
            $normalized['status'] = $row['status'] ?? 'active';
        } elseif ($type === 'collection_assignments') {
            $normalized['assigned_collector_id'] = (int) ($row['assigned_collector_id'] ?? 0) ?: null;
            $normalized['ptp_amount'] = $this->decimal($row['ptp_amount'] ?? 0);
            $normalized['next_followup_at'] = $this->date($row['next_followup_at'] ?? null);
            $normalized['ptp_date'] = $this->date($row['ptp_date'] ?? null);
        }

        return $normalized;
    }

    protected function validateImportRow(string $type, array $row, string $duplicateMode = 'skip'): array
    {
        if ($this->isPaymentImportType($type)) {
            return $this->validatePaymentRow($row);
        }
        if ($this->isFullLoanUpdateType($type)) {
            $errors = [];
            if (empty($row['loan_id']) && empty($row['loan_number'])) {
                $errors[] = 'loan_invoice or loan_id is required';
            }
            foreach (['paid_amount', 'balance_amount', 'deposit_amount', 'collection_amount'] as $amountField) {
                if (isset($row[$amountField]) && $row[$amountField] < 0) {
                    $errors[] = $amountField.' must be 0 or greater';
                }
            }

            return $errors;
        }
        if ($type === 'active_loans') {
            $errors = [];
            if (empty($row['loan_number']) && empty($row['loan_id'])) {
                $errors[] = 'loan_number or id is required';
            }
            if (($row['paid_amount'] ?? 0) < 0) {
                $errors[] = 'paid_amount must be 0 or greater';
            }
            if (($row['balance_amount'] ?? 0) < 0) {
                $errors[] = 'balance_amount must be 0 or greater';
            }

            return $errors;
        }
        if ($this->isLoanImportType($type)) {
            return $this->validateLoanRow($row, $duplicateMode);
        }

        $errors = [];
        if ($type !== 'customers' && empty($row['loan_id']) && empty($row['customer_id'])) {
            $errors[] = 'loan_number, loan_id, or customer_id is required';
        }
        if ($type === 'customers' && empty($row['name']) && empty($row['phone'])) {
            $errors[] = 'name or phone is required';
        }
        if ($type === 'schedules') {
            if (empty($row['installment_no'])) $errors[] = 'installment_no is required';
            if (empty($row['due_date'])) $errors[] = 'due_date is required';
            if (($row['schedule_amount'] ?? 0) <= 0) $errors[] = 'schedule_amount must be greater than 0';
            if (! empty($row['paid_date']) && empty($this->date($row['paid_date']))) $errors[] = 'paid_date format is invalid';
        }
        if ($type === 'guarantors' && empty($row['name'])) {
            $errors[] = 'name is required';
        }
        if ($type === 'imei' && empty($row['imei']) && empty($row['serial_no'])) {
            $errors[] = 'imei or serial_no is required';
        }

        return $errors;
    }

    protected function isValidPhone(?string $phone): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return false;
        }

        return (bool) preg_match('/^\+?[0-9\s().-]{6,25}$/', $phone);
    }

    protected function imeiExists(string $imei, ?string $loanNumber = null): bool
    {
        $imei = trim($imei);
        if ($imei === '') {
            return false;
        }

        $loanId = null;
        if ($loanNumber && Schema::connection($this->connection)->hasTable('loans')) {
            $loanId = DB::connection($this->connection)->table('loans')->where('loan_number', $loanNumber)->value('id');
        }

        foreach (['loans' => ['imei_snapshot'], 'loan_items' => ['imei_snapshot', 'serial_number_snapshot', 'serial_number'], 'loan_product_items' => ['imei', 'serial_no']] as $table => $columns) {
            if (! Schema::connection($this->connection)->hasTable($table)) {
                continue;
            }
            $available = array_values(array_filter($columns, fn ($column) => $this->hasColumn($table, $column)));
            if (empty($available)) {
                continue;
            }

            $query = DB::connection($this->connection)->table($table)->where(function ($where) use ($available, $imei) {
                foreach ($available as $column) {
                    $where->orWhere($column, $imei);
                }
            });

            if ($loanId && $this->hasColumn($table, 'loan_id')) {
                $query->where('loan_id', '!=', $loanId);
            } elseif ($loanId && $table === 'loans') {
                $query->where('id', '!=', $loanId);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeFullLoanUpdateRow(array $row): array
    {
        $loanNumber = trim((string) ($row['loan_number'] ?? $row['loan_invoice'] ?? ''));
        $loanId = (int) ($row['loan_id'] ?? $row['id'] ?? 0);
        if ($loanId <= 0 && $loanNumber !== '' && Schema::connection($this->connection)->hasTable('loans')) {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $loanNumber)->value('id');
        }

        return [
            'loan_id' => $loanId ?: null,
            'loan_number' => $loanNumber,
            'invoice_no' => $row['invoice_no'] ?? $row['source_invoice_no'] ?? null,
            'loan_date' => $this->date($row['loan_date'] ?? $row['sale_date'] ?? null),
            'status' => $row['status'] ?? $row['loan_status'] ?? null,
            'customer_id' => (int) ($row['customer_id'] ?? 0) ?: null,
            'customer_code' => $row['customer_code'] ?? null,
            'customer_name' => $row['customer_name'] ?? $row['customer_name_snapshot'] ?? $row['name'] ?? null,
            'customer_phone' => $row['customer_phone'] ?? $row['phone'] ?? null,
            'customer_address' => $row['customer_address'] ?? $row['address'] ?? null,
            'national_id' => $row['national_id'] ?? $row['id_number'] ?? $row['id_card_number'] ?? null,
            'product_name' => $row['product_name'] ?? $row['product_name_snapshot'] ?? null,
            'sku' => $row['sku'] ?? $row['sku_snapshot'] ?? null,
            'imei' => $row['imei'] ?? $row['imei_snapshot'] ?? null,
            'serial_number' => $row['serial_number'] ?? $row['serial_no'] ?? null,
            'principal_amount' => $this->decimal($row['principal_amount'] ?? 0),
            'total_amount' => $this->decimal($row['total_amount'] ?? 0),
            'paid_amount' => $this->decimal($row['paid_amount'] ?? $row['total_paid'] ?? 0),
            'balance_amount' => $this->decimal($row['balance_amount'] ?? 0),
            'deposit_amount' => $this->decimal($row['deposit_amount'] ?? $row['customer_deposit_amount'] ?? 0),
            'deposit_date' => $this->date($row['deposit_date'] ?? $row['customer_deposit_date'] ?? null),
            'deposit_method' => $row['deposit_method'] ?? $row['customer_deposit_method'] ?? 'Cash',
            'deposit_reference_no' => $row['deposit_reference_no'] ?? $row['customer_deposit_reference_no'] ?? null,
            'deposit_status' => $row['deposit_status'] ?? 'confirmed',
            'collection_amount' => $this->decimal($row['collection_amount'] ?? $row['payment_collection_amount'] ?? 0),
            'collection_date' => $this->date($row['collection_date'] ?? $row['payment_collection_date'] ?? null),
            'collection_method' => $row['collection_method'] ?? $row['payment_collection_method'] ?? 'Cash',
            'collection_reference_no' => $row['collection_reference_no'] ?? $row['payment_collection_reference_no'] ?? null,
            'collection_status' => $row['collection_status'] ?? 'confirmed',
            'note' => $row['note'] ?? null,
        ];
    }

    protected function normalizeLoanRow(array $row): array
    {
        $qty = max(1, $this->decimal($row['qty'] ?? $row['quantity'] ?? 1));
        $unitPrice = $this->decimal($row['unit_price'] ?? 0);
        $principal = $this->decimal($row['principal_amount'] ?? $row['product_price'] ?? 0);
        if ($principal <= 0 && $unitPrice > 0) {
            $principal = round($unitPrice * $qty, 2);
        }
        $interestRate = $this->importInterestRate($row['interest_rate'] ?? 0);
        $durationMonths = max(1, (int) ($row['duration_months'] ?? $row['installment_count'] ?? $row['total_installment_months'] ?? 1));
        $downPaymentCash = $this->decimal($row['down_payment_cash'] ?? 0);
        $downPaymentBank = $this->decimal($row['down_payment_bank'] ?? 0);
        $downPayment = $this->decimal($row['down_payment'] ?? ($downPaymentCash + $downPaymentBank));
        $financedAmount = $this->decimal($row['financed_amount'] ?? max(0, $principal - $downPayment));
        $interestBase = $financedAmount > 0 ? $financedAmount : $principal;
        $interest = $this->decimal($row['interest_amount'] ?? $row['total_interest'] ?? 0);
        if ($interest <= 0 && $interestRate > 0 && $interestBase > 0) {
            $interest = round($interestBase * ($interestRate / 100) * $durationMonths, 2);
        }
        $total = $this->decimal($row['total_amount'] ?? ($principal + $interest));
        $paid = array_key_exists('paid_amount', $row) && $row['paid_amount'] !== ''
            ? $this->decimal($row['paid_amount'])
            : $this->decimal($row['total_paid'] ?? $downPayment);
        $balance = array_key_exists('balance_amount', $row) && $row['balance_amount'] !== ''
            ? $this->decimal($row['balance_amount'])
            : max(0, ($total > 0 ? $total : ($principal + $interest)) - $paid);
        $installments = $durationMonths;
        $location = $this->resolveImportLocation($row);

        return [
            'loan_id' => (int) ($row['loan_id'] ?? $row['id'] ?? 0) ?: null,
            'loan_number' => $row['loan_number'] ?? $row['loan_invoice'] ?? $this->nextLoanNumber(),
            'sale_id' => (int) ($row['sale_id'] ?? 0) ?: null,
            'invoice_no' => $row['invoice_no'] ?? $row['source_invoice_no'] ?? null,
            'sale_date' => $this->date($row['sale_date'] ?? $row['source_created_at'] ?? $row['loan_date'] ?? null),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_code' => $row['customer_code'] ?? null,
            'customer_name' => $row['customer_name'] ?? $row['customer_name_snapshot'] ?? $row['name'] ?? '',
            'customer_group' => $row['customer_group'] ?? null,
            'khmer_name' => $row['khmer_name'] ?? null,
            'customer_phone' => $row['customer_phone'] ?? $row['customer_phone_snapshot'] ?? $row['phone'] ?? '',
            'alternate_phone' => $row['alternate_phone'] ?? null,
            'email' => $row['email'] ?? null,
            'telegram' => $row['telegram'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'id_number' => $row['id_number'] ?? $row['id_card_number'] ?? $row['national_id'] ?? null,
            'national_id' => $row['national_id'] ?? $row['id_number'] ?? $row['id_card_number'] ?? null,
            'gender' => $row['gender'] ?? null,
            'date_of_birth' => $this->date($row['date_of_birth'] ?? $row['dob'] ?? null),
            'address' => $row['address'] ?? null,
            'province' => $row['province'] ?? null,
            'district' => $row['district'] ?? null,
            'commune' => $row['commune'] ?? null,
            'village' => $row['village'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'family_contact_name' => $row['family_contact_name'] ?? null,
            'family_contact_phone' => $row['family_contact_phone'] ?? null,
            'spouse_name' => $row['spouse_name'] ?? null,
            'spouse_phone' => $row['spouse_phone'] ?? null,
            'workplace' => $row['workplace'] ?? $row['employer_name'] ?? null,
            'occupation' => $row['occupation'] ?? null,
            'employer_name' => $row['employer_name'] ?? null,
            'employer_phone' => $row['employer_phone'] ?? null,
            'monthly_income' => $this->decimal($row['monthly_income'] ?? 0) ?: null,
            'customer_type' => $row['customer_type'] ?? null,
            'business_location_id' => $location['id'],
            'location_name' => $location['name'],
            'product_id' => (int) ($row['product_id'] ?? 0) ?: null,
            'product_name' => $row['product_name'] ?? '',
            'sku' => $row['sku'] ?? null,
            'brand' => $row['brand'] ?? null,
            'category' => $row['category'] ?? null,
            'imei_or_serial' => $row['imei_or_serial'] ?? $row['imei'] ?? $row['serial'] ?? '',
            'serial_number' => $row['serial_number'] ?? $row['serial_no'] ?? $row['serial'] ?? null,
            'qty' => $qty,
            'unit_price' => $unitPrice > 0 ? $unitPrice : $principal,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'interest_rate' => $interestRate,
            'interest_type' => strtolower((string) ($row['interest_type'] ?? 'flat')) === 'reducing' ? 'reducing' : 'flat',
            'duration_months' => $durationMonths,
            'total_amount' => $total > 0 ? $total : ($principal + $interest),
            'down_payment' => $downPayment,
            'financed_amount' => $financedAmount,
            'down_payment_cash' => $downPaymentCash,
            'down_payment_bank' => $downPaymentBank,
            'paid_amount' => $paid,
            'balance_amount' => array_key_exists('balance_amount', $row) ? $balance : $this->decimal($row['outstanding_total'] ?? $row['remaining_balance'] ?? $row['balance'] ?? $balance),
            'initial_payment_method' => $row['payment_method'] ?? $row['method'] ?? null,
            'initial_payment_type' => $this->normalizePaymentType($row['payment_type'] ?? 'loan'),
            'initial_payment_date' => $this->date($row['paid_date'] ?? $row['payment_date'] ?? $row['loan_date'] ?? null),
            'initial_payment_reference' => $row['reference_number'] ?? $row['payment_ref_no'] ?? null,
            'installment_count' => $installments,
            'payment_frequency' => $row['payment_frequency'] ?? 'monthly',
            'loan_date' => $this->date($row['loan_date'] ?? $row['sale_date'] ?? null),
            'first_due_date' => $this->date($row['first_due_date'] ?? null),
            'maturity_date' => $this->date($row['maturity_date'] ?? null),
            'status' => $row['status'] ?? $row['loan_status'] ?? 'active',
            'currency' => $row['currency'] ?? 'USD',
            'staff_id' => (int) ($row['staff_id'] ?? 0) ?: null,
            'staff_name_snapshot' => $row['staff_name_snapshot'] ?? $row['staff_name'] ?? null,
            'collector_id' => (int) ($row['collector_id'] ?? 0) ?: null,
            'collector_name_snapshot' => $row['collector_name_snapshot'] ?? $row['collector_name'] ?? null,
            'source_invoice_no' => $row['source_invoice_no'] ?? $row['invoice_no'] ?? null,
            'collection_status' => $row['collection_status'] ?? null,
            'risk_level' => $row['risk_level'] ?? null,
            'assigned_collector_id' => (int) ($row['assigned_collector_id'] ?? 0) ?: null,
            'assigned_collection_team' => $row['assigned_collection_team'] ?? null,
            'days_past_due' => (int) ($row['days_past_due'] ?? 0),
            'overdue_bucket' => $row['overdue_bucket'] ?? null,
            'next_followup_at' => $this->date($row['next_followup_at'] ?? null),
            'ptp_date' => $this->date($row['ptp_date'] ?? null),
            'ptp_amount' => $this->decimal($row['ptp_amount'] ?? 0) ?: null,
            'monthly_principal' => $this->decimal($row['monthly_principal'] ?? 0),
            'monthly_interest' => $this->decimal($row['monthly_interest'] ?? 0),
            'monthly_payment' => $this->decimal($row['monthly_payment'] ?? 0),
            'penalty_amount' => $this->decimal($row['penalty_amount'] ?? 0),
            'penalty_type' => $row['penalty_type'] ?? null,
            'guarantor_name' => $row['guarantor_name'] ?? null,
            'guarantor_phone' => $row['guarantor_phone'] ?? null,
            'guarantor_national_id' => $row['guarantor_national_id'] ?? $row['guarantor_id_number'] ?? null,
            'guarantor_address' => $row['guarantor_address'] ?? null,
            'guarantor_relationship' => $row['guarantor_relationship'] ?? $row['guarantor_relation'] ?? null,
            'note' => $row['note'] ?? null,
            'raw_import_row' => $row,
        ];
    }

    protected function normalizePaymentRow(array $row, string $type = 'payments'): array
    {
        $loanId = (int) ($row['loan_id'] ?? 0);
        $loanNumber = trim((string) ($row['loan_number'] ?? $row['loan_invoice'] ?? ''));
        if ($loanId <= 0 && $loanNumber !== '') {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $loanNumber)->value('id');
        }
        $scheduleId = (int) ($row['schedule_id'] ?? 0) ?: null;
        if (! $scheduleId && $loanId > 0 && ! empty($row['installment_no']) && Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            $scheduleId = (int) DB::connection($this->connection)
                ->table('loan_payment_schedules')
                ->where('loan_id', $loanId)
                ->where('installment_no', (int) $row['installment_no'])
                ->value('id') ?: null;
        }
        $amountInput = trim((string) ($row['amount'] ?? $row['paid_amount'] ?? ''));
        $amount = $this->decimal($amountInput);
        if ($amountInput === '') {
            $amount = $this->decimal($row['cash_amount'] ?? 0)
                + $this->decimal($row['bank_amount'] ?? 0);
        }
        $method = trim((string) ($row['payment_method'] ?? $row['method'] ?? $row['channel'] ?? ''));
        if ($method === '') {
            $method = $this->decimal($row['bank_amount'] ?? 0) > 0 ? 'Bank' : 'Cash';
        }
        $reference = trim((string) ($row['reference_number'] ?? $row['reference_no'] ?? $row['payment_ref_no'] ?? ''));
        $currency = trim((string) ($row['currency'] ?? ''));
        $exchangeRate = $this->decimal($row['exchange_rate'] ?? 1);

        $defaultPaymentType = in_array($type, ['customer_deposit_payments', 'loan_payments'], true) ? 'loan' : 'monthly';

        return [
            'loan_id' => $loanId,
            'loan_number' => $loanNumber,
            'schedule_id' => $scheduleId,
            'installment_no' => (int) ($row['installment_no'] ?? 0) ?: null,
            'due_date' => $this->date($row['due_date'] ?? null),
            'payment_type' => $this->normalizePaymentType($row['payment_type'] ?? $defaultPaymentType),
            'amount' => $amount,
            'paid_date' => $this->date($row['payment_date'] ?? $row['paid_date'] ?? $row['paid_at'] ?? null),
            'paid_at' => ! empty($row['paid_at']) && strtotime((string) $row['paid_at']) ? date('Y-m-d H:i:s', strtotime((string) $row['paid_at'])) : null,
            'payment_method' => $method,
            'currency' => $currency !== '' ? $currency : 'USD',
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1,
            'reference_number' => $reference !== '' ? $reference : null,
            'status' => $row['status'] ?? 'confirmed',
            'received_by' => trim((string) ($row['received_by'] ?? '')),
            'penalty_amount' => $this->decimal($row['penalty_amount'] ?? 0),
            'discount_amount' => $this->decimal($row['discount_amount'] ?? 0),
            'note' => $row['note'] ?? null,
        ];
    }

    protected function validateLoanRow(array $row, string $duplicateMode = 'skip'): array
    {
        $errors = [];
        if (empty($row['loan_number'])) $errors[] = 'loan_invoice is required';
        if (! empty($row['loan_number']) && $duplicateMode === 'skip' && DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->exists()) $errors[] = 'duplicate loan_invoice';
        if (empty($row['customer_name'])) $errors[] = 'customer_name is required';
        if (! empty($row['customer_phone']) && ! $this->isValidPhone($row['customer_phone'])) $errors[] = 'phone format is invalid';
        if (empty($row['sale_date'])) $errors[] = 'sale_date is required';
        if ($row['principal_amount'] <= 0) $errors[] = 'principal_amount must be greater than 0';
        if (($row['duration_months'] ?? 0) < 1 || ($row['duration_months'] ?? 0) > 360) $errors[] = 'duration_months must be between 1 and 360';
        if ($this->importInterestRate($row['interest_rate'] ?? 0) < 0) $errors[] = 'interest_rate must be positive';
        if (! in_array($row['payment_frequency'] ?? 'monthly', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'], true)) $errors[] = 'payment_frequency is invalid';
        if (empty($row['first_due_date'])) $errors[] = 'first_due_date is required';
        if (! in_array($row['interest_type'] ?? 'flat', ['flat', 'reducing'], true)) $errors[] = 'interest_type must be flat or reducing';

        return $errors;
    }

    protected function resolveImportLocation(array $row): array
    {
        $locationId = (int) ($row['business_location_id'] ?? 0);
        $locationKey = trim((string) ($row['location_id'] ?? $row['location_code'] ?? ''));
        $locationName = trim((string) ($row['location_name'] ?? $row['business_location_name_snapshot'] ?? ''));

        if (! Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return [
                'id' => $locationId > 0 ? $locationId : null,
                'name' => $locationName !== '' ? $locationName : null,
            ];
        }

        $query = DB::connection($this->connection)->table('loan_business_locations');
        if (Schema::connection($this->connection)->hasColumn('loan_business_locations', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $location = null;
        if ($locationKey !== '' && is_numeric($locationKey) && Schema::hasTable('business_locations') && Schema::hasColumn('business_locations', 'location_id') && $this->hasColumn('loan_business_locations', 'location_code')) {
            $mainLocationCode = DB::table('business_locations')
                ->where('id', (int) $locationKey)
                ->value('location_id');

            if (! empty($mainLocationCode)) {
                $location = (clone $query)->where('location_code', $mainLocationCode)->first();
            }
        }

        if ($locationId > 0) {
            $location = (clone $query)->where('id', $locationId)->first();
        }

        if (! $location && $locationKey !== '') {
            $location = (clone $query)->where(function ($where) use ($locationKey) {
                if (is_numeric($locationKey)) {
                    $where->orWhere('id', (int) $locationKey);
                    if ($this->hasColumn('loan_business_locations', 'main_location_id')) {
                        $where->orWhere('main_location_id', (int) $locationKey);
                    }
                }
                if ($this->hasColumn('loan_business_locations', 'location_code')) {
                    $where->orWhere('location_code', $locationKey);
                }
            })->first();
        }

        if (! $location && $locationName !== '') {
            $location = (clone $query)->where('name', $locationName)->first();
        }

        if (! $location && $locationKey !== '') {
            $location = $this->resolveMainBusinessLocationSnapshot($locationKey, $locationName);
        }

        return [
            'id' => $location ? (int) $location->id : ($locationId > 0 ? $locationId : null),
            'name' => $location ? (string) ($location->name ?? $locationName) : ($locationName !== '' ? $locationName : null),
        ];
    }

    protected function resolveMainBusinessLocationSnapshot(string $locationKey, string $locationName = '')
    {
        if (! Schema::hasTable('business_locations') || ! Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return null;
        }

        $canMatchMainLocation = is_numeric($locationKey)
            || Schema::hasColumn('business_locations', 'location_id')
            || ($locationName !== '' && Schema::hasColumn('business_locations', 'name'));
        if (! $canMatchMainLocation) {
            return null;
        }

        $query = DB::table('business_locations');
        if (Schema::hasColumn('business_locations', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $mainLocation = $query->where(function ($where) use ($locationKey, $locationName) {
            if (is_numeric($locationKey)) {
                $where->orWhere('id', (int) $locationKey);
            }
            if (Schema::hasColumn('business_locations', 'location_id')) {
                $where->orWhere('location_id', $locationKey);
            }
            if ($locationName !== '' && Schema::hasColumn('business_locations', 'name')) {
                $where->orWhere('name', $locationName);
            }
        })->first();

        if (! $mainLocation) {
            return null;
        }

        $canMatchLoanLocation = $this->hasColumn('loan_business_locations', 'main_location_id')
            || $this->hasColumn('loan_business_locations', 'location_code');
        if (! $canMatchLoanLocation) {
            return null;
        }

        $loanQuery = DB::connection($this->connection)->table('loan_business_locations');
        $existing = (clone $loanQuery)->where(function ($where) use ($mainLocation, $locationKey) {
            if ($this->hasColumn('loan_business_locations', 'main_location_id')) {
                $where->orWhere('main_location_id', (int) $mainLocation->id);
            }
            if ($this->hasColumn('loan_business_locations', 'location_code')) {
                $where->orWhere('location_code', $mainLocation->location_id ?? $locationKey);
            }
        })->first();

        $payload = $this->safeColumns('loan_business_locations', [
            'main_business_id' => $mainLocation->business_id ?? null,
            'main_location_id' => $mainLocation->id,
            'name' => $mainLocation->name ?? ($locationName !== '' ? $locationName : $locationKey),
            'location_code' => $mainLocation->location_id ?? $locationKey,
            'address' => $mainLocation->landmark ?? null,
            'phone' => $mainLocation->mobile ?? ($mainLocation->alternate_number ?? null),
            'invoice_scheme_id' => $mainLocation->invoice_scheme_id ?? null,
            'status' => 'active',
            'synced_at' => now(),
            'updated_at' => now(),
        ]);

        if ($existing) {
            DB::connection($this->connection)->table('loan_business_locations')->where('id', $existing->id)->update($payload);

            return DB::connection($this->connection)->table('loan_business_locations')->where('id', $existing->id)->first();
        }

        if ($this->hasColumn('loan_business_locations', 'created_at')) {
            $payload['created_at'] = now();
        }

        $id = (int) DB::connection($this->connection)->table('loan_business_locations')->insertGetId($payload);

        return DB::connection($this->connection)->table('loan_business_locations')->where('id', $id)->first();
    }

    protected function validatePaymentRow(array $row): array
    {
        $errors = [];
        if (empty($row['loan_id']) && empty($row['loan_number'])) {
            $errors[] = 'loan_invoice or loan_id is required';
        } elseif (empty($row['loan_id'])) {
            $errors[] = 'loan_invoice "'.$row['loan_number'].'" was not found in loans';
        } elseif (! DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->exists()) {
            $errors[] = 'loan_id '.$row['loan_id'].' was not found in loans';
        }
        if ($row['amount'] <= 0) $errors[] = 'amount must be greater than 0';
        if (empty($row['paid_date'])) $errors[] = 'paid_date is required';
        if (! in_array($row['payment_type'] ?? 'monthly', ['loan', 'monthly'], true)) $errors[] = 'payment_type must be loan or monthly';

        return $errors;
    }

    protected function normalizePaymentType($value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['loan', 'initial', 'down_payment', 'downpayment', 'deposit', 'customer_deposit', 'customer_deposit_payment'], true) ? 'loan' : 'monthly';
    }

    protected function storeLoan(array $row, string $duplicateMode = 'skip', ?int $existingLoanId = null): int
    {
        $customerId = $row['customer_id'] ?: $this->firstOrCreateCustomer($row);
        $interestRate = $this->importInterestRate($row['interest_rate'] ?? 0);
        if ($customerId > 0) {
            $this->updateImportedCustomer($customerId, $row);
        }

        $payload = $this->safeColumns('loans', [
            'loan_number' => $row['loan_number'],
            'sale_id' => $row['sale_id'],
            'invoice_no' => $row['invoice_no'],
            'sale_date' => $row['sale_date'],
            'customer_id' => $customerId,
            'business_location_id' => $row['business_location_id'],
            'business_location_name_snapshot' => $row['location_name'],
            'staff_id' => $row['staff_id'],
            'staff_name_snapshot' => $row['staff_name_snapshot'],
            'collector_id' => $row['collector_id'],
            'collector_name_snapshot' => $row['collector_name_snapshot'],
            'customer_name_snapshot' => $row['customer_name'],
            'customer_phone_snapshot' => $row['customer_phone'],
            'product_name_snapshot' => $row['product_name'],
            'imei_snapshot' => $row['imei_or_serial'],
            'source_invoice_no' => $row['source_invoice_no'],
            'source_transaction_id' => $row['sale_id'],
            'source_created_at' => $row['sale_date'],
            'principal_amount' => $row['principal_amount'],
            'interest_amount' => $row['interest_amount'],
            'interest_rate' => $interestRate,
            'interest_type' => $row['interest_type'],
            'duration_months' => $row['duration_months'],
            'total_amount' => $row['total_amount'],
            'paid_amount' => $row['paid_amount'],
            'balance_amount' => $row['balance_amount'],
            'down_payment' => $row['down_payment'],
            'financed_amount' => $row['financed_amount'],
            'installment_count' => $row['installment_count'],
            'payment_frequency' => $row['payment_frequency'],
            'loan_date' => $row['loan_date'],
            'first_due_date' => $row['first_due_date'],
            'maturity_date' => $row['maturity_date'],
            'status' => $row['status'],
            'currency' => $row['currency'],
            'penalty_type' => $row['penalty_type'],
            'penalty_amount' => $row['penalty_amount'],
            'collection_status' => $row['collection_status'],
            'risk_level' => $row['risk_level'],
            'assigned_collector_id' => $row['assigned_collector_id'],
            'assigned_collection_team' => $row['assigned_collection_team'],
            'days_past_due' => $row['days_past_due'],
            'overdue_bucket' => $row['overdue_bucket'],
            'next_followup_at' => $row['next_followup_at'],
            'ptp_date' => $row['ptp_date'],
            'ptp_amount' => $row['ptp_amount'],
            'note' => $row['note'],
            'meta_json' => json_encode([
                'interest_rate' => $interestRate,
                'interest_type' => $row['interest_type'],
                'imported_customer' => array_intersect_key($row, array_flip([
                    'customer_code', 'khmer_name', 'alternate_phone', 'email', 'id_number', 'gender',
                    'date_of_birth', 'address', 'province', 'district', 'commune', 'village',
                    'family_contact_name', 'family_contact_phone', 'spouse_name', 'spouse_phone',
                    'workplace', 'monthly_income', 'customer_type',
                ])),
                'raw_import_row' => $row['raw_import_row'] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'source_type' => 'import',
            'updated_at' => now(),
        ]);

        if ($existingLoanId && $duplicateMode === 'replace') {
            DB::connection($this->connection)->table('loans')->where('id', $existingLoanId)->update($payload);
            $loanId = $existingLoanId;
            $this->replaceImportedLoanItems($loanId);
            $this->createImportedLoanItem($loanId, $row);
            $this->createImportedGuarantor($loanId, $customerId, $row, true);
            $this->createImportedSchedules($loanId, $row, true);
            $this->createImportedInitialPayment($loanId, $row, true);
            $this->refreshLoanTotals($loanId);

            return $loanId;
        }

        $payload['created_at'] = now();
        $loanId = (int) DB::connection($this->connection)->table('loans')->insertGetId($payload);
        $this->createImportedLoanItem($loanId, $row);
        $this->createImportedGuarantor($loanId, $customerId, $row);
        $this->createImportedSchedules($loanId, $row);
        if ($this->createImportedInitialPayment($loanId, $row)) {
            $this->refreshLoanTotals($loanId);
        }

        return $loanId;
    }

    protected function storeLoanImportRow(string $type, array $row, string $duplicateMode = 'skip', ?int $existingLoanId = null): int
    {
        if ($type === 'active_loans') {
            return $this->storeActiveLoanUpdate($row, $existingLoanId);
        }

        return $this->storeLoan($row, $duplicateMode, $existingLoanId);
    }

    protected function storeActiveLoanUpdate(array $row, ?int $existingLoanId = null): int
    {
        $loanId = $existingLoanId ?: (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0 && ! empty($row['loan_number'])) {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->value('id');
        }
        if ($loanId <= 0) {
            throw new \RuntimeException('Matching active loan was not found. Export active loans first, then import with Replace existing.');
        }

        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            throw new \RuntimeException('Matching active loan was not found.');
        }

        $payload = [
            'loan_number' => $row['loan_number'] ?? null,
            'customer_id' => $row['customer_id'] ?? null,
            'customer_name_snapshot' => $row['customer_name'] ?? null,
            'customer_phone_snapshot' => $row['customer_phone'] ?? null,
            'principal_amount' => $row['principal_amount'] ?? null,
            'total_amount' => $row['total_amount'] ?? null,
            'paid_amount' => $row['paid_amount'] ?? null,
            'balance_amount' => $row['balance_amount'] ?? null,
            'installment_count' => $row['installment_count'] ?? null,
            'duration_months' => $row['duration_months'] ?? ($row['installment_count'] ?? null),
            'loan_date' => $row['loan_date'] ?? null,
            'status' => $row['status'] ?? null,
            'collection_status' => $row['collection_status'] ?? null,
            'assigned_collector_id' => $row['assigned_collector_id'] ?? null,
            'updated_at' => now(),
        ];
        $payload = array_filter($payload, fn ($value) => $value !== null && $value !== '');

        if (! empty($payload)) {
            DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', $payload));
        }

        $customerId = (int) ($row['customer_id'] ?? $loan->customer_id ?? 0);
        if ($customerId > 0) {
            $customerPayload = [
                'name' => $row['customer_name'] ?? null,
                'phone' => $row['customer_phone'] ?? null,
                'updated_at' => now(),
            ];
            $customerPayload = array_filter($customerPayload, fn ($value) => $value !== null && $value !== '');
            if (! empty($customerPayload) && Schema::connection($this->connection)->hasTable('loan_customers')) {
                DB::connection($this->connection)->table('loan_customers')->where('id', $customerId)->update($this->safeColumns('loan_customers', $customerPayload));
            }
        }

        return $loanId;
    }

    protected function storeFullLoanUpdate(array $row, string $duplicateMode = 'skip', ?int $existingLoanId = null): int
    {
        $loanId = $existingLoanId ?: (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0 && ! empty($row['loan_number'])) {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->value('id');
        }
        if ($loanId <= 0) {
            throw new \RuntimeException('Matching loan was not found for full update row.');
        }

        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            throw new \RuntimeException('Matching loan was not found for full update row.');
        }

        $loanPayload = [
            'loan_number' => $row['loan_number'] ?: null,
            'invoice_no' => $row['invoice_no'] ?: null,
            'source_invoice_no' => $row['invoice_no'] ?: null,
            'loan_date' => $row['loan_date'] ?: null,
            'sale_date' => $row['loan_date'] ?: null,
            'customer_name_snapshot' => $row['customer_name'] ?: null,
            'customer_phone_snapshot' => $row['customer_phone'] ?: null,
            'customer_address_snapshot' => $row['customer_address'] ?: null,
            'id_card_number' => $row['national_id'] ?: null,
            'product_name_snapshot' => $row['product_name'] ?: null,
            'imei_snapshot' => $row['imei'] ?: null,
            'principal_amount' => ($row['principal_amount'] ?? 0) > 0 ? $row['principal_amount'] : null,
            'total_amount' => ($row['total_amount'] ?? 0) > 0 ? $row['total_amount'] : null,
            'paid_amount' => array_key_exists('paid_amount', $row) ? $row['paid_amount'] : null,
            'balance_amount' => array_key_exists('balance_amount', $row) ? $row['balance_amount'] : null,
            'status' => $row['status'] ?: null,
            'note' => $row['note'] ?: null,
            'updated_at' => now(),
        ];
        $loanPayload = array_filter($loanPayload, fn ($value) => $value !== null && $value !== '');
        if (! empty($loanPayload)) {
            DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', $loanPayload));
        }

        $customerId = (int) ($row['customer_id'] ?? $loan->customer_id ?? 0);
        if ($customerId > 0 && Schema::connection($this->connection)->hasTable('loan_customers')) {
            DB::connection($this->connection)->table('loan_customers')->where('id', $customerId)->update($this->safeColumns('loan_customers', [
                'customer_code' => $row['customer_code'] ?: null,
                'name' => $row['customer_name'] ?: null,
                'phone' => $row['customer_phone'] ?: null,
                'address' => $row['customer_address'] ?: null,
                'id_number' => $row['national_id'] ?: null,
                'id_card_number' => $row['national_id'] ?: null,
                'national_id' => $row['national_id'] ?: null,
                'updated_at' => now(),
            ]));
        }

        $this->upsertFullUpdateProductItem($loanId, $row);

        $loanNumber = $row['loan_number'] ?: (string) ($loan->loan_number ?? $loanId);
        if (($row['deposit_amount'] ?? 0) > 0) {
            $paymentRow = [
                'loan_id' => $loanId,
                'loan_number' => $loanNumber,
                'payment_type' => 'loan',
                'schedule_id' => null,
                'installment_no' => null,
                'amount' => $row['deposit_amount'],
                'paid_date' => $row['deposit_date'] ?: ($row['loan_date'] ?: now()->toDateString()),
                'paid_at' => null,
                'payment_method' => $row['deposit_method'] ?: 'Cash',
                'currency' => $loan->currency ?? 'USD',
                'exchange_rate' => 1,
                'reference_number' => $row['deposit_reference_no'] ?: 'FULL-DEP-'.$loanNumber,
                'status' => $row['deposit_status'] ?: 'confirmed',
                'received_by' => '',
                'penalty_amount' => 0,
                'discount_amount' => 0,
                'note' => $row['note'],
            ];
            $this->storePayment($paymentRow, $duplicateMode, $this->existingImportRowId('customer_deposit_payments', $paymentRow));
        }

        if (($row['collection_amount'] ?? 0) > 0) {
            $paymentRow = [
                'loan_id' => $loanId,
                'loan_number' => $loanNumber,
                'payment_type' => 'monthly',
                'schedule_id' => null,
                'installment_no' => null,
                'amount' => $row['collection_amount'],
                'paid_date' => $row['collection_date'] ?: ($row['loan_date'] ?: now()->toDateString()),
                'paid_at' => null,
                'payment_method' => $row['collection_method'] ?: 'Cash',
                'currency' => $loan->currency ?? 'USD',
                'exchange_rate' => 1,
                'reference_number' => $row['collection_reference_no'] ?: 'FULL-COL-'.$loanNumber,
                'status' => $row['collection_status'] ?: 'confirmed',
                'received_by' => '',
                'penalty_amount' => 0,
                'discount_amount' => 0,
                'note' => $row['note'],
            ];
            $this->storePayment($paymentRow, $duplicateMode, $this->existingImportRowId('payments', $paymentRow));
        }

        $finalPayload = [
            'paid_amount' => array_key_exists('paid_amount', $row) ? $row['paid_amount'] : null,
            'balance_amount' => array_key_exists('balance_amount', $row) ? $row['balance_amount'] : null,
            'status' => $row['status'] ?: null,
            'updated_at' => now(),
        ];
        $finalPayload = array_filter($finalPayload, fn ($value) => $value !== null && $value !== '');
        if (! empty($finalPayload)) {
            DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', $finalPayload));
        }

        return $loanId;
    }

    protected function upsertFullUpdateProductItem(int $loanId, array $row): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items') || empty($row['product_name'])) {
            return;
        }

        $payload = $this->safeColumns('loan_items', [
            'loan_id' => $loanId,
            'product_name' => $row['product_name'],
            'product_name_snapshot' => $row['product_name'],
            'sku' => $row['sku'],
            'sku_snapshot' => $row['sku'],
            'imei' => $row['imei'],
            'imei_snapshot' => $row['imei'],
            'serial_number' => $row['serial_number'],
            'serial_number_snapshot' => $row['serial_number'],
            'updated_at' => now(),
        ]);

        $existing = DB::connection($this->connection)->table('loan_items')->where('loan_id', $loanId)->orderBy('id')->value('id');
        if ($existing) {
            DB::connection($this->connection)->table('loan_items')->where('id', $existing)->update($payload);
            return;
        }

        $payload['created_at'] = now();
        DB::connection($this->connection)->table('loan_items')->insert($payload);
    }

    protected function createImportedInitialPayment(int $loanId, array $row, bool $replaceExisting = false): bool
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payments')) {
            return false;
        }

        $amount = (float) ($row['paid_amount'] ?? 0);
        if ($amount <= 0) {
            $amount = (float) ($row['down_payment'] ?? 0);
        }
        if ($amount <= 0) {
            return false;
        }

        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return false;
        }

        $paidDate = $row['initial_payment_date'] ?: ($row['loan_date'] ?: now()->toDateString());
        $paidAt = $paidDate.' '.now()->format('H:i:s');
        $method = trim((string) ($row['initial_payment_method'] ?? ''));
        if ($method === '') {
            $cash = (float) ($row['down_payment_cash'] ?? 0);
            $bank = (float) ($row['down_payment_bank'] ?? 0);
            $method = $cash > 0 && $bank > 0 ? 'Mixed' : ($bank > 0 ? 'Bank' : ($cash > 0 ? 'Cash' : 'Import'));
        }

        $paymentRef = trim((string) ($row['initial_payment_reference'] ?? ''));
        if ($paymentRef === '') {
            $paymentRef = 'IMP-DOWN-'.($row['loan_number'] ?? $loan->loan_number ?? $loanId);
        }

        $existingPaymentId = $this->existingPaymentIdByReference($paymentRef, $loanId);
        if ($existingPaymentId && ! $replaceExisting) {
            return false;
        }

        $note = trim(implode("\n", array_filter([
            'Imported initial/down payment',
            $row['note'] ?? null,
        ])));

        $payload = $this->safeColumns('loan_payments', [
            'payment_number' => $paymentRef,
            'payment_ref_no' => $paymentRef,
            'receipt_number' => $paymentRef,
            'loan_id' => $loanId,
            'payment_type' => $row['initial_payment_type'] ?? 'loan',
            'customer_id' => $loan->customer_id ?? null,
            'schedule_id' => null,
            'loan_number_snapshot' => $loan->loan_number ?? ($row['loan_number'] ?? null),
            'customer_name_snapshot' => $loan->customer_name_snapshot ?? ($row['customer_name'] ?? null),
            'customer_phone_snapshot' => $loan->customer_phone_snapshot ?? ($row['customer_phone'] ?? null),
            'channel' => $method,
            'payment_method_snapshot' => $method,
            'amount' => $amount,
            'total_paid' => $amount,
            'total_paid_base' => $amount,
            'base_currency' => $row['currency'] ?? ($loan->currency ?? 'USD'),
            'currency' => $row['currency'] ?? ($loan->currency ?? 'USD'),
            'exchange_rate' => 1,
            'paid_date' => $paidDate,
            'paid_at' => $paidAt,
            'status' => 'confirmed',
            'reference_number' => $paymentRef,
            'note' => $note ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($existingPaymentId) {
            unset($payload['created_at']);
            DB::connection($this->connection)->table('loan_payments')->where('id', $existingPaymentId)->update($payload);
            $paymentId = $existingPaymentId;
        } else {
            $paymentId = (int) DB::connection($this->connection)->table('loan_payments')->insertGetId($payload);
        }

        $this->replaceImportedInitialPaymentDetails($paymentId, $row, $method, $amount, $paymentRef, $note);

        return true;
    }

    protected function existingPaymentIdByReference(string $reference, ?int $loanId = null): ?int
    {
        $columns = array_values(array_filter(['payment_number', 'payment_ref_no', 'receipt_number', 'reference_number'], fn ($column) => $this->hasColumn('loan_payments', $column)));
        if (empty($columns)) {
            return null;
        }

        $query = DB::connection($this->connection)->table('loan_payments');
        if ($loanId && $this->hasColumn('loan_payments', 'loan_id')) {
            $query->where('loan_id', $loanId);
        }
        $query->where(function ($where) use ($columns, $reference) {
            foreach ($columns as $column) {
                $where->orWhere($column, $reference);
            }
        });

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected function replaceImportedInitialPaymentDetails(int $paymentId, array $row, string $method, float $amount, string $reference, ?string $note): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_details')) {
            return;
        }

        DB::connection($this->connection)->table('loan_payment_details')->where('payment_id', $paymentId)->delete();

        $cash = (float) ($row['down_payment_cash'] ?? 0);
        $bank = (float) ($row['down_payment_bank'] ?? 0);
        $detailRows = [];

        if ($cash > 0 && $bank > 0 && round($cash + $bank, 2) === round($amount, 2)) {
            $detailRows[] = ['method' => 'Cash', 'amount' => $cash];
            $detailRows[] = ['method' => 'Bank', 'amount' => $bank];
        } else {
            $detailRows[] = ['method' => $method, 'amount' => $amount];
        }

        foreach ($detailRows as $detail) {
            DB::connection($this->connection)->table('loan_payment_details')->insert($this->safeColumns('loan_payment_details', [
                'payment_id' => $paymentId,
                'method' => $detail['method'],
                'payment_method_snapshot' => $detail['method'],
                'currency' => $row['currency'] ?? 'USD',
                'amount' => $detail['amount'],
                'exchange_rate' => 1,
                'amount_base' => $detail['amount'],
                'reference_number' => $reference,
                'transaction_no' => $reference,
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function storePayment(array $row, string $duplicateMode = 'skip', ?int $existingPaymentId = null): int
    {
        $loan = DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->first();
        $isMonthlyPayment = ($row['payment_type'] ?? 'monthly') === 'monthly';
        $scheduleId = $isMonthlyPayment ? ($row['schedule_id'] ?: $this->oldestOpenScheduleId((int) $loan->id)) : null;
        $paidAt = $row['paid_at'] ?: ($row['paid_date'].' '.now()->format('H:i:s'));
        $paymentRef = $row['reference_number'] ?: 'IMP-PAY-'.now()->format('YmdHis').'-'.Str::random(4);
        $receivedBy = trim((string) ($row['received_by'] ?? ''));
        $receivedById = ctype_digit($receivedBy) ? (int) $receivedBy : $this->currentImportUserId;
        $receivedByName = $receivedBy !== '' && ! ctype_digit($receivedBy)
            ? $receivedBy
            : $this->userDisplayName($receivedById);

        $payload = $this->safeColumns('loan_payments', [
            'payment_number' => $paymentRef,
            'payment_ref_no' => $paymentRef,
            'receipt_number' => $paymentRef,
            'reference_no' => $paymentRef,
            'loan_id' => $loan->id,
            'payment_type' => $row['payment_type'],
            'customer_id' => $loan->customer_id ?? null,
            'schedule_id' => $scheduleId,
            'loan_number_snapshot' => $loan->loan_number ?? ($row['loan_number'] ?? null),
            'customer_name_snapshot' => $loan->customer_name_snapshot ?? null,
            'received_by' => $receivedById,
            'received_by_name_snapshot' => $receivedByName,
            'collected_by_name_snapshot' => $receivedByName,
            'channel' => $row['payment_method'],
            'payment_method_snapshot' => $row['payment_method'],
            'amount' => $row['amount'],
            'principal_paid' => $this->paymentPrincipalPart($scheduleId, $row['amount']),
            'interest_paid' => $this->paymentInterestPart($scheduleId, $row['amount']),
            'penalty_amount' => $row['penalty_amount'] ?? 0,
            'discount_amount' => $row['discount_amount'] ?? 0,
            'total_paid' => $row['amount'],
            'total_paid_base' => $row['amount'] * max(1, $row['exchange_rate']),
            'base_currency' => $row['currency'],
            'currency' => $row['currency'],
            'exchange_rate' => $row['exchange_rate'],
            'paid_date' => $row['paid_date'],
            'payment_date' => $row['paid_date'],
            'paid_at' => $paidAt,
            'status' => $row['status'] ?? 'confirmed',
            'reference_number' => $paymentRef,
            'note' => $row['note'],
            'updated_at' => now(),
        ]);

        if ($existingPaymentId && $duplicateMode === 'replace') {
            DB::connection($this->connection)->table('loan_payments')->where('id', $existingPaymentId)->update($payload);
            $paymentId = $existingPaymentId;
        } else {
            $payload['created_at'] = now();
            $paymentId = (int) DB::connection($this->connection)->table('loan_payments')->insertGetId($payload);
        }

        if (Schema::connection($this->connection)->hasTable('loan_payment_details')) {
            if ($existingPaymentId && $duplicateMode === 'replace') {
                DB::connection($this->connection)->table('loan_payment_details')->where('payment_id', $existingPaymentId)->delete();
            }
            DB::connection($this->connection)->table('loan_payment_details')->insert($this->safeColumns('loan_payment_details', [
                'payment_id' => $paymentId,
                'method' => $row['payment_method'],
                'payment_method_snapshot' => $row['payment_method'],
                'currency' => $row['currency'],
                'amount' => $row['amount'],
                'exchange_rate' => $row['exchange_rate'],
                'amount_base' => $row['amount'] * max(1, $row['exchange_rate']),
                'reference_number' => $paymentRef,
                'transaction_no' => $paymentRef,
                'note' => $row['note'],
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        if ($isMonthlyPayment && (! $existingPaymentId || $duplicateMode !== 'replace')) {
            $this->applyPaymentToSchedules((int) $loan->id, $scheduleId ? (int) $scheduleId : null, $row['amount'], $paidAt);
        }
        $this->refreshLoanTotals((int) $loan->id);

        return $paymentId;
    }

    protected function storeGenericImport(string $type, array $row, string $duplicateMode = 'skip', ?int $existingId = null): int
    {
        if ($type === 'customers') {
            return $this->storeGenericCustomer($row);
        }
        if ($type === 'collection_assignments') {
            return $this->storeCollectionAssignment($row);
        }

        $table = [
            'schedules' => 'loan_payment_schedules',
            'guarantors' => 'loan_guarantors',
            'imei' => Schema::connection($this->connection)->hasTable('loan_product_items') ? 'loan_product_items' : 'loan_items',
        ][$type] ?? null;

        if (! $table || ! Schema::connection($this->connection)->hasTable($table)) {
            throw new \RuntimeException('Import table is not available for '.$type.'.');
        }

        $payload = [
            'loan_id' => $row['loan_id'] ?? null,
            'customer_id' => $row['customer_id'] ?? null,
            'installment_no' => $row['installment_no'] ?? null,
            'due_date' => $row['due_date'] ?? null,
            'principal_amount' => $row['principal_amount'] ?? null,
            'principal_due' => $row['principal_amount'] ?? null,
            'interest_amount' => $row['interest_amount'] ?? null,
            'interest_due' => $row['interest_amount'] ?? null,
            'penalty_amount' => $row['penalty_amount'] ?? null,
            'penalty_due' => $row['penalty_amount'] ?? null,
            'principal' => $row['principal_amount'] ?? null,
            'interest' => $row['interest_amount'] ?? null,
            'total' => $row['schedule_amount'] ?? null,
            'schedule_amount' => $row['schedule_amount'] ?? null,
            'amount_due' => $row['schedule_amount'] ?? null,
            'paid_amount' => $row['paid_amount'] ?? null,
            'amount_paid' => $row['paid_amount'] ?? null,
            'balance_amount' => $row['balance_amount'] ?? null,
            'amount_balance' => $row['balance_amount'] ?? null,
            'name' => $row['name'] ?? null,
            'guarantor_name' => $row['name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'guarantor_phone' => $row['phone'] ?? null,
            'relation' => $row['relation'] ?? null,
            'address' => $row['address'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'product_name' => $row['product_name'] ?? null,
            'product_name_snapshot' => $row['product_name'] ?? null,
            'imei' => $row['imei'] ?? null,
            'imei_snapshot' => $row['imei'] ?? null,
            'serial_no' => $row['serial_no'] ?? null,
            'serial_number_snapshot' => $row['serial_no'] ?? null,
            'qty' => $row['qty'] ?? null,
            'quantity' => $row['qty'] ?? null,
            'unit_price' => $row['unit_price'] ?? null,
            'line_total' => $row['line_total'] ?? null,
            'status' => $row['status'] ?? null,
            'paid_date' => $row['paid_date'] ?? null,
            'paid_at' => $row['paid_date'] ?? null,
            'note' => $row['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($type === 'schedules' && ! empty($row['loan_id']) && ! empty($row['installment_no'])) {
            $existing = DB::connection($this->connection)->table($table)
                ->where('loan_id', $row['loan_id'])
                ->where('installment_no', $row['installment_no'])
                ->value('id');
            if ($existing) {
                if ($duplicateMode === 'replace') {
                    DB::connection($this->connection)->table($table)->where('id', $existing)->update($this->safeColumns($table, $payload));
                    $this->refreshLoanTotals((int) $row['loan_id']);
                }
                return (int) $existing;
            }
        }

        $id = (int) DB::connection($this->connection)->table($table)->insertGetId($this->safeColumns($table, $payload));
        if ($type === 'schedules' && ! empty($row['loan_id'])) {
            $this->refreshLoanTotals((int) $row['loan_id']);
        }

        return $id;
    }

    protected function storeGenericCustomer(array $row): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            throw new \RuntimeException('loan_customers table is not available.');
        }

        $query = DB::connection($this->connection)->table('loan_customers');
        $existing = null;
        if (! empty($row['id']) && $this->hasColumn('loan_customers', 'id')) {
            $existing = (clone $query)->where('id', (int) $row['id'])->value('id');
        }
        if (! $existing && ! empty($row['customer_code']) && $this->hasColumn('loan_customers', 'customer_code')) {
            $existing = (clone $query)->where('customer_code', $row['customer_code'])->value('id');
        }
        if (! $existing && ! empty($row['phone']) && $this->hasColumn('loan_customers', 'phone')) {
            $existing = (clone $query)->where('phone', $row['phone'])->value('id');
        }

        $payload = $this->safeColumns('loan_customers', [
            'customer_code' => $this->normalizeCustomerCode($row['customer_code'] ?? null) ?? ('IMP-CUS-'.now()->format('YmdHis').'-'.Str::random(4)),
            'name' => $row['name'] ?? null,
            'khmer_name' => $row['khmer_name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'alternate_phone' => $row['alternate_phone'] ?? null,
            'email' => $row['email'] ?? null,
            'telegram' => $row['telegram'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'address' => $row['address'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'id_card_number' => $row['id_number'] ?? null,
            'national_id' => $row['national_id'] ?? $row['id_number'] ?? null,
            'customer_group' => $row['customer_group'] ?? null,
            'province' => $row['province'] ?? null,
            'district' => $row['district'] ?? null,
            'commune' => $row['commune'] ?? null,
            'village' => $row['village'] ?? null,
            'family_contact_name' => $row['family_contact_name'] ?? null,
            'family_contact_phone' => $row['family_contact_phone'] ?? null,
            'spouse_name' => $row['spouse_name'] ?? null,
            'spouse_phone' => $row['spouse_phone'] ?? null,
            'occupation' => $row['occupation'] ?? null,
            'workplace' => $row['workplace'] ?? null,
            'employer_name' => $row['employer_name'] ?? null,
            'employer_phone' => $row['employer_phone'] ?? null,
            'monthly_income' => $row['monthly_income'] ?? null,
            'customer_type' => $row['customer_type'] ?? null,
            'gender' => $row['gender'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'status' => $row['status'] ?? 'active',
            'blacklist_status' => $row['blacklist_status'] ?? null,
            'blacklist_reason' => $row['blacklist_reason'] ?? null,
            'note' => $row['note'] ?? null,
            'updated_at' => now(),
        ]);

        if ($existing) {
            DB::connection($this->connection)->table('loan_customers')->where('id', $existing)->update($payload);
            return (int) $existing;
        }

        $payload['created_at'] = now();
        return (int) DB::connection($this->connection)->table('loan_customers')->insertGetId($payload);
    }

    protected function storeCollectionAssignment(array $row): int
    {
        if (empty($row['loan_id'])) {
            throw new \RuntimeException('loan_number or loan_id is required.');
        }

        DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->update($this->safeColumns('loans', [
            'assigned_collector_id' => $row['assigned_collector_id'] ?? null,
            'assigned_collection_team' => $row['assigned_collection_team'] ?? null,
            'collection_status' => $row['collection_status'] ?? null,
            'risk_level' => $row['risk_level'] ?? null,
            'next_followup_at' => $row['next_followup_at'] ?? null,
            'ptp_date' => $row['ptp_date'] ?? null,
            'ptp_amount' => $row['ptp_amount'] ?? null,
            'note' => $row['note'] ?? null,
            'updated_at' => now(),
        ]));

        return (int) $row['loan_id'];
    }

    protected function firstOrCreateCustomer(array $row): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return 0;
        }

        $query = DB::connection($this->connection)->table('loan_customers');
        if (! empty($row['customer_code']) && $this->hasColumn('loan_customers', 'customer_code')) {
            $existing = (clone $query)->where('customer_code', $row['customer_code'])->value('id');
            if ($existing) return (int) $existing;
        }
        if (! empty($row['customer_phone'])) {
            $existing = (clone $query)->where('phone', $row['customer_phone'])->value('id');
            if ($existing) return (int) $existing;
        }
        if (! empty($row['id_number']) && $this->hasColumn('loan_customers', 'id_card_number')) {
            $existing = (clone $query)->where('id_card_number', $row['id_number'])->value('id');
            if ($existing) return (int) $existing;
        }

        return (int) DB::connection($this->connection)->table('loan_customers')->insertGetId($this->safeColumns('loan_customers', [
            'customer_code' => $this->normalizeCustomerCode($row['customer_code'] ?? null) ?? 'IMP-CUS-'.now()->format('YmdHis').'-'.Str::random(4),
            'name' => $row['customer_name'],
            'customer_group' => $row['customer_group'],
            'khmer_name' => $row['khmer_name'],
            'phone' => $row['customer_phone'],
            'alternate_phone' => $row['alternate_phone'],
            'email' => $row['email'],
            'telegram' => $row['telegram'],
            'facebook' => $row['facebook'],
            'id_card_number' => $row['id_number'],
            'id_number' => $row['id_number'],
            'national_id' => $row['national_id'],
            'gender' => $row['gender'],
            'date_of_birth' => $row['date_of_birth'],
            'address' => $row['address'],
            'province' => $row['province'],
            'district' => $row['district'],
            'commune' => $row['commune'],
            'village' => $row['village'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'family_contact_name' => $row['family_contact_name'],
            'family_contact_phone' => $row['family_contact_phone'],
            'spouse_name' => $row['spouse_name'],
            'spouse_phone' => $row['spouse_phone'],
            'workplace' => $row['workplace'],
            'occupation' => $row['occupation'],
            'employer_name' => $row['employer_name'],
            'employer_phone' => $row['employer_phone'],
            'monthly_income' => $row['monthly_income'],
            'customer_type' => $row['customer_type'],
            'business_location_id' => $row['business_location_id'],
            'business_location_name_snapshot' => $row['location_name'],
            'status' => 'active',
            'note' => $row['note'],
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function updateImportedCustomer(int $customerId, array $row): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return;
        }

        $payload = $this->safeColumns('loan_customers', [
            'customer_code' => $row['customer_code'],
            'name' => $row['customer_name'],
            'customer_group' => $row['customer_group'],
            'khmer_name' => $row['khmer_name'],
            'phone' => $row['customer_phone'],
            'alternate_phone' => $row['alternate_phone'],
            'email' => $row['email'],
            'telegram' => $row['telegram'],
            'facebook' => $row['facebook'],
            'id_card_number' => $row['id_number'],
            'id_number' => $row['id_number'],
            'national_id' => $row['national_id'],
            'gender' => $row['gender'],
            'date_of_birth' => $row['date_of_birth'],
            'address' => $row['address'],
            'province' => $row['province'],
            'district' => $row['district'],
            'commune' => $row['commune'],
            'village' => $row['village'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'family_contact_name' => $row['family_contact_name'],
            'family_contact_phone' => $row['family_contact_phone'],
            'spouse_name' => $row['spouse_name'],
            'spouse_phone' => $row['spouse_phone'],
            'workplace' => $row['workplace'],
            'occupation' => $row['occupation'],
            'employer_name' => $row['employer_name'],
            'employer_phone' => $row['employer_phone'],
            'monthly_income' => $row['monthly_income'],
            'customer_type' => $row['customer_type'],
            'business_location_id' => $row['business_location_id'],
            'business_location_name_snapshot' => $row['location_name'],
            'note' => $row['note'],
            'updated_at' => now(),
        ]);

        $payload = array_filter($payload, fn ($value) => $value !== null && $value !== '');
        if (! empty($payload)) {
            DB::connection($this->connection)->table('loan_customers')->where('id', $customerId)->update($payload);
        }
    }

    protected function normalizeCustomerCode($value): ?string
    {
        $code = trim((string) $value);

        return $code !== '' ? $code : null;
    }

    protected function createImportedLoanItem(int $loanId, array $row): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items') || empty($row['product_name'])) {
            return;
        }

        DB::connection($this->connection)->table('loan_items')->insert($this->safeColumns('loan_items', [
            'loan_id' => $loanId,
            'product_id' => $row['product_id'],
            'product_name_snapshot' => $row['product_name'],
            'product_name' => $row['product_name'],
            'sku' => $row['sku'],
            'sku_snapshot' => $row['sku'],
            'brand' => $row['brand'],
            'category' => $row['category'],
            'imei_snapshot' => $row['imei_or_serial'],
            'serial_number' => $row['serial_number'],
            'serial_number_snapshot' => $row['serial_number'],
            'qty' => $row['qty'],
            'unit_price' => $row['unit_price'],
            'line_total' => round($row['qty'] * $row['unit_price'], 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function replaceImportedLoanItems(int $loanId): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_items')) {
            DB::connection($this->connection)->table('loan_items')->where('loan_id', $loanId)->delete();
        }
    }

    protected function createImportedGuarantor(int $loanId, int $customerId, array $row, bool $replaceExisting = false): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_guarantors') || empty($row['guarantor_name'])) {
            return;
        }

        if ($replaceExisting) {
            DB::connection($this->connection)->table('loan_guarantors')->where('loan_id', $loanId)->delete();
        }

        DB::connection($this->connection)->table('loan_guarantors')->insert($this->safeColumns('loan_guarantors', [
            'loan_id' => $loanId,
            'customer_id' => $customerId ?: null,
            'name' => $row['guarantor_name'],
            'guarantor_name' => $row['guarantor_name'],
            'phone' => $row['guarantor_phone'],
            'guarantor_phone' => $row['guarantor_phone'],
            'id_number' => $row['guarantor_national_id'],
            'national_id' => $row['guarantor_national_id'],
            'address' => $row['guarantor_address'],
            'relation' => $row['guarantor_relationship'],
            'relationship' => $row['guarantor_relationship'],
            'status' => 'active',
            'note' => 'Imported with loan migration.',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function createImportedSchedules(int $loanId, array $row, bool $replaceExisting = false): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        $months = max(1, (int) $row['installment_count']);
        $loanPrincipalTotal = max(0, round((float) ($row['principal_amount'] ?? 0), 2));
        $financedAmount = round((float) ($row['financed_amount'] ?? 0), 2);
        $principalAfterDownPayment = round($loanPrincipalTotal - (float) ($row['down_payment'] ?? 0), 2);
        $paymentPrincipalTotal = max(0, $financedAmount > 0 ? $financedAmount : ($principalAfterDownPayment > 0 ? $principalAfterDownPayment : $loanPrincipalTotal));
        $scheduleTotal = round($paymentPrincipalTotal + (float) $row['interest_amount'], 2);
        $principal = $row['monthly_principal'] > 0 ? $row['monthly_principal'] : round($loanPrincipalTotal / $months, 2);
        $paymentPrincipal = round($paymentPrincipalTotal / $months, 2);
        $interest = $row['monthly_interest'] > 0 ? $row['monthly_interest'] : round($row['interest_amount'] / $months, 2);
        $monthlyPayment = $row['monthly_payment'] > 0 ? $row['monthly_payment'] : 0;
        $useMonthlyPayment = $monthlyPayment > 0 && ($monthlyPayment * ($months - 1)) < $scheduleTotal;
        $dueDate = $row['first_due_date'] ?: now()->addMonth()->toDateString();
        $principalAssigned = 0;
        $paymentPrincipalAssigned = 0;
        $interestAssigned = 0;

        for ($i = 1; $i <= $months; $i++) {
            $remainingPrincipal = max(0, round($loanPrincipalTotal - $principalAssigned, 2));
            $remainingPaymentPrincipal = max(0, round($paymentPrincipalTotal - $paymentPrincipalAssigned, 2));
            $remainingInterest = max(0, round($row['interest_amount'] - $interestAssigned, 2));
            $principalDue = $i === $months ? $remainingPrincipal : min($principal, $remainingPrincipal);
            $paymentPrincipalDue = $i === $months ? $remainingPaymentPrincipal : min($paymentPrincipal, $remainingPaymentPrincipal);
            $interestDue = $i === $months ? $remainingInterest : min($interest, $remainingInterest);
            $amountDue = $useMonthlyPayment && $i < $months
                ? $monthlyPayment
                : round($paymentPrincipalDue + $interestDue, 2);
            if ($useMonthlyPayment && $i === $months) {
                $amountDue = round($scheduleTotal - (($monthlyPayment * ($months - 1))), 2);
            }
            $amountDue = max(0, $amountDue);
            $paid = 0;
            $balance = max(0, $amountDue - $paid);
            $payload = $this->safeColumns('loan_payment_schedules', [
                'loan_id' => $loanId,
                'installment_no' => $i,
                'due_date' => date('Y-m-d', strtotime($dueDate.' +'.($i - 1).' month')),
                'principal_due' => $principalDue,
                'principal_amount' => $principalDue,
                'interest_due' => $interestDue,
                'interest_amount' => $interestDue,
                'amount_due' => $amountDue,
                'schedule_amount' => $amountDue,
                'amount_paid' => $paid,
                'paid_amount' => $paid,
                'amount_balance' => $balance,
                'balance_amount' => $balance,
                'status' => 'unpaid',
                'paid_at' => null,
                'updated_at' => now(),
            ]);

            $existingScheduleId = $replaceExisting
                ? DB::connection($this->connection)->table('loan_payment_schedules')
                    ->where('loan_id', $loanId)
                    ->where('installment_no', $i)
                    ->value('id')
                : null;

            if ($existingScheduleId) {
                DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $existingScheduleId)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::connection($this->connection)->table('loan_payment_schedules')->insert($payload);
            }
            $principalAssigned = round($principalAssigned + $principalDue, 2);
            $paymentPrincipalAssigned = round($paymentPrincipalAssigned + $paymentPrincipalDue, 2);
            $interestAssigned = round($interestAssigned + $interestDue, 2);
        }
    }

    protected function oldestOpenScheduleId(int $loanId): ?int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return null;
        }

        $query = DB::connection($this->connection)->table('loan_payment_schedules')->where('loan_id', $loanId);
        if ($this->hasColumn('loan_payment_schedules', 'balance_amount')) {
            $query->where('balance_amount', '>', 0);
        } elseif ($this->hasColumn('loan_payment_schedules', 'amount_balance')) {
            $query->where('amount_balance', '>', 0);
        }

        return ($row = $query->orderBy('due_date')->orderBy('id')->first()) ? (int) $row->id : null;
    }

    protected function applyPaymentToSchedules(int $loanId, ?int $preferredScheduleId, float $amount, string $paidAt): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules') || $amount <= 0) {
            return;
        }

        $remaining = $amount;

        if ($preferredScheduleId) {
            $remaining = $this->applyPaymentToSchedule($preferredScheduleId, $remaining, $paidAt);
        }

        if ($remaining <= 0) {
            return;
        }

        $query = DB::connection($this->connection)->table('loan_payment_schedules')->where('loan_id', $loanId);
        if ($this->hasColumn('loan_payment_schedules', 'balance_amount')) {
            $query->where('balance_amount', '>', 0);
        } elseif ($this->hasColumn('loan_payment_schedules', 'amount_balance')) {
            $query->where('amount_balance', '>', 0);
        }
        if ($preferredScheduleId) {
            $query->where('id', '!=', $preferredScheduleId);
        }

        foreach ($query->orderBy('due_date')->orderBy('id')->get() as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $remaining = $this->applyPaymentToSchedule((int) $schedule->id, $remaining, $paidAt);
        }
    }

    protected function paymentPrincipalPart(?int $scheduleId, float $amount): float
    {
        if (! $scheduleId || ! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return $amount;
        }

        $schedule = DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $scheduleId)->first();
        if (! $schedule) {
            return $amount;
        }

        $principalDue = (float) ($schedule->principal ?? $schedule->principal_amount ?? $schedule->principal_due ?? 0);
        $paid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);

        return max(0, min($amount, max(0, $principalDue - $paid)));
    }

    protected function paymentInterestPart(?int $scheduleId, float $amount): float
    {
        return max(0, round($amount - $this->paymentPrincipalPart($scheduleId, $amount), 2));
    }

    protected function applyPaymentToSchedule(int $scheduleId, float $amount, string $paidAt): float
    {
        $schedule = DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $scheduleId)->first();
        if (! $schedule) {
            return $amount;
        }

        $due = (float) ($schedule->schedule_amount ?? $schedule->amount_due ?? 0);
        if ($due <= 0) {
            $due = (float) ($schedule->principal_amount ?? $schedule->principal_due ?? 0)
                + (float) ($schedule->interest_amount ?? $schedule->interest_due ?? 0)
                + (float) ($schedule->penalty_amount ?? $schedule->penalty_due ?? 0);
        }
        $currentPaid = (float) ($schedule->paid_amount ?? $schedule->amount_paid ?? 0);
        $openAmount = max(0, $due - $currentPaid);
        $applied = min($amount, $openAmount);
        $paid = round($currentPaid + $applied, 2);
        $balance = max(0, $due - $paid);

        DB::connection($this->connection)->table('loan_payment_schedules')->where('id', $scheduleId)->update($this->safeColumns('loan_payment_schedules', [
            'paid_amount' => $paid,
            'amount_paid' => $paid,
            'balance_amount' => $balance,
            'amount_balance' => $balance,
            'status' => $balance <= 0 ? 'paid' : 'partial',
            'paid_at' => $balance <= 0 ? $paidAt : null,
            'updated_at' => now(),
        ]));

        return round($amount - $applied, 2);
    }

    protected function refreshLoanTotals(int $loanId): void
    {
        $paymentQuery = DB::connection($this->connection)->table('loan_payments')->where('loan_id', $loanId);
        if ($this->hasColumn('loan_payments', 'status')) {
            $paymentQuery->whereRaw('LOWER(COALESCE(status, "")) NOT IN ("cancelled", "canceled", "failed", "void", "deleted", "rejected")');
        }
        if ($this->hasColumn('loan_payments', 'deleted_at')) {
            $paymentQuery->whereNull('deleted_at');
        }

        $paid = (float) (clone $paymentQuery)->sum($this->paymentAmountColumn());
        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }
        $total = (float) ($loan->total_amount ?? 0);
        if ($total <= 0) {
            $total = (float) ($loan->principal_amount ?? 0) + (float) ($loan->interest_amount ?? 0);
        }
        $principalPaid = $this->hasColumn('loan_payments', 'principal_paid')
            ? (float) (clone $paymentQuery)->sum('principal_paid')
            : min($paid, (float) ($loan->principal_amount ?? 0));
        $interestPaid = $this->hasColumn('loan_payments', 'interest_paid')
            ? (float) (clone $paymentQuery)->sum('interest_paid')
            : max(0, $paid - $principalPaid);
        $nextSchedule = Schema::connection($this->connection)->hasTable('loan_payment_schedules')
            ? DB::connection($this->connection)->table('loan_payment_schedules')
                ->where('loan_id', $loanId)
                ->where(function ($query) {
                    if ($this->hasColumn('loan_payment_schedules', 'balance_amount')) {
                        $query->orWhere('balance_amount', '>', 0);
                    }
                    if ($this->hasColumn('loan_payment_schedules', 'amount_balance')) {
                        $query->orWhere('amount_balance', '>', 0);
                    }
                })
                ->orderBy('due_date')
                ->orderBy('id')
                ->first()
            : null;
        $today = now()->toDateString();
        $overdueAmount = Schema::connection($this->connection)->hasTable('loan_payment_schedules')
            ? (float) DB::connection($this->connection)->table('loan_payment_schedules')
                ->where('loan_id', $loanId)
                ->whereDate('due_date', '<', $today)
                ->sum($this->hasColumn('loan_payment_schedules', 'balance_amount') ? 'balance_amount' : 'amount_balance')
            : 0;

        DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', [
            'paid_amount' => $paid,
            'balance_amount' => max(0, $total - $paid),
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'outstanding_principal' => max(0, (float) ($loan->principal_amount ?? 0) - $principalPaid),
            'outstanding_interest' => max(0, (float) ($loan->interest_amount ?? 0) - $interestPaid),
            'overdue_amount' => $overdueAmount,
            'next_due_amount' => $nextSchedule ? (float) ($nextSchedule->balance_amount ?? $nextSchedule->amount_balance ?? $nextSchedule->schedule_amount ?? $nextSchedule->amount_due ?? 0) : 0,
            'next_due_date' => $nextSchedule->due_date ?? null,
            'status' => ($total - $paid) <= 0 ? 'closed' : ($loan->status ?? 'active'),
            'updated_at' => now(),
        ]));
    }

    protected function resolveLoanId(array $row): int
    {
        $loanId = (int) ($row['loan_id'] ?? 0);
        if ($loanId > 0) {
            return $loanId;
        }

        $loanNumber = trim((string) ($row['loan_number'] ?? ''));
        if ($loanNumber === '' || ! Schema::connection($this->connection)->hasTable('loans')) {
            return 0;
        }

        return (int) DB::connection($this->connection)->table('loans')->where('loan_number', $loanNumber)->value('id');
    }

    protected function existingImportRowId(string $type, array $row): ?int
    {
        if (($this->isLoanImportType($type) || $this->isFullLoanUpdateType($type)) && Schema::connection($this->connection)->hasTable('loans')) {
            $query = DB::connection($this->connection)->table('loans');
            if (! empty($row['loan_number'])) {
                $query->where('loan_number', $row['loan_number']);
            } elseif (! empty($row['loan_id'])) {
                $query->where('id', (int) $row['loan_id']);
            } else {
                return null;
            }
            $id = $query->value('id');
            return $id ? (int) $id : null;
        }

        if ($type === 'schedules' && ! empty($row['loan_id']) && ! empty($row['installment_no']) && Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            $id = DB::connection($this->connection)->table('loan_payment_schedules')
                ->where('loan_id', $row['loan_id'])
                ->where('installment_no', $row['installment_no'])
                ->value('id');
            return $id ? (int) $id : null;
        }

        if ($this->isPaymentImportType($type) && Schema::connection($this->connection)->hasTable('loan_payments')) {
            if (! empty($row['reference_number'])) {
                $columns = array_values(array_filter(['payment_number', 'payment_ref_no', 'receipt_number', 'reference_number'], fn ($column) => $this->hasColumn('loan_payments', $column)));
                if (! empty($columns)) {
                    $query = DB::connection($this->connection)->table('loan_payments');
                    $reference = $row['reference_number'];
                    if (! empty($row['loan_id']) && $this->hasColumn('loan_payments', 'loan_id')) {
                        $query->where('loan_id', $row['loan_id']);
                    }
                    $query->where(function ($where) use ($columns, $reference) {
                        foreach ($columns as $column) {
                            $where->orWhere($column, $reference);
                        }
                    });
                    $id = $query->value('id');
                    if ($id) {
                        return (int) $id;
                    }
                }
            }

            return $this->existingPaymentIdByComposite($row);
        }

        return null;
    }

    protected function existingPaymentIdByComposite(array $row): ?int
    {
        if (empty($row['loan_id']) || empty($row['paid_date'])) {
            return null;
        }

        $effectiveScheduleId = $row['schedule_id'] ?? null;
        if (empty($effectiveScheduleId)) {
            $effectiveScheduleId = $this->oldestOpenScheduleId((int) $row['loan_id']);
        }

        $query = DB::connection($this->connection)->table('loan_payments')
            ->where('loan_id', $row['loan_id']);

        if ($this->hasColumn('loan_payments', 'payment_type')) {
            $query->where('payment_type', $row['payment_type'] ?? 'monthly');
        }
        if ($this->hasColumn('loan_payments', 'schedule_id')) {
            if (! empty($effectiveScheduleId)) {
                $query->where('schedule_id', $effectiveScheduleId);
            } else {
                $query->whereNull('schedule_id');
            }
        }
        if ($this->hasColumn('loan_payments', 'paid_date')) {
            $query->whereDate('paid_date', $row['paid_date']);
        }
        if ($this->hasColumn('loan_payments', 'amount')) {
            $query->where('amount', $row['amount']);
        }

        $method = trim((string) ($row['payment_method'] ?? ''));
        $hasMethodSnapshot = $this->hasColumn('loan_payments', 'payment_method_snapshot');
        $hasChannel = $this->hasColumn('loan_payments', 'channel');
        if ($method !== '' && ($hasMethodSnapshot || $hasChannel)) {
            $query->where(function ($where) use ($method) {
                if ($this->hasColumn('loan_payments', 'payment_method_snapshot')) {
                    $where->orWhere('payment_method_snapshot', $method);
                }
                if ($this->hasColumn('loan_payments', 'channel')) {
                    $where->orWhere('channel', $method);
                }
            });
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected function exportRows(string $type, array $filters)
    {
        if ($type === 'full_loan_update') {
            return $this->fullLoanUpdateExportRows($filters);
        }
        if ($type === 'active_loan_deposit_template') {
            return $this->activeLoanDepositTemplateRows($filters);
        }
        if ($type === 'active_loan_schedule_template') {
            return $this->activeLoanScheduleTemplateRows($filters);
        }
        if (in_array($type, ['loans', 'active_loans', 'closed_loans', 'overdue_loans', 'collection_report', 'customer_loan_history', 'repossessed_assets', 'monthly_loan_summary', 'collection_assignments'], true)) {
            return $this->loanExportRows($filters, $type);
        }
        if (in_array($type, ['payments', 'monthly_collections', 'customer_deposit_payments', 'loan_payments'], true)) {
            return $this->paymentExportRows($filters, $type);
        }
        if ($type === 'schedules') {
            return $this->scheduleExportRows($filters);
        }
        if ($type === 'customers') {
            return $this->customerExportRows($filters);
        }

        $table = [
            'schedules' => 'loan_payment_schedules',
            'guarantors' => 'loan_guarantors',
            'imei' => Schema::connection($this->connection)->hasTable('loan_product_items') ? 'loan_product_items' : 'loan_items',
        ][$type] ?? 'loans';

        if (! Schema::connection($this->connection)->hasTable($table)) {
            return collect();
        }

        $query = DB::connection($this->connection)->table($table);
        $this->applyCommonFilters($query, $filters, $table);

        return $query->select($this->safeSelect($table, $this->exportColumns($type)))->orderByDesc('id')->get();
    }

    protected function fullLoanUpdateExportRows(array $filters)
    {
        $query = DB::connection($this->connection)->table('loans as l');
        $this->applyCommonFilters($query, $filters, 'loans', 'l');

        $hasCustomerJoin = Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id');
        $hasItemJoin = Schema::connection($this->connection)->hasTable('loan_items');

        if ($hasCustomerJoin) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
        }
        if ($hasItemJoin) {
            $query->leftJoin('loan_items as li', function ($join) {
                $join->on('li.loan_id', '=', 'l.id')
                    ->whereRaw('li.id = (SELECT MIN(li2.id) FROM loan_items li2 WHERE li2.loan_id = l.id)');
            });
        }

        return $query->select([
            DB::raw($this->sqlColumn('loans', 'l', 'loan_number').' as loan_invoice'),
            DB::raw('l.id as loan_id'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'invoice_no').', '.$this->sqlColumn('loans', 'l', 'source_invoice_no').') as invoice_no'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'loan_date').', '.$this->sqlColumn('loans', 'l', 'sale_date').', '.$this->sqlColumn('loans', 'l', 'source_created_at').') as loan_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'status').' as status'),
            DB::raw($this->sqlColumn('loans', 'l', 'customer_id').' as customer_id'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'customer_code').' as customer_code'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'customer_name_snapshot').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'name').') as customer_name'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'customer_phone_snapshot').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'phone').') as customer_phone'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'customer_address_snapshot').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'address').') as customer_address'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'id_card_number').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'national_id').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'id_card_number').') as national_id'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'product_name_snapshot').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'product_name').', '.$this->sqlColumn('loans', 'l', 'product_name_snapshot').') as product_name'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'sku_snapshot').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'sku').') as sku'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'imei_snapshot').', '.$this->sqlColumn('loans', 'l', 'imei_snapshot').') as imei'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'serial_number_snapshot').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'serial_number').') as serial_number'),
            DB::raw($this->sqlColumn('loans', 'l', 'principal_amount', '0').' as principal_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'total_amount', '0').' as total_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'paid_amount', '0').' as paid_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'balance_amount', '0').' as balance_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'down_payment').', 0) as deposit_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'loan_date').', CURDATE()) as deposit_date'),
            DB::raw("'Cash' as deposit_method"),
            DB::raw("CONCAT('FULL-DEP-', COALESCE(".$this->sqlColumn('loans', 'l', 'loan_number', "''").", l.id)) as deposit_reference_no"),
            DB::raw("'confirmed' as deposit_status"),
            DB::raw('GREATEST(COALESCE('.$this->sqlColumn('loans', 'l', 'paid_amount', '0').', 0) - COALESCE('.$this->sqlColumn('loans', 'l', 'down_payment', '0').', 0), 0) as collection_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'last_payment_date').', '.$this->sqlColumn('loans', 'l', 'loan_date').', CURDATE()) as collection_date'),
            DB::raw("'Cash' as collection_method"),
            DB::raw("CONCAT('FULL-COL-', COALESCE(".$this->sqlColumn('loans', 'l', 'loan_number', "''").", l.id)) as collection_reference_no"),
            DB::raw("'confirmed' as collection_status"),
            DB::raw($this->sqlColumn('loans', 'l', 'note').' as note'),
        ])->orderByDesc('l.id')->get();
    }

    protected function activeLoanBaseQuery(array $filters)
    {
        $query = DB::connection($this->connection)->table('loans as l');
        $this->applyCommonFilters($query, $filters, 'loans', 'l');

        if ($this->hasColumn('loans', 'status')) {
            $query->whereIn('l.status', ['active', 'open', 'partial']);
        }

        return $query;
    }

    protected function activeLoanDepositTemplateRows(array $filters)
    {
        $query = $this->activeLoanBaseQuery($filters);

        return $query->select([
            DB::raw($this->sqlColumn('loans', 'l', 'loan_number').' as loan_invoice'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'last_payment_date').', '.$this->sqlColumn('loans', 'l', 'loan_date').', CURDATE()) as payment_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'balance_amount', '0').' as amount'),
            DB::raw('0 as cash_amount'),
            DB::raw('0 as bank_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'balance_amount', '0').' as payoff_amount'),
            DB::raw("'Cash' as payment_method"),
            DB::raw("'loan' as payment_type"),
            DB::raw('NULL as installment_no'),
            DB::raw('NULL as schedule_id'),
            DB::raw($this->sqlColumn('loans', 'l', 'currency', "'USD'").' as currency'),
            DB::raw('1 as exchange_rate'),
            DB::raw('0 as penalty_amount'),
            DB::raw('0 as discount_amount'),
            DB::raw("CONCAT('DEP-', COALESCE(".$this->sqlColumn('loans', 'l', 'loan_number', "''").", l.id), '-', DATE_FORMAT(CURDATE(), '%Y%m%d')) as reference_no"),
            DB::raw("'confirmed' as status"),
            DB::raw('NULL as received_by'),
            DB::raw("'Fill/verify amount before import. Generated from Active/Ongoing loan export.' as note"),
        ])->orderByDesc('l.id')->get();
    }

    protected function activeLoanScheduleTemplateRows(array $filters)
    {
        $query = $this->activeLoanBaseQuery($filters);
        if (Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            $query->leftJoin('loan_payment_schedules as s', 's.loan_id', '=', 'l.id');
        }

        $hasScheduleJoin = Schema::connection($this->connection)->hasTable('loan_payment_schedules');
        $schedulePrincipal = $hasScheduleJoin
            ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'principal').', '.$this->sqlColumn('loan_payment_schedules', 's', 'principal_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'principal_due').', '.$this->sqlColumn('loans', 'l', 'principal_amount', '0').')'
            : $this->sqlColumn('loans', 'l', 'principal_amount', '0');
        $scheduleInterest = $hasScheduleJoin
            ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'interest').', '.$this->sqlColumn('loan_payment_schedules', 's', 'interest_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'interest_due').', '.$this->sqlColumn('loans', 'l', 'interest_amount', '0').')'
            : $this->sqlColumn('loans', 'l', 'interest_amount', '0');
        $scheduleTotal = $hasScheduleJoin
            ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'total').', '.$this->sqlColumn('loan_payment_schedules', 's', 'schedule_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_due').', '.$this->sqlColumn('loans', 'l', 'total_amount', '0').')'
            : $this->sqlColumn('loans', 'l', 'total_amount', '0');
        $schedulePaid = $hasScheduleJoin
            ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'paid_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_paid').', '.$this->sqlColumn('loans', 'l', 'paid_amount', '0').')'
            : $this->sqlColumn('loans', 'l', 'paid_amount', '0');
        $scheduleBalance = $hasScheduleJoin
            ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'balance_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_balance').', '.$this->sqlColumn('loans', 'l', 'balance_amount', '0').')'
            : $this->sqlColumn('loans', 'l', 'balance_amount', '0');

        return $query->select([
            DB::raw($this->sqlColumn('loans', 'l', 'loan_number').' as loan_invoice'),
            DB::raw('l.id as loan_id'),
            DB::raw($hasScheduleJoin ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'installment_no').', 1) as installment_no' : '1 as installment_no'),
            DB::raw($hasScheduleJoin ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'due_date').', '.$this->sqlColumn('loans', 'l', 'first_due_date').', '.$this->sqlColumn('loans', 'l', 'loan_date').', CURDATE()) as due_date' : 'COALESCE('.$this->sqlColumn('loans', 'l', 'first_due_date').', '.$this->sqlColumn('loans', 'l', 'loan_date').', CURDATE()) as due_date'),
            DB::raw($schedulePrincipal.' as principal'),
            DB::raw($scheduleInterest.' as interest'),
            DB::raw($scheduleTotal.' as total'),
            DB::raw($schedulePaid.' as paid_amount'),
            DB::raw($scheduleBalance.' as balance_amount'),
            DB::raw('CASE WHEN '.$scheduleBalance.' <= 0 THEN "paid" ELSE COALESCE('.($hasScheduleJoin ? $this->sqlColumn('loan_payment_schedules', 's', 'status') : 'NULL').', "unpaid") END as status'),
            DB::raw($hasScheduleJoin ? 'COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'paid_date').', '.$this->sqlColumn('loan_payment_schedules', 's', 'paid_at').', '.$this->sqlColumn('loans', 'l', 'last_payment_date').') as paid_date' : $this->sqlColumn('loans', 'l', 'last_payment_date').' as paid_date'),
        ])->orderByDesc('l.id')->orderBy('installment_no')->get();
    }

    protected function customerExportRows(array $filters)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return collect();
        }

        $query = DB::connection($this->connection)->table('loan_customers as c');
        $this->applyCommonFilters($query, $filters, 'loan_customers', 'c');

        return $query->select([
            DB::raw($this->sqlColumn('loan_customers', 'c', 'id').' as id'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'customer_code').' as customer_code'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_customers', 'c', 'name').', '.$this->sqlColumn('loan_customers', 'c', 'customer_name').') as customer_name'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'khmer_name').' as khmer_name'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'customer_group').' as customer_group'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'phone').' as phone'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'alternate_phone').' as alternate_phone'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'email').' as email'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'telegram').' as telegram'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'facebook').' as facebook'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_customers', 'c', 'national_id').', '.$this->sqlColumn('loan_customers', 'c', 'id_card_number').', '.$this->sqlColumn('loan_customers', 'c', 'id_number').') as national_id'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_customers', 'c', 'id_number').', '.$this->sqlColumn('loan_customers', 'c', 'id_card_number').', '.$this->sqlColumn('loan_customers', 'c', 'national_id').') as id_number'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'address').' as address'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'province').' as province'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'district').' as district'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'commune').' as commune'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'village').' as village'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'gender').' as gender'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'date_of_birth').' as date_of_birth'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'family_contact_name').' as family_contact_name'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'family_contact_phone').' as family_contact_phone'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'spouse_name').' as spouse_name'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'spouse_phone').' as spouse_phone'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'occupation').' as occupation'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'workplace').' as workplace'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'employer_name').' as employer_name'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'employer_phone').' as employer_phone'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'monthly_income').' as monthly_income'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'customer_type').' as customer_type'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'status').' as status'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'blacklist_status').' as blacklist_status'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'blacklist_reason').' as blacklist_reason'),
            DB::raw($this->sqlColumn('loan_customers', 'c', 'note').' as note'),
        ])->orderByDesc('c.id')->get();
    }

    protected function loanExportRows(array $filters, string $type = 'loans')
    {
        $query = DB::connection($this->connection)->table('loans as l');
        $this->applyCommonFilters($query, $filters, 'loans', 'l');

        if ($type === 'active_loans' && $this->hasColumn('loans', 'status')) {
            $query->whereIn('l.status', ['active', 'open', 'partial']);
        }
        if ($type === 'closed_loans' && $this->hasColumn('loans', 'status')) {
            $query->whereIn('l.status', ['closed', 'completed', 'paid']);
        }
        if ($type === 'overdue_loans') {
            $hasCollectionStatus = $this->hasColumn('loans', 'collection_status');
            $hasDaysPastDue = $this->hasColumn('loans', 'days_past_due');
            $hasOverdueBucket = $this->hasColumn('loans', 'overdue_bucket');
            if ($hasCollectionStatus || $hasDaysPastDue || $hasOverdueBucket) {
                $query->where(function ($where) use ($hasCollectionStatus, $hasDaysPastDue, $hasOverdueBucket) {
                    if ($hasCollectionStatus) {
                        $where->orWhereIn('l.collection_status', ['overdue', 'delinquent']);
                    }
                    if ($hasDaysPastDue) {
                        $where->orWhere('l.days_past_due', '>', 0);
                    }
                    if ($hasOverdueBucket) {
                        $where->orWhereNotNull('l.overdue_bucket');
                    }
                });
            }
        }
        if ($type === 'repossessed_assets') {
            $query->where(function ($where) {
                if ($this->hasColumn('loans', 'repossession_status')) {
                    $where->orWhereNotNull('l.repossession_status')->orWhere('l.collection_status', 'repossession');
                } else {
                    $where->orWhere('l.collection_status', 'repossession');
                }
            });
        }

        if ($type !== 'loans') {
            if (in_array($type, ['collection_report', 'customer_loan_history', 'repossessed_assets', 'monthly_loan_summary'], true)) {
                if (Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id')) {
                    $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
                }
                if (Schema::connection($this->connection)->hasTable('loan_business_locations') && $this->hasColumn('loans', 'business_location_id')) {
                    $query->leftJoin('loan_business_locations as loc', 'loc.id', '=', 'l.business_location_id');
                }
                if (Schema::connection($this->connection)->hasTable('loan_guarantors')) {
                    $query->leftJoin('loan_guarantors as g', 'g.loan_id', '=', 'l.id');
                }
                if (Schema::connection($this->connection)->hasTable('loan_items')) {
                    $query->leftJoin('loan_items as li', 'li.loan_id', '=', 'l.id');
                }

                return $query->select($this->loanReportExportSelect())->orderByDesc('l.id')->get();
            }

            return $query->select($this->safeQualifiedSelect('loans', 'l', $this->exportColumns($type)))->orderByDesc('l.id')->get();
        }

        if (Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id')) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
        }
        if (Schema::connection($this->connection)->hasTable('loan_business_locations') && $this->hasColumn('loans', 'business_location_id')) {
            $query->leftJoin('loan_business_locations as loc', 'loc.id', '=', 'l.business_location_id');
        }
        if (Schema::connection($this->connection)->hasTable('loan_guarantors')) {
            $query->leftJoin('loan_guarantors as g', 'g.loan_id', '=', 'l.id');
        }
        if (Schema::connection($this->connection)->hasTable('loan_items')) {
            $query->leftJoin('loan_items as li', 'li.loan_id', '=', 'l.id');
        }

        return $query->select($this->loanFullExportSelect())->orderByDesc('l.id')->get();
    }

    protected function paymentExportRows(array $filters, string $type = 'payments')
    {
        $query = DB::connection($this->connection)->table('loan_payments as p')
            ->leftJoin('loans as l', 'l.id', '=', 'p.loan_id');
        if (Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id')) {
            $query->leftJoin('loan_customers as c', 'c.id', '=', 'l.customer_id');
        }
        if (Schema::connection($this->connection)->hasTable('loan_payment_schedules') && $this->hasColumn('loan_payments', 'schedule_id')) {
            $query->leftJoin('loan_payment_schedules as s', 's.id', '=', 'p.schedule_id');
        }
        $this->applyCommonFilters($query, $filters, 'loan_payments', 'p');
        if ($type === 'monthly_collections' && $this->hasColumn('loan_payments', 'payment_type')) {
            $query->where('p.payment_type', 'monthly');
        }
        if (in_array($type, ['customer_deposit_payments', 'loan_payments'], true) && $this->hasColumn('loan_payments', 'payment_type')) {
            $query->where('p.payment_type', 'loan');
        }

        return $query->select($this->paymentFullExportSelect())->orderByDesc('p.id')->get();
    }

    protected function scheduleExportRows(array $filters)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return collect();
        }

        $query = DB::connection($this->connection)->table('loan_payment_schedules as s')
            ->leftJoin('loans as l', 'l.id', '=', 's.loan_id');
        $this->applyCommonFilters($query, $filters, 'loan_payment_schedules', 's');

        return $query->select([
            DB::raw($this->sqlColumn('loans', 'l', 'loan_number').' as loan_invoice'),
            DB::raw($this->sqlColumn('loan_payment_schedules', 's', 'loan_id').' as loan_id'),
            DB::raw($this->sqlColumn('loan_payment_schedules', 's', 'installment_no').' as installment_no'),
            DB::raw($this->sqlColumn('loan_payment_schedules', 's', 'due_date').' as due_date'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'principal').', '.$this->sqlColumn('loan_payment_schedules', 's', 'principal_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'principal_due').') as principal'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'interest').', '.$this->sqlColumn('loan_payment_schedules', 's', 'interest_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'interest_due').') as interest'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'total').', '.$this->sqlColumn('loan_payment_schedules', 's', 'schedule_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_due').') as total'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'paid_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_paid').', 0) as paid_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'balance_amount').', '.$this->sqlColumn('loan_payment_schedules', 's', 'amount_balance').', 0) as balance_amount'),
            DB::raw($this->sqlColumn('loan_payment_schedules', 's', 'status').' as status'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payment_schedules', 's', 'paid_date').', '.$this->sqlColumn('loan_payment_schedules', 's', 'paid_at').') as paid_date'),
        ])->orderByDesc('s.id')->get();
    }

    protected function loanFullExportSelect(): array
    {
        $hasCustomerJoin = Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id');
        $hasLocationJoin = Schema::connection($this->connection)->hasTable('loan_business_locations') && $this->hasColumn('loans', 'business_location_id');
        $hasGuarantorJoin = Schema::connection($this->connection)->hasTable('loan_guarantors');
        $hasItemJoin = Schema::connection($this->connection)->hasTable('loan_items');

        return [
            DB::raw($this->sqlColumn('loans', 'l', 'id').' as loan_id'),
            DB::raw($this->sqlColumn('loans', 'l', 'loan_number').' as loan_invoice'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'sale_id').', '.$this->sqlColumn('loans', 'l', 'source_transaction_id').') as sale_id'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'invoice_no').', '.$this->sqlColumn('loans', 'l', 'source_invoice_no').') as invoice_no'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'sale_date').', '.$this->sqlColumn('loans', 'l', 'source_created_at').', '.$this->sqlColumn('loans', 'l', 'loan_date').') as sale_date'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasLocationJoin, 'loan_business_locations', 'loc', 'location_code').', '.$this->sqlColumn('loans', 'l', 'business_location_id').') as location_id'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'business_location_name_snapshot').', '.$this->sqlColumnWhen($hasLocationJoin, 'loan_business_locations', 'loc', 'name').') as location_name'),
            DB::raw($this->sqlColumn('loans', 'l', 'created_by').' as created_by'),
            DB::raw($this->sqlColumn('loans', 'l', 'approved_by').' as approved_by'),
            DB::raw($this->sqlColumn('loans', 'l', 'status').' as loan_status'),
            DB::raw($this->sqlColumn('loans', 'l', 'status').' as status'),
            DB::raw($this->sqlColumn('loans', 'l', 'created_at').' as created_at'),
            DB::raw($this->sqlColumn('loans', 'l', 'customer_id').' as customer_id'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'customer_code').' as customer_code'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'customer_name_snapshot').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'name').') as customer_name'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'customer_group').' as customer_group'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'customer_phone_snapshot').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'phone').') as phone'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'alternate_phone').' as alternate_phone'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'email').' as email'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'national_id').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'id_card_number').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'id_number').') as national_id'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'address').' as address'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'occupation').' as occupation'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'employer_name').', '.$this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'workplace').') as employer_name'),
            DB::raw($this->sqlColumnWhen($hasCustomerJoin, 'loan_customers', 'c', 'employer_phone').' as employer_phone'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'guarantor_name').', '.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'name').') as guarantor_name'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'guarantor_phone').', '.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'phone').') as guarantor_phone'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'national_id').', '.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'id_number').') as guarantor_national_id'),
            DB::raw($this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'address').' as guarantor_address'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'relationship').', '.$this->sqlColumnWhen($hasGuarantorJoin, 'loan_guarantors', 'g', 'relation').') as guarantor_relationship'),
            DB::raw($this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'product_id').' as product_id'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'product_name').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'product_name_snapshot').', '.$this->sqlColumn('loans', 'l', 'product_name_snapshot').') as product_name'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'sku').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'sku_snapshot').') as sku'),
            DB::raw($this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'brand').' as brand'),
            DB::raw($this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'category').' as category'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'imei_snapshot').', '.$this->sqlColumn('loans', 'l', 'imei_snapshot').') as imei'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'serial_number').', '.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'serial_number_snapshot').') as serial_number'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'qty', '1').', 1) as quantity'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'unit_price').', '.$this->sqlColumn('loans', 'l', 'principal_amount', '0').') as unit_price'),
            DB::raw('COALESCE('.$this->sqlColumnWhen($hasItemJoin, 'loan_items', 'li', 'line_total').', '.$this->sqlColumn('loans', 'l', 'principal_amount', '0').') as total_price'),
            DB::raw($this->sqlColumn('loans', 'l', 'principal_amount', '0').' as principal_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'total_amount', '0').' as total_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'down_payment', '0').' as down_payment'),
            DB::raw("'loan' as payment_type"),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'financed_amount').', '.$this->sqlColumn('loans', 'l', 'balance_amount', '0').') as financed_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'interest_rate', '0').' as interest_rate'),
            DB::raw("COALESCE(".$this->sqlColumn('loans', 'l', 'interest_type').", 'flat') as interest_type"),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'duration_months').', '.$this->sqlColumn('loans', 'l', 'installment_count', '1').') as duration_months'),
            DB::raw($this->sqlColumn('loans', 'l', 'payment_frequency', "'monthly'").' as payment_frequency'),
            DB::raw($this->sqlColumn('loans', 'l', 'first_due_date').' as first_due_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'penalty_type').' as penalty_type'),
            DB::raw($this->sqlColumn('loans', 'l', 'penalty_amount', '0').' as penalty_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'note').' as note'),
            DB::raw($this->sqlColumn('loans', 'l', 'paid_amount', '0').' as total_paid'),
            DB::raw($this->sqlColumn('loans', 'l', 'paid_amount', '0').' as paid_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'balance_amount', '0').' as balance_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'principal_paid', '0').' as principal_paid'),
            DB::raw($this->sqlColumn('loans', 'l', 'interest_paid', '0').' as interest_paid'),
            DB::raw($this->sqlColumn('loans', 'l', 'outstanding_principal', '0').' as outstanding_principal'),
            DB::raw($this->sqlColumn('loans', 'l', 'outstanding_interest', '0').' as outstanding_interest'),
            DB::raw($this->sqlColumn('loans', 'l', 'overdue_amount', '0').' as overdue_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'next_due_amount', '0').' as next_due_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'next_due_date').' as next_due_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'last_payment_date').' as last_payment_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'last_payment_amount', '0').' as last_payment_amount'),
            DB::raw($this->sqlColumn('loans', 'l', 'collection_status').' as collection_status'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'collector_name_snapshot').', '.$this->sqlColumn('loans', 'l', 'assigned_collection_team').') as collector_name'),
            DB::raw($this->sqlColumn('loans', 'l', 'last_followup_date').' as last_followup_date'),
            DB::raw('COALESCE('.$this->sqlColumn('loans', 'l', 'followup_result').', '.$this->sqlColumn('loans', 'l', 'last_contact_result').') as followup_result'),
            DB::raw($this->sqlColumn('loans', 'l', 'contact_attempt_count', '0').' as contact_attempts'),
            DB::raw($this->sqlColumn('loans', 'l', 'repossession_status').' as repossession_status'),
            DB::raw($this->sqlColumn('loans', 'l', 'repossession_date').' as repossession_date'),
            DB::raw($this->sqlColumn('loans', 'l', 'repossession_reason').' as repossession_reason'),
        ];
    }

    protected function loanReportExportSelect(): array
    {
        return $this->loanFullExportSelect();
    }

    protected function paymentFullExportSelect(): array
    {
        $hasCustomerJoin = Schema::connection($this->connection)->hasTable('loan_customers') && $this->hasColumn('loans', 'customer_id');
        $hasScheduleJoin = Schema::connection($this->connection)->hasTable('loan_payment_schedules') && $this->hasColumn('loan_payments', 'schedule_id');

        return [
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'loan_number_snapshot').', '.$this->sqlColumn('loans', 'l', 'loan_number').') as loan_invoice'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'payment_date').', '.$this->sqlColumn('loan_payments', 'p', 'paid_date').', '.$this->sqlColumn('loan_payments', 'p', 'paid_at').') as payment_date'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'amount', '0').' as amount'),
            DB::raw('0 as cash_amount'),
            DB::raw('0 as bank_amount'),
            DB::raw('0 as payoff_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'payment_method_snapshot').', '.$this->sqlColumn('loan_payments', 'p', 'channel').") as payment_method"),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'payment_type').' as payment_type'),
            DB::raw($hasScheduleJoin ? 's.installment_no' : 'NULL as installment_no'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'schedule_id').' as schedule_id'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'base_currency').', '.$this->sqlColumn('loan_payments', 'p', 'currency').", 'USD') as currency"),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'exchange_rate', '1').' as exchange_rate'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'penalty_amount', '0').' as penalty_amount'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'discount_amount', '0').' as discount_amount'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'reference_no').', '.$this->sqlColumn('loan_payments', 'p', 'reference_number').', '.$this->sqlColumn('loan_payments', 'p', 'payment_ref_no').', '.$this->sqlColumn('loan_payments', 'p', 'receipt_number').', '.$this->sqlColumn('loan_payments', 'p', 'payment_number').') as reference_no'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'status', "'confirmed'").' as status'),
            DB::raw('COALESCE('.$this->sqlColumn('loan_payments', 'p', 'received_by_name_snapshot').', '.$this->sqlColumn('loan_payments', 'p', 'received_by').') as received_by'),
            DB::raw($this->sqlColumn('loan_payments', 'p', 'note').' as note'),
        ];
    }

    protected function applyCommonFilters($query, array $filters, string $table, ?string $alias = null): void
    {
        $prefix = $alias ? $alias.'.' : '';
        if (! empty($filters['status']) && $this->hasColumn($table, 'status')) {
            $query->where($prefix.'status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $column = $this->dateColumn($table);
            if ($column) $query->whereDate($prefix.$column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $column = $this->dateColumn($table);
            if ($column) $query->whereDate($prefix.$column, '<=', $filters['date_to']);
        }
    }

    protected function exportColumns(string $type): array
    {
        $loanColumns = [
            'loan_id', 'loan_invoice', 'sale_id', 'invoice_no', 'sale_date', 'location_id', 'location_name',
            'created_by', 'approved_by', 'loan_status', 'status', 'created_at',
            'customer_id', 'customer_code', 'customer_name', 'customer_group', 'phone', 'alternate_phone',
            'email', 'national_id', 'address', 'occupation', 'employer_name', 'employer_phone',
            'guarantor_name', 'guarantor_phone', 'guarantor_national_id', 'guarantor_address',
            'guarantor_relationship', 'product_id', 'product_name', 'sku', 'brand', 'category', 'imei',
            'serial_number', 'quantity', 'unit_price', 'total_price', 'principal_amount', 'total_amount',
            'down_payment', 'payment_type', 'financed_amount', 'interest_rate', 'interest_type', 'duration_months',
            'payment_frequency', 'first_due_date', 'penalty_type', 'penalty_amount', 'note',
            'total_paid', 'paid_amount', 'balance_amount', 'principal_paid', 'interest_paid', 'outstanding_principal',
            'outstanding_interest', 'overdue_amount', 'next_due_amount', 'next_due_date',
            'last_payment_date', 'last_payment_amount', 'collection_status', 'collector_name',
            'last_followup_date', 'followup_result', 'contact_attempts', 'repossession_status',
            'repossession_date', 'repossession_reason',
        ];

        $columns = [
            'customers' => ['id', 'customer_code', 'customer_name', 'khmer_name', 'customer_group', 'phone', 'alternate_phone', 'email', 'telegram', 'facebook', 'national_id', 'id_number', 'address', 'province', 'district', 'commune', 'village', 'gender', 'date_of_birth', 'family_contact_name', 'family_contact_phone', 'spouse_name', 'spouse_phone', 'occupation', 'workplace', 'employer_name', 'employer_phone', 'monthly_income', 'customer_type', 'status', 'blacklist_status', 'blacklist_reason', 'note'],
            'loans' => $loanColumns,
            'full_loan_update' => ['loan_invoice', 'loan_id', 'invoice_no', 'loan_date', 'status', 'customer_id', 'customer_code', 'customer_name', 'customer_phone', 'customer_address', 'national_id', 'product_name', 'sku', 'imei', 'serial_number', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'deposit_amount', 'deposit_date', 'deposit_method', 'deposit_reference_no', 'deposit_status', 'collection_amount', 'collection_date', 'collection_method', 'collection_reference_no', 'collection_status', 'note'],
            'active_loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'installment_count', 'loan_date', 'status', 'collection_status', 'assigned_collector_id'],
            'active_loan_deposit_template' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
            'active_loan_schedule_template' => ['loan_invoice', 'loan_id', 'installment_no', 'due_date', 'principal', 'interest', 'total', 'paid_amount', 'balance_amount', 'status', 'paid_date'],
            'closed_loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'loan_date', 'status', 'last_payment_date', 'last_payment_amount'],
            'overdue_loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'total_amount', 'paid_amount', 'balance_amount', 'days_past_due', 'overdue_bucket', 'collection_status', 'risk_level', 'next_followup_at', 'assigned_collector_id'],
            'collection_report' => ['loan_id', 'loan_invoice', 'customer_name', 'phone', 'collection_status', 'collector_name', 'last_followup_date', 'followup_result', 'contact_attempts', 'overdue_amount', 'next_due_amount', 'next_due_date', 'repossession_status'],
            'customer_loan_history' => ['customer_id', 'customer_code', 'customer_name', 'phone', 'loan_id', 'loan_invoice', 'loan_status', 'sale_date', 'principal_amount', 'total_amount', 'total_paid', 'overdue_amount', 'next_due_date'],
            'payments' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
            'monthly_collections' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
            'customer_deposit_payments' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
            'loan_payments' => ['loan_invoice', 'payment_date', 'amount', 'cash_amount', 'bank_amount', 'payoff_amount', 'payment_method', 'payment_type', 'installment_no', 'schedule_id', 'currency', 'exchange_rate', 'penalty_amount', 'discount_amount', 'reference_no', 'status', 'received_by', 'note'],
            'repossessed_assets' => ['loan_id', 'loan_invoice', 'customer_name', 'phone', 'product_name', 'imei', 'serial_number', 'repossession_status', 'repossession_date', 'repossession_reason', 'collector_name'],
            'monthly_loan_summary' => ['loan_id', 'loan_invoice', 'sale_date', 'customer_name', 'phone', 'principal_amount', 'total_amount', 'total_paid', 'outstanding_principal', 'outstanding_interest', 'loan_status', 'collection_status'],
            'schedules' => ['loan_invoice', 'loan_id', 'installment_no', 'due_date', 'principal', 'interest', 'total', 'paid_amount', 'balance_amount', 'status', 'paid_date'],
            'guarantors' => ['id', 'loan_id', 'customer_id', 'name', 'guarantor_name', 'phone', 'guarantor_phone', 'relation', 'address', 'id_number', 'status', 'note'],
            'imei' => ['id', 'loan_id', 'product_name', 'product_name_snapshot', 'imei', 'imei_snapshot', 'serial_no', 'serial_number_snapshot', 'qty', 'quantity', 'unit_price', 'line_total', 'status', 'note'],
            'collection_assignments' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'assigned_collector_id', 'assigned_collection_team', 'collection_status', 'risk_level', 'next_followup_at', 'ptp_date', 'ptp_amount', 'days_past_due', 'overdue_bucket', 'note'],
        ];

        return $columns[$type] ?? $columns['loans'];
    }

    protected function sqlColumn(string $table, string $alias, string $column, string $default = 'NULL'): string
    {
        return $this->hasColumn($table, $column) ? $alias.'.'.$column : $default;
    }

    protected function sqlColumnWhen(bool $joined, string $table, string $alias, string $column, string $default = 'NULL'): string
    {
        return $joined ? $this->sqlColumn($table, $alias, $column, $default) : $default;
    }

    protected function dateColumn(string $table): ?string
    {
        foreach (['loan_date', 'paid_date', 'paid_at', 'created_at'] as $column) {
            if ($this->hasColumn($table, $column)) return $column;
        }

        return null;
    }

    protected function createBatch(string $type, UploadedFile $file, ?int $userId, int $totalRows, array $headers): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return 0;
        }

        $this->ensureImportBatchColumns();
        $path = $this->storeUploadedFileInModule($file, 'imports');

        return (int) DB::connection($this->connection)->table('loan_import_batches')->insertGetId($this->safeColumns('loan_import_batches', [
            'batch_code' => 'IMP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $this->importBatchStorageType($type),
            'uploaded_by' => $userId,
            'status' => 'processing',
            'column_mapping_json' => json_encode($headers),
            'total_rows' => $totalRows,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function importBatch(int $batchId)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return null;
        }

        return DB::connection($this->connection)->table('loan_import_batches')->where('id', $batchId)->first();
    }

    protected function readStoredImportFile(string $path, string $type): array
    {
        $path = str_replace('\\', '/', trim($path));
        $legacyPrefix = 'Modules/LoanManagement/storage/';
        $prefix = Str::startsWith($path, $legacyPrefix) ? $legacyPrefix : 'storage/';
        if (! Str::startsWith($path, $prefix) || Str::contains($path, ['..'])) {
            throw new \RuntimeException('Invalid import file path.');
        }

        $relative = substr($path, strlen($prefix));
        $absolute = $this->moduleStoragePath(dirname($relative), basename($relative));
        if (! is_file($absolute)) {
            throw new \RuntimeException('Uploaded import file was not found.');
        }

        return strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'xlsx'
            ? $this->readXlsx($absolute, $type)
            : $this->readCsv($absolute);
    }

    protected function createImportRow(int $batchId, int $rowNo, array $raw, array $normalized, array $errors): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_rows')) {
            return 0;
        }

        return (int) DB::connection($this->connection)->table('loan_import_rows')->insertGetId($this->safeColumns('loan_import_rows', [
            'batch_id' => $batchId,
            'row_no' => $rowNo,
            'raw_row_json' => json_encode($raw),
            'normalized_json' => json_encode($normalized),
            'status' => empty($errors) ? 'valid' : 'invalid',
            'error_message' => implode('; ', $errors),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function createExportLog(string $type, array $filters, ?int $userId, int $rowsCount, string $format = 'csv'): ?int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_export_logs')) {
            return null;
        }

        return (int) DB::connection($this->connection)->table('loan_export_logs')->insertGetId($this->safeColumns('loan_export_logs', [
            'export_type' => $type,
            'format' => $format,
            'status' => 'processing',
            'requested_by' => $userId,
            'requested_by_name_snapshot' => auth()->user()->username ?? auth()->user()->first_name ?? null,
            'filters_json' => json_encode($filters),
            'rows_count' => $rowsCount,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function safeColumns(string $table, array $payload): array
    {
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return [];
        }

        return array_intersect_key($payload, array_flip(Schema::connection($this->connection)->getColumnListing($table)));
    }

    protected function safeSelect(string $table, array $columns): array
    {
        $available = Schema::connection($this->connection)->getColumnListing($table);
        $select = array_values(array_intersect($columns, $available));

        return empty($select) ? ['id'] : $select;
    }

    protected function safeQualifiedSelect(string $table, string $alias, array $columns): array
    {
        $available = Schema::connection($this->connection)->getColumnListing($table);
        $select = [];

        foreach (array_values(array_intersect($columns, $available)) as $column) {
            $select[] = $alias.'.'.$column;
        }

        return empty($select) ? [$alias.'.id'] : $select;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return Schema::connection($this->connection)->hasTable($table)
            && Schema::connection($this->connection)->hasColumn($table, $column);
    }

    protected function paymentAmountColumn(): string
    {
        if ($this->hasColumn('loan_payments', 'total_paid_base')) return 'total_paid_base';
        if ($this->hasColumn('loan_payments', 'total_paid')) return 'total_paid';
        return 'amount';
    }

    protected function nextLoanNumber(): string
    {
        return 'IMP-LN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    protected function storeUploadedFileInModule(UploadedFile $file, string $directory): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = now()->format('YmdHis').'-'.Str::random(32).($extension !== '' ? '.'.$extension : '');
        $absolutePath = $this->moduleStoragePath($directory, $filename);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $file->move(dirname($absolutePath), basename($absolutePath));

        return 'storage/'.$directory.'/'.$filename;
    }

    protected function moduleStoragePath(string $directory, string $filename): string
    {
        return module_path('LoanManagement', 'storage/'.$directory.'/'.$filename);
    }

    protected function ensurePaymentTypeColumn(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payments')
            || Schema::connection($this->connection)->hasColumn('loan_payments', 'payment_type')) {
            return;
        }

        Schema::connection($this->connection)->table('loan_payments', function ($table) {
            $table->string('payment_type', 20)->default('monthly')->after('loan_id');
        });
    }

    protected function ensureImportBatchColumns(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return;
        }

        $this->ensureVarcharColumnLength('loan_import_batches', 'status', 30, "NOT NULL DEFAULT 'uploaded'");
        $this->ensureVarcharColumnLength('loan_import_batches', 'file_type', 60, 'NULL');
    }

    protected function ensureVarcharColumnLength(string $table, string $columnName, int $minimumLength, string $definitionSuffix): void
    {
        if (! Schema::connection($this->connection)->hasColumn($table, $columnName)) {
            return;
        }

        $column = DB::connection($this->connection)->selectOne(
            'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS character_maximum_length
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                LIMIT 1',
            [$table, $columnName]
        );

        if ($column
            && strtolower((string) $column->data_type) === 'varchar'
            && (int) $column->character_maximum_length < $minimumLength) {
            DB::connection($this->connection)->statement(
                "ALTER TABLE `{$table}` MODIFY `{$columnName}` varchar({$minimumLength}) {$definitionSuffix}"
            );
        }
    }

    protected function userDisplayName(?int $userId): ?string
    {
        if (empty($userId) || ! Schema::hasTable('users')) {
            return null;
        }

        $user = DB::table('users')->where('id', $userId)->first(['surname', 'first_name', 'last_name', 'username']);
        if (! $user) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $user->surname ?? '',
            $user->first_name ?? '',
            $user->last_name ?? '',
        ])));

        return $name !== '' ? $name : ($user->username ?? null);
    }

    protected function decimal($value): float
    {
        return round((float) str_replace(',', '', (string) ($value ?? 0)), 2);
    }

    protected function importInterestRate($value): float
    {
        $rate = $this->decimal($value);
        if ($rate > 0 && $rate < 1) {
            $rate *= 100;
        }

        return round($rate, 2);
    }

    protected function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return gmdate('Y-m-d', (int) (((float) $value - 25569) * 86400));
        }

        $time = strtotime((string) $value);

        return $time ? date('Y-m-d', $time) : null;
    }
}
