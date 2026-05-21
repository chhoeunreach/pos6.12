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
            'schedules' => [
                'label' => 'Payment Schedules',
                'description' => 'Import or update installment due schedules for existing loans.',
            ],
            'payments' => [
                'label' => 'Monthly Payments',
                'description' => 'Import customer monthly installment collections.',
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
        ];
    }

    public function exportTypes(): array
    {
        return [
            'customers' => ['label' => 'Customers', 'description' => 'Loan customer master list.'],
            'loans' => ['label' => 'All Loans', 'description' => 'All loan account records.'],
            'active_loans' => ['label' => 'Active Loans', 'description' => 'Loans filtered to active/open status where available.'],
            'overdue_loans' => ['label' => 'Overdue Loans', 'description' => 'Loans marked overdue or with overdue workflow fields.'],
            'payments' => ['label' => 'Payment History', 'description' => 'All loan payment records.'],
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

        return $templates[$type] ?? $templates['loans'];
    }

    public function import(string $type, UploadedFile $file, ?int $userId = null, string $duplicateMode = 'skip'): array
    {
        $type = $this->normalizeType($type);
        $this->ensurePaymentTypeColumn();
        $duplicateMode = in_array($duplicateMode, ['skip', 'replace'], true) ? $duplicateMode : 'skip';
        $rows = $this->readImportFile($file, $type);
        $headers = array_shift($rows) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        if (empty($headers)) {
            throw new \RuntimeException('Import file does not contain a header row.');
        }

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
                    'loan_id' => in_array($type, ['loans', 'schedules', 'payments'], true) ? ($type === 'loans' ? $duplicateId : ($normalized['loan_id'] ?? null)) : null,
                    'error_message' => 'Skipped duplicate existing record.',
                    'updated_at' => now(),
                ]));
                $skipped++;
                continue;
            }

            try {
                $id = DB::connection($this->connection)->transaction(function () use ($type, $normalized, $duplicateMode, $duplicateId) {
                    return $type === 'payments'
                        ? $this->storePayment($normalized, $duplicateMode, $duplicateId)
                        : ($type === 'loans' ? $this->storeLoan($normalized, $duplicateMode, $duplicateId) : $this->storeGenericImport($type, $normalized, $duplicateMode, $duplicateId));
                });

                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => $duplicateId && $duplicateMode === 'replace' ? 'replaced' : 'imported',
                    'loan_id' => $type === 'loans' ? $id : ($normalized['loan_id'] ?? null),
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

    public function export(string $type, array $filters = [], ?int $userId = null): array
    {
        $type = $this->normalizeType($type);
        $columns = $this->exportColumns($type);
        $rows = $this->exportRows($type, $filters);

        $logId = $this->createExportLog($type, $filters, $userId, $rows->count());
        $filename = 'loan-management-'.$type.'-'.now()->format('Ymd-His').'.csv';
        $relativePath = 'Modules/LoanManagement/storage/exports/'.$filename;
        $absolutePath = $this->moduleStoragePath('exports', $filename);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $handle = fopen($absolutePath, 'w');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row->{$column} ?? '', $columns));
        }
        fclose($handle);

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

    public function template(string $type): array
    {
        $type = $this->normalizeType($type);
        $definition = $this->templateDetails($type);

        return [
            'filename' => $type.'-import-template.csv',
            'content' => $this->csvContent($definition['columns'], $definition['example']),
        ];
    }

    public function recentBatches(int $limit = 20, ?string $type = null)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return collect();
        }

        $query = DB::connection($this->connection)->table('loan_import_batches');
        if ($type !== null) {
            $query->where('file_type', $this->normalizeType($type));
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
            'customer' => 'customers',
            'loan' => 'loans',
            'loan_information' => 'loans',
            'loan_info' => 'loans',
            'full_loan_information' => 'loans',
            'full_loan_info' => 'loans',
            'all_loans' => 'loans',
            'schedule' => 'schedules',
            'payment_schedules' => 'schedules',
            'guarantor' => 'guarantors',
            'imei_serial' => 'imei',
            'imei_serial_items' => 'imei',
            'collection_assignment' => 'collection_assignments',
        ];

        return $aliases[$type] ?? $type;
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

        $preferred = $type === 'payments'
            ? ['monthly payment', 'monthly payments', 'payment']
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
            'បង់-លុយសុទ្ធ' => 'cash_amount',
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

    protected function templateDefinitions(): array
    {
        return [
            'customers' => [
                'columns' => ['customer_code', 'name', 'phone', 'email', 'address', 'id_number', 'gender', 'date_of_birth', 'status', 'note'],
                'required' => ['name or phone'],
                'optional' => ['customer_code', 'email', 'address', 'id_number', 'gender', 'date_of_birth', 'status', 'note'],
                'example' => ['CUS-0001', 'Sok Dara', '012345678', 'sok@example.com', 'Phnom Penh', '010101234', 'male', '1995-01-15', 'active', 'Imported customer'],
                'notes' => 'If customer_code or phone already exists, the importer updates that customer. Otherwise it creates a new one.',
            ],
            'loans' => [
                'columns' => [
                    'loan_number', 'location_id', 'location_name', 'customer_id', 'customer_code', 'customer_name', 'khmer_name', 'customer_phone',
                    'alternate_phone', 'email', 'id_number', 'gender', 'date_of_birth', 'address', 'province',
                    'district', 'commune', 'village', 'family_contact_name', 'family_contact_phone', 'spouse_name',
                    'spouse_phone', 'workplace', 'monthly_income', 'product_name', 'imei_or_serial', 'qty',
                    'unit_price', 'product_price', 'principal_amount', 'interest_amount', 'total_amount', 'paid_amount',
                    'balance_amount', 'down_payment', 'down_payment_cash', 'down_payment_bank', 'paid_date',
                    'payment_method', 'reference_number', 'installment_count', 'payment_frequency', 'loan_date',
                    'first_due_date', 'maturity_date', 'status', 'currency', 'collection_status', 'risk_level',
                    'assigned_collector_id', 'assigned_collection_team', 'days_past_due', 'overdue_bucket',
                    'next_followup_at', 'ptp_date', 'ptp_amount', 'note',
                ],
                'required' => ['loan_number', 'customer_id or customer_name', 'principal_amount or product_price'],
                'optional' => ['location_id or location_name', 'customer_code', 'customer_phone', 'address/id fields', 'product/item fields', 'payment totals', 'down payment method/date/reference', 'collection fields', 'currency', 'note'],
                'example' => ['LN-0001', 'PP01', 'Phnom Penh', '', 'CUS-0001', 'Sok Dara', '', '012345678', '', 'sok@example.com', '010101234', 'male', '1995-01-15', 'Phnom Penh', 'Phnom Penh', '', '', '', '', '', '', '', '', '', 'iPhone 12 Pro Max', '356789123456789', '1', '500.00', '500.00', '500.00', '0.00', '500.00', '200.00', '300.00', '200.00', '200.00', '0.00', now()->toDateString(), 'Cash', 'IMP-DOWN-LN-0001', '10', 'monthly', now()->toDateString(), now()->addMonth()->toDateString(), '', 'active', 'USD', 'normal', 'low', '', '', '0', '', '', '', '', 'Example: product price 500, down payment 200, balance 300'],
                'notes' => 'Creates the customer, loan, item snapshot, unpaid monthly schedules, and an initial loan/down-payment record when paid_amount or down_payment is greater than zero. Monthly collections must be imported separately with the payments template.',
            ],
            'schedules' => [
                'columns' => ['loan_number', 'loan_id', 'installment_no', 'due_date', 'principal_amount', 'interest_amount', 'penalty_amount', 'schedule_amount', 'paid_amount', 'status', 'note'],
                'required' => ['loan_number or loan_id', 'installment_no', 'due_date', 'schedule_amount or principal_amount'],
                'optional' => ['interest_amount', 'penalty_amount', 'paid_amount', 'status', 'note'],
                'example' => ['LN-0001', '', '1', now()->addMonth()->toDateString(), '50.00', '5.00', '0.00', '55.00', '0.00', 'unpaid', 'Imported schedule'],
                'notes' => 'Updates an existing loan schedule with same loan and installment_no when found, otherwise creates one.',
            ],
            'payments' => [
                'columns' => ['loan_number', 'loan_id', 'schedule_id', 'payment_type', 'amount', 'paid_date', 'payment_method', 'currency', 'exchange_rate', 'reference_number', 'note'],
                'required' => ['loan_number or loan_id', 'amount', 'paid_date'],
                'optional' => ['schedule_id', 'payment_type: loan or monthly', 'payment_method', 'currency', 'exchange_rate', 'reference_number', 'note'],
                'example' => ['LN-0001', '', '', 'monthly', '55.00', now()->toDateString(), 'Cash', 'USD', '1', 'PAY-EXAMPLE-001', 'Monthly installment payment'],
                'notes' => 'If schedule_id is empty, payment is applied to the oldest unpaid schedule for the loan.',
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
        if ($type === 'payments') {
            return $this->normalizePaymentRow($row);
        }
        if ($type === 'loans') {
            return $this->normalizeLoanRow($row);
        }

        $loanId = $this->resolveLoanId($row);
        $normalized = $row;
        $normalized['loan_id'] = $loanId ?: (int) ($row['loan_id'] ?? 0);

        if ($type === 'customers') {
            $normalized['name'] = $row['name'] ?? $row['customer_name'] ?? '';
            $normalized['phone'] = $row['phone'] ?? $row['customer_phone'] ?? '';
            $normalized['status'] = $row['status'] ?? 'active';
            $normalized['date_of_birth'] = $this->date($row['date_of_birth'] ?? $row['dob'] ?? null);
            $normalized['id_number'] = $row['id_number'] ?? $row['id_card_number'] ?? null;
        } elseif ($type === 'schedules') {
            $principal = $this->decimal($row['principal_amount'] ?? $row['principal_due'] ?? 0);
            $interest = $this->decimal($row['interest_amount'] ?? $row['interest_due'] ?? 0);
            $penalty = $this->decimal($row['penalty_amount'] ?? $row['penalty_due'] ?? 0);
            $amount = $this->decimal($row['schedule_amount'] ?? $row['amount_due'] ?? ($principal + $interest + $penalty));
            $paid = $this->decimal($row['paid_amount'] ?? $row['amount_paid'] ?? 0);
            $normalized = array_merge($normalized, [
                'installment_no' => (int) ($row['installment_no'] ?? 0),
                'due_date' => $this->date($row['due_date'] ?? null),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'penalty_amount' => $penalty,
                'schedule_amount' => $amount,
                'paid_amount' => $paid,
                'balance_amount' => max(0, $amount - $paid),
                'status' => $row['status'] ?? ($paid >= $amount && $amount > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')),
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
        if ($type === 'payments') {
            return $this->validatePaymentRow($row);
        }
        if ($type === 'loans') {
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
        }
        if ($type === 'guarantors' && empty($row['name'])) {
            $errors[] = 'name is required';
        }
        if ($type === 'imei' && empty($row['imei']) && empty($row['serial_no'])) {
            $errors[] = 'imei or serial_no is required';
        }

        return $errors;
    }

    protected function normalizeLoanRow(array $row): array
    {
        $qty = max(1, $this->decimal($row['qty'] ?? $row['quantity'] ?? 1));
        $unitPrice = $this->decimal($row['unit_price'] ?? 0);
        $principal = $this->decimal($row['principal_amount'] ?? $row['product_price'] ?? 0);
        if ($principal <= 0 && $unitPrice > 0) {
            $principal = round($unitPrice * $qty, 2);
        }
        $interest = $this->decimal($row['interest_amount'] ?? $row['total_interest'] ?? 0);
        $downPaymentCash = $this->decimal($row['down_payment_cash'] ?? 0);
        $downPaymentBank = $this->decimal($row['down_payment_bank'] ?? 0);
        $downPayment = $this->decimal($row['down_payment'] ?? ($downPaymentCash + $downPaymentBank));
        $total = $this->decimal($row['total_amount'] ?? ($principal + $interest));
        $paid = array_key_exists('paid_amount', $row) && $row['paid_amount'] !== ''
            ? $this->decimal($row['paid_amount'])
            : $downPayment;
        $balance = array_key_exists('balance_amount', $row) && $row['balance_amount'] !== ''
            ? $this->decimal($row['balance_amount'])
            : max(0, ($total > 0 ? $total : ($principal + $interest)) - $paid);
        $installments = max(1, (int) ($row['installment_count'] ?? $row['total_installment_months'] ?? 1));
        $location = $this->resolveImportLocation($row);

        return [
            'loan_number' => $row['loan_number'] ?? $this->nextLoanNumber(),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_code' => $row['customer_code'] ?? null,
            'customer_name' => $row['customer_name'] ?? $row['name'] ?? '',
            'khmer_name' => $row['khmer_name'] ?? null,
            'customer_phone' => $row['customer_phone'] ?? $row['phone'] ?? '',
            'alternate_phone' => $row['alternate_phone'] ?? null,
            'email' => $row['email'] ?? null,
            'telegram' => $row['telegram'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'id_number' => $row['id_number'] ?? $row['id_card_number'] ?? null,
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
            'workplace' => $row['workplace'] ?? null,
            'monthly_income' => $this->decimal($row['monthly_income'] ?? 0) ?: null,
            'customer_type' => $row['customer_type'] ?? null,
            'business_location_id' => $location['id'],
            'location_name' => $location['name'],
            'product_name' => $row['product_name'] ?? '',
            'imei_or_serial' => $row['imei_or_serial'] ?? $row['imei'] ?? $row['serial'] ?? '',
            'qty' => $qty,
            'unit_price' => $unitPrice > 0 ? $unitPrice : $principal,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_amount' => $total > 0 ? $total : ($principal + $interest),
            'down_payment' => $downPayment,
            'down_payment_cash' => $downPaymentCash,
            'down_payment_bank' => $downPaymentBank,
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'initial_payment_method' => $row['payment_method'] ?? $row['method'] ?? null,
            'initial_payment_date' => $this->date($row['paid_date'] ?? $row['payment_date'] ?? $row['loan_date'] ?? null),
            'initial_payment_reference' => $row['reference_number'] ?? $row['payment_ref_no'] ?? null,
            'installment_count' => $installments,
            'payment_frequency' => $row['payment_frequency'] ?? 'monthly',
            'loan_date' => $this->date($row['loan_date'] ?? null),
            'first_due_date' => $this->date($row['first_due_date'] ?? null),
            'maturity_date' => $this->date($row['maturity_date'] ?? null),
            'status' => $row['status'] ?? 'active',
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
            'note' => $row['note'] ?? null,
            'raw_import_row' => $row,
        ];
    }

    protected function normalizePaymentRow(array $row): array
    {
        $loanId = (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0 && ! empty($row['loan_number'])) {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->value('id');
        }
        $amount = $this->decimal($row['amount'] ?? $row['paid_amount'] ?? 0);
        if ($amount <= 0) {
            $amount = $this->decimal($row['cash_amount'] ?? 0)
                + $this->decimal($row['bank_amount'] ?? 0)
                + $this->decimal($row['payoff_amount'] ?? 0);
        }
        $method = $row['payment_method'] ?? $row['method'] ?? $row['channel'] ?? '';
        if ($method === '') {
            $method = $this->decimal($row['bank_amount'] ?? 0) > 0 ? 'Bank' : 'Cash';
        }

        return [
            'loan_id' => $loanId,
            'schedule_id' => (int) ($row['schedule_id'] ?? 0) ?: null,
            'payment_type' => $this->normalizePaymentType($row['payment_type'] ?? 'monthly'),
            'amount' => $amount,
            'paid_date' => $this->date($row['paid_date'] ?? $row['paid_at'] ?? null),
            'payment_method' => $method,
            'currency' => $row['currency'] ?? 'USD',
            'exchange_rate' => $this->decimal($row['exchange_rate'] ?? 1),
            'reference_number' => $row['reference_number'] ?? $row['payment_ref_no'] ?? null,
            'note' => $row['note'] ?? null,
        ];
    }

    protected function validateLoanRow(array $row, string $duplicateMode = 'skip'): array
    {
        $errors = [];
        if (empty($row['loan_number'])) $errors[] = 'loan_number is required';
        if ($duplicateMode !== 'skip' && $duplicateMode !== 'replace' && DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->exists()) $errors[] = 'loan_number already exists';
        if (empty($row['customer_id']) && empty($row['customer_name'])) $errors[] = 'customer_id or customer_name is required';
        if ($row['principal_amount'] <= 0) $errors[] = 'principal_amount must be greater than 0';

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

        return [
            'id' => $location ? (int) $location->id : ($locationId > 0 ? $locationId : null),
            'name' => $location ? (string) ($location->name ?? $locationName) : ($locationName !== '' ? $locationName : null),
        ];
    }

    protected function validatePaymentRow(array $row): array
    {
        $errors = [];
        if (empty($row['loan_id'])) $errors[] = 'loan_id or loan_number is required';
        if (! empty($row['loan_id']) && ! DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->exists()) $errors[] = 'loan not found';
        if ($row['amount'] <= 0) $errors[] = 'amount must be greater than 0';
        if (empty($row['paid_date'])) $errors[] = 'paid_date is required';
        if (! in_array($row['payment_type'] ?? 'monthly', ['loan', 'monthly'], true)) $errors[] = 'payment_type must be loan or monthly';

        return $errors;
    }

    protected function normalizePaymentType($value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['loan', 'initial', 'down_payment', 'downpayment', 'deposit'], true) ? 'loan' : 'monthly';
    }

    protected function storeLoan(array $row, string $duplicateMode = 'skip', ?int $existingLoanId = null): int
    {
        $customerId = $row['customer_id'] ?: $this->firstOrCreateCustomer($row);
        if ($customerId > 0) {
            $this->updateImportedCustomer($customerId, $row);
        }

        $payload = $this->safeColumns('loans', [
            'loan_number' => $row['loan_number'],
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
            'principal_amount' => $row['principal_amount'],
            'interest_amount' => $row['interest_amount'],
            'total_amount' => $row['total_amount'],
            'paid_amount' => $row['paid_amount'],
            'balance_amount' => $row['balance_amount'],
            'down_payment' => $row['down_payment'],
            'installment_count' => $row['installment_count'],
            'payment_frequency' => $row['payment_frequency'],
            'loan_date' => $row['loan_date'],
            'first_due_date' => $row['first_due_date'],
            'maturity_date' => $row['maturity_date'],
            'status' => $row['status'],
            'currency' => $row['currency'],
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
            $this->createImportedSchedules($loanId, $row, true);
            $this->createImportedInitialPayment($loanId, $row, true);
            $this->refreshLoanTotals($loanId);

            return $loanId;
        }

        $payload['created_at'] = now();
        $loanId = (int) DB::connection($this->connection)->table('loans')->insertGetId($payload);
        $this->createImportedLoanItem($loanId, $row);
        $this->createImportedSchedules($loanId, $row);
        if ($this->createImportedInitialPayment($loanId, $row)) {
            $this->refreshLoanTotals($loanId);
        }

        return $loanId;
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
            'payment_type' => 'loan',
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
        $scheduleId = $row['schedule_id'] ?: $this->oldestOpenScheduleId((int) $loan->id);
        $paidAt = $row['paid_date'].' '.now()->format('H:i:s');
        $paymentRef = $row['reference_number'] ?: 'IMP-PAY-'.now()->format('YmdHis').'-'.Str::random(4);

        $payload = $this->safeColumns('loan_payments', [
            'payment_ref_no' => $paymentRef,
            'receipt_number' => $paymentRef,
            'loan_id' => $loan->id,
            'payment_type' => $row['payment_type'],
            'customer_id' => $loan->customer_id ?? null,
            'schedule_id' => $scheduleId,
            'channel' => $row['payment_method'],
            'payment_method_snapshot' => $row['payment_method'],
            'amount' => $row['amount'],
            'total_paid' => $row['amount'],
            'total_paid_base' => $row['amount'] * max(1, $row['exchange_rate']),
            'base_currency' => $row['currency'],
            'paid_date' => $row['paid_date'],
            'paid_at' => $paidAt,
            'status' => 'confirmed',
            'reference_number' => $row['reference_number'],
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
                'reference_number' => $row['reference_number'],
                'transaction_no' => $row['reference_number'],
                'note' => $row['note'],
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        if (! $existingPaymentId || $duplicateMode !== 'replace') {
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
                }
                return (int) $existing;
            }
        }

        return (int) DB::connection($this->connection)->table($table)->insertGetId($this->safeColumns($table, $payload));
    }

    protected function storeGenericCustomer(array $row): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            throw new \RuntimeException('loan_customers table is not available.');
        }

        $query = DB::connection($this->connection)->table('loan_customers');
        $existing = null;
        if (! empty($row['customer_code']) && $this->hasColumn('loan_customers', 'customer_code')) {
            $existing = (clone $query)->where('customer_code', $row['customer_code'])->value('id');
        }
        if (! $existing && ! empty($row['phone']) && $this->hasColumn('loan_customers', 'phone')) {
            $existing = (clone $query)->where('phone', $row['phone'])->value('id');
        }

        $payload = $this->safeColumns('loan_customers', [
            'customer_code' => $row['customer_code'] ?? ('IMP-CUS-'.now()->format('YmdHis').'-'.Str::random(4)),
            'name' => $row['name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'address' => $row['address'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'id_card_number' => $row['id_number'] ?? null,
            'gender' => $row['gender'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'status' => $row['status'] ?? 'active',
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
            'customer_code' => $row['customer_code'] ?: 'IMP-CUS-'.now()->format('YmdHis').'-'.Str::random(4),
            'name' => $row['customer_name'],
            'khmer_name' => $row['khmer_name'],
            'phone' => $row['customer_phone'],
            'alternate_phone' => $row['alternate_phone'],
            'email' => $row['email'],
            'telegram' => $row['telegram'],
            'facebook' => $row['facebook'],
            'id_card_number' => $row['id_number'],
            'id_number' => $row['id_number'],
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
            'khmer_name' => $row['khmer_name'],
            'phone' => $row['customer_phone'],
            'alternate_phone' => $row['alternate_phone'],
            'email' => $row['email'],
            'telegram' => $row['telegram'],
            'facebook' => $row['facebook'],
            'id_card_number' => $row['id_number'],
            'id_number' => $row['id_number'],
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

    protected function createImportedLoanItem(int $loanId, array $row): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_items') || empty($row['product_name'])) {
            return;
        }

        DB::connection($this->connection)->table('loan_items')->insert($this->safeColumns('loan_items', [
            'loan_id' => $loanId,
            'product_name_snapshot' => $row['product_name'],
            'imei_snapshot' => $row['imei_or_serial'],
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

    protected function createImportedSchedules(int $loanId, array $row, bool $replaceExisting = false): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        $months = max(1, (int) $row['installment_count']);
        $schedulePrincipalTotal = max(0, round((float) $row['principal_amount'], 2));
        $scheduleTotal = round($schedulePrincipalTotal + (float) $row['interest_amount'], 2);
        $principal = $row['monthly_principal'] > 0 ? $row['monthly_principal'] : round($schedulePrincipalTotal / $months, 2);
        $interest = $row['monthly_interest'] > 0 ? $row['monthly_interest'] : round($row['interest_amount'] / $months, 2);
        $monthlyPayment = $row['monthly_payment'] > 0 ? $row['monthly_payment'] : 0;
        $useMonthlyPayment = $monthlyPayment > 0 && ($monthlyPayment * ($months - 1)) < $scheduleTotal;
        $dueDate = $row['first_due_date'] ?: now()->addMonth()->toDateString();
        $principalAssigned = 0;
        $interestAssigned = 0;

        for ($i = 1; $i <= $months; $i++) {
            $remainingPrincipal = max(0, round($schedulePrincipalTotal - $principalAssigned, 2));
            $remainingInterest = max(0, round($row['interest_amount'] - $interestAssigned, 2));
            $principalDue = $i === $months ? $remainingPrincipal : min($principal, $remainingPrincipal);
            $interestDue = $i === $months ? $remainingInterest : min($interest, $remainingInterest);
            $amountDue = $useMonthlyPayment && $i < $months
                ? $monthlyPayment
                : round($principalDue + $interestDue, 2);
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
        $paid = (float) DB::connection($this->connection)->table('loan_payments')->where('loan_id', $loanId)->sum($this->paymentAmountColumn());
        $loan = DB::connection($this->connection)->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }
        $total = (float) ($loan->total_amount ?? 0);
        if ($total <= 0) {
            $total = (float) ($loan->principal_amount ?? 0) + (float) ($loan->interest_amount ?? 0);
        }

        DB::connection($this->connection)->table('loans')->where('id', $loanId)->update($this->safeColumns('loans', [
            'paid_amount' => $paid,
            'balance_amount' => max(0, $total - $paid),
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
        if ($type === 'loans' && ! empty($row['loan_number']) && Schema::connection($this->connection)->hasTable('loans')) {
            $id = DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->value('id');
            return $id ? (int) $id : null;
        }

        if ($type === 'schedules' && ! empty($row['loan_id']) && ! empty($row['installment_no']) && Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            $id = DB::connection($this->connection)->table('loan_payment_schedules')
                ->where('loan_id', $row['loan_id'])
                ->where('installment_no', $row['installment_no'])
                ->value('id');
            return $id ? (int) $id : null;
        }

        if ($type === 'payments' && ! empty($row['reference_number']) && Schema::connection($this->connection)->hasTable('loan_payments')) {
            $columns = array_values(array_filter(['payment_ref_no', 'receipt_number', 'reference_number'], fn ($column) => $this->hasColumn('loan_payments', $column)));
            if (empty($columns)) {
                return null;
            }
            $query = DB::connection($this->connection)->table('loan_payments');
            $reference = $row['reference_number'];
            $query->where(function ($where) use ($columns, $reference) {
                foreach ($columns as $column) {
                    $where->orWhere($column, $reference);
                }
            });
            $id = $query->value('id');
            return $id ? (int) $id : null;
        }

        return null;
    }

    protected function exportRows(string $type, array $filters)
    {
        if (in_array($type, ['loans', 'active_loans', 'overdue_loans', 'collection_assignments'], true)) {
            return $this->loanExportRows($filters, $type);
        }
        if (in_array($type, ['payments', 'monthly_collections'], true)) {
            return $this->paymentExportRows($filters);
        }

        $table = [
            'customers' => 'loan_customers',
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

    protected function loanExportRows(array $filters, string $type = 'loans')
    {
        $query = DB::connection($this->connection)->table('loans');
        $this->applyCommonFilters($query, $filters, 'loans');

        if ($type === 'active_loans' && $this->hasColumn('loans', 'status')) {
            $query->whereIn('status', ['active', 'open', 'partial']);
        }
        if ($type === 'overdue_loans') {
            $hasCollectionStatus = $this->hasColumn('loans', 'collection_status');
            $hasDaysPastDue = $this->hasColumn('loans', 'days_past_due');
            $hasOverdueBucket = $this->hasColumn('loans', 'overdue_bucket');
            if ($hasCollectionStatus || $hasDaysPastDue || $hasOverdueBucket) {
                $query->where(function ($where) use ($hasCollectionStatus, $hasDaysPastDue, $hasOverdueBucket) {
                    if ($hasCollectionStatus) {
                        $where->orWhereIn('collection_status', ['overdue', 'delinquent']);
                    }
                    if ($hasDaysPastDue) {
                        $where->orWhere('days_past_due', '>', 0);
                    }
                    if ($hasOverdueBucket) {
                        $where->orWhereNotNull('overdue_bucket');
                    }
                });
            }
        }

        return $query->select($this->safeSelect('loans', $this->exportColumns($type)))->orderByDesc('id')->get();
    }

    protected function paymentExportRows(array $filters)
    {
        $query = DB::connection($this->connection)->table('loan_payments');
        $this->applyCommonFilters($query, $filters, 'loan_payments');

        return $query->select($this->safeSelect('loan_payments', $this->exportColumns('payments')))->orderByDesc('id')->get();
    }

    protected function applyCommonFilters($query, array $filters, string $table): void
    {
        if (! empty($filters['status']) && $this->hasColumn($table, 'status')) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $column = $this->dateColumn($table);
            if ($column) $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $column = $this->dateColumn($table);
            if ($column) $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    protected function exportColumns(string $type): array
    {
        $columns = [
            'customers' => ['id', 'customer_code', 'name', 'phone', 'email', 'address', 'id_number', 'gender', 'date_of_birth', 'status', 'blacklist_status', 'created_at', 'updated_at'],
            'loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'product_name_snapshot', 'imei_snapshot', 'principal_amount', 'interest_amount', 'total_amount', 'paid_amount', 'balance_amount', 'down_payment', 'installment_count', 'loan_date', 'first_due_date', 'status', 'currency', 'collection_status', 'risk_level', 'assigned_collector_id', 'days_past_due', 'overdue_bucket', 'note'],
            'active_loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'installment_count', 'loan_date', 'status', 'collection_status', 'assigned_collector_id'],
            'overdue_loans' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'total_amount', 'paid_amount', 'balance_amount', 'days_past_due', 'overdue_bucket', 'collection_status', 'risk_level', 'next_followup_at', 'assigned_collector_id'],
            'payments' => ['id', 'payment_ref_no', 'receipt_number', 'loan_id', 'payment_type', 'customer_id', 'schedule_id', 'channel', 'payment_method_snapshot', 'amount', 'total_paid', 'total_paid_base', 'paid_date', 'paid_at', 'status', 'reference_number', 'note'],
            'monthly_collections' => ['id', 'payment_ref_no', 'loan_id', 'payment_type', 'customer_id', 'schedule_id', 'channel', 'amount', 'total_paid', 'paid_date', 'paid_at', 'status', 'reference_number'],
            'schedules' => ['id', 'loan_id', 'installment_no', 'due_date', 'principal_amount', 'principal_due', 'interest_amount', 'interest_due', 'schedule_amount', 'amount_due', 'paid_amount', 'amount_paid', 'balance_amount', 'amount_balance', 'status', 'paid_at'],
            'guarantors' => ['id', 'loan_id', 'customer_id', 'name', 'guarantor_name', 'phone', 'guarantor_phone', 'relation', 'address', 'id_number', 'status', 'note'],
            'imei' => ['id', 'loan_id', 'product_name', 'product_name_snapshot', 'imei', 'imei_snapshot', 'serial_no', 'serial_number_snapshot', 'qty', 'quantity', 'unit_price', 'line_total', 'status', 'note'],
            'collection_assignments' => ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'assigned_collector_id', 'assigned_collection_team', 'collection_status', 'risk_level', 'next_followup_at', 'ptp_date', 'ptp_amount', 'days_past_due', 'overdue_bucket', 'note'],
        ];

        return $columns[$type] ?? $columns['loans'];
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

        $path = $this->storeUploadedFileInModule($file, 'imports');

        return (int) DB::connection($this->connection)->table('loan_import_batches')->insertGetId($this->safeColumns('loan_import_batches', [
            'batch_code' => 'IMP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $type,
            'uploaded_by' => $userId,
            'status' => 'processing',
            'column_mapping_json' => json_encode($headers),
            'total_rows' => $totalRows,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
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

    protected function createExportLog(string $type, array $filters, ?int $userId, int $rowsCount): ?int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_export_logs')) {
            return null;
        }

        return (int) DB::connection($this->connection)->table('loan_export_logs')->insertGetId($this->safeColumns('loan_export_logs', [
            'export_type' => $type,
            'format' => 'csv',
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

        return 'Modules/LoanManagement/storage/'.$directory.'/'.$filename;
    }

    protected function moduleStoragePath(string $directory, string $filename): string
    {
        return base_path('Modules/LoanManagement/storage/'.$directory.'/'.$filename);
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

    protected function decimal($value): float
    {
        return round((float) str_replace(',', '', (string) ($value ?? 0)), 2);
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
