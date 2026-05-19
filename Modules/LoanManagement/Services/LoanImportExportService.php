<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanImportExportService
{
    protected $connection = 'mysql_loan';

    public function import(string $type, UploadedFile $file, ?int $userId = null): array
    {
        $type = $this->normalizeType($type);
        $rows = $this->readCsv($file->getRealPath());
        $headers = array_shift($rows) ?: [];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        if (empty($headers)) {
            throw new \RuntimeException('Import file does not contain a header row.');
        }

        $batchId = $this->createBatch($type, $file, $userId, count($rows), $headers);
        $valid = 0;
        $invalid = 0;
        $imported = 0;

        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $raw = $this->combineRow($headers, $row);
            $normalized = $type === 'payments'
                ? $this->normalizePaymentRow($raw)
                : $this->normalizeLoanRow($raw);
            $errors = $type === 'payments'
                ? $this->validatePaymentRow($normalized)
                : $this->validateLoanRow($normalized);

            $rowId = $this->createImportRow($batchId, $index + 2, $raw, $normalized, $errors);

            if (! empty($errors)) {
                $invalid++;
                continue;
            }

            try {
                $id = DB::connection($this->connection)->transaction(function () use ($type, $normalized) {
                    return $type === 'payments'
                        ? $this->storePayment($normalized)
                        : $this->storeLoan($normalized);
                });

                DB::connection($this->connection)->table('loan_import_rows')->where('id', $rowId)->update($this->safeColumns('loan_import_rows', [
                    'status' => 'imported',
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
            'total_rows' => $valid + $invalid,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'imported_rows' => $imported,
        ];
    }

    public function export(string $type, array $filters = [], ?int $userId = null): array
    {
        $type = $this->normalizeType($type);
        $columns = $this->exportColumns($type);
        $rows = $type === 'payments'
            ? $this->paymentExportRows($filters)
            : $this->loanExportRows($filters);

        $logId = $this->createExportLog($type, $filters, $userId, $rows->count());
        $filename = 'loan-management-'.$type.'-'.now()->format('Ymd-His').'.csv';
        $relativePath = 'loan-management/exports/'.$filename;
        $absolutePath = Storage::path($relativePath);

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
        if ($type === 'payments') {
            $columns = ['loan_number', 'schedule_id', 'amount', 'paid_date', 'payment_method', 'currency', 'exchange_rate', 'reference_number', 'note'];
            $example = ['LN-0001', '', '55.00', now()->toDateString(), 'Cash', 'USD', '1', 'PAY-EXAMPLE-001', 'Monthly installment payment'];

            return [
                'filename' => 'monthly-payments-import-template.csv',
                'content' => $this->csvContent($columns, $example),
            ];
        }

        return [
            'filename' => 'loans-import-template.csv',
            'content' => $this->csvContent(
                ['loan_number', 'customer_id', 'customer_name', 'customer_phone', 'product_name', 'imei_or_serial', 'principal_amount', 'interest_amount', 'down_payment', 'installment_count', 'loan_date', 'first_due_date', 'status', 'currency', 'note'],
                ['LN-0001', '', 'Sok Dara', '012345678', 'iPhone 12 Pro Max', '356789123456789', '500.00', '50.00', '100.00', '10', now()->toDateString(), now()->addMonth()->toDateString(), 'active', 'USD', 'Example imported loan']
            ),
        ];
    }

    public function recentBatches(int $limit = 20)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return collect();
        }

        return DB::connection($this->connection)->table('loan_import_batches')->orderByDesc('id')->limit($limit)->get();
    }

    public function recentExports(int $limit = 20)
    {
        if (! Schema::connection($this->connection)->hasTable('loan_export_logs')) {
            return collect();
        }

        return DB::connection($this->connection)->table('loan_export_logs')->orderByDesc('id')->limit($limit)->get();
    }

    protected function normalizeType(string $type): string
    {
        return in_array($type, ['payment', 'payments', 'monthly_payments'], true) ? 'payments' : 'loans';
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

    protected function normalizeLoanRow(array $row): array
    {
        $principal = $this->decimal($row['principal_amount'] ?? $row['product_price'] ?? 0);
        $interest = $this->decimal($row['interest_amount'] ?? $row['total_interest'] ?? 0);
        $downPayment = $this->decimal($row['down_payment'] ?? 0);

        return [
            'loan_number' => $row['loan_number'] ?? $this->nextLoanNumber(),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_name' => $row['customer_name'] ?? $row['name'] ?? '',
            'customer_phone' => $row['customer_phone'] ?? $row['phone'] ?? '',
            'product_name' => $row['product_name'] ?? '',
            'imei_or_serial' => $row['imei_or_serial'] ?? $row['imei'] ?? $row['serial'] ?? '',
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_amount' => $principal + $interest,
            'down_payment' => $downPayment,
            'paid_amount' => $downPayment,
            'balance_amount' => max(0, ($principal + $interest) - $downPayment),
            'installment_count' => max(1, (int) ($row['installment_count'] ?? $row['total_installment_months'] ?? 1)),
            'loan_date' => $this->date($row['loan_date'] ?? null),
            'first_due_date' => $this->date($row['first_due_date'] ?? null),
            'status' => $row['status'] ?? 'active',
            'currency' => $row['currency'] ?? 'USD',
            'note' => $row['note'] ?? null,
        ];
    }

    protected function normalizePaymentRow(array $row): array
    {
        $loanId = (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0 && ! empty($row['loan_number'])) {
            $loanId = (int) DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->value('id');
        }

        return [
            'loan_id' => $loanId,
            'schedule_id' => (int) ($row['schedule_id'] ?? 0) ?: null,
            'amount' => $this->decimal($row['amount'] ?? $row['paid_amount'] ?? 0),
            'paid_date' => $this->date($row['paid_date'] ?? $row['paid_at'] ?? null),
            'payment_method' => $row['payment_method'] ?? $row['method'] ?? $row['channel'] ?? 'Cash',
            'currency' => $row['currency'] ?? 'USD',
            'exchange_rate' => $this->decimal($row['exchange_rate'] ?? 1),
            'reference_number' => $row['reference_number'] ?? $row['payment_ref_no'] ?? null,
            'note' => $row['note'] ?? null,
        ];
    }

    protected function validateLoanRow(array $row): array
    {
        $errors = [];
        if (empty($row['loan_number'])) $errors[] = 'loan_number is required';
        if (DB::connection($this->connection)->table('loans')->where('loan_number', $row['loan_number'])->exists()) $errors[] = 'loan_number already exists';
        if (empty($row['customer_id']) && empty($row['customer_name'])) $errors[] = 'customer_id or customer_name is required';
        if ($row['principal_amount'] <= 0) $errors[] = 'principal_amount must be greater than 0';

        return $errors;
    }

    protected function validatePaymentRow(array $row): array
    {
        $errors = [];
        if (empty($row['loan_id'])) $errors[] = 'loan_id or loan_number is required';
        if (! empty($row['loan_id']) && ! DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->exists()) $errors[] = 'loan not found';
        if ($row['amount'] <= 0) $errors[] = 'amount must be greater than 0';
        if (empty($row['paid_date'])) $errors[] = 'paid_date is required';

        return $errors;
    }

    protected function storeLoan(array $row): int
    {
        $customerId = $row['customer_id'] ?: $this->firstOrCreateCustomer($row);
        $payload = $this->safeColumns('loans', [
            'loan_number' => $row['loan_number'],
            'customer_id' => $customerId,
            'customer_name_snapshot' => $row['customer_name'],
            'customer_phone_snapshot' => $row['customer_phone'],
            'product_name_snapshot' => $row['product_name'],
            'imei_snapshot' => $row['imei_or_serial'],
            'principal_amount' => $row['principal_amount'],
            'interest_amount' => $row['interest_amount'],
            'total_amount' => $row['total_amount'],
            'paid_amount' => $row['paid_amount'],
            'balance_amount' => $row['balance_amount'],
            'down_payment' => $row['down_payment'],
            'installment_count' => $row['installment_count'],
            'loan_date' => $row['loan_date'],
            'first_due_date' => $row['first_due_date'],
            'status' => $row['status'],
            'currency' => $row['currency'],
            'note' => $row['note'],
            'source_type' => 'import',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loanId = (int) DB::connection($this->connection)->table('loans')->insertGetId($payload);
        $this->createImportedLoanItem($loanId, $row);
        $this->createImportedSchedules($loanId, $row);

        return $loanId;
    }

    protected function storePayment(array $row): int
    {
        $loan = DB::connection($this->connection)->table('loans')->where('id', $row['loan_id'])->first();
        $scheduleId = $row['schedule_id'] ?: $this->oldestOpenScheduleId((int) $loan->id);
        $paidAt = $row['paid_date'].' '.now()->format('H:i:s');
        $paymentRef = $row['reference_number'] ?: 'IMP-PAY-'.now()->format('YmdHis').'-'.Str::random(4);

        $paymentId = (int) DB::connection($this->connection)->table('loan_payments')->insertGetId($this->safeColumns('loan_payments', [
            'payment_ref_no' => $paymentRef,
            'receipt_number' => $paymentRef,
            'loan_id' => $loan->id,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        if (Schema::connection($this->connection)->hasTable('loan_payment_details')) {
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

        $this->applyPaymentToSchedules((int) $loan->id, $scheduleId ? (int) $scheduleId : null, $row['amount'], $paidAt);
        $this->refreshLoanTotals((int) $loan->id);

        return $paymentId;
    }

    protected function firstOrCreateCustomer(array $row): int
    {
        if (! Schema::connection($this->connection)->hasTable('loan_customers')) {
            return 0;
        }

        $query = DB::connection($this->connection)->table('loan_customers');
        if (! empty($row['customer_phone'])) {
            $existing = (clone $query)->where('phone', $row['customer_phone'])->value('id');
            if ($existing) return (int) $existing;
        }

        return (int) DB::connection($this->connection)->table('loan_customers')->insertGetId($this->safeColumns('loan_customers', [
            'customer_code' => 'IMP-CUS-'.now()->format('YmdHis').'-'.Str::random(4),
            'name' => $row['customer_name'],
            'phone' => $row['customer_phone'],
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
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
            'qty' => 1,
            'unit_price' => $row['principal_amount'],
            'line_total' => $row['principal_amount'],
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function createImportedSchedules(int $loanId, array $row): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        $months = max(1, (int) $row['installment_count']);
        $principal = round($row['principal_amount'] / $months, 2);
        $interest = round($row['interest_amount'] / $months, 2);
        $dueDate = $row['first_due_date'] ?: now()->addMonth()->toDateString();
        $paidRemaining = $row['down_payment'];
        $principalAssigned = 0;
        $interestAssigned = 0;

        for ($i = 1; $i <= $months; $i++) {
            $principalDue = $i === $months ? round($row['principal_amount'] - $principalAssigned, 2) : $principal;
            $interestDue = $i === $months ? round($row['interest_amount'] - $interestAssigned, 2) : $interest;
            $amountDue = round($principalDue + $interestDue, 2);
            $paid = min($paidRemaining, $amountDue);
            $balance = max(0, $amountDue - $paid);
            DB::connection($this->connection)->table('loan_payment_schedules')->insert($this->safeColumns('loan_payment_schedules', [
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
                'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'paid_at' => $balance <= 0 ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $principalAssigned = round($principalAssigned + $principalDue, 2);
            $interestAssigned = round($interestAssigned + $interestDue, 2);
            $paidRemaining = max(0, $paidRemaining - $amountDue);
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

    protected function loanExportRows(array $filters)
    {
        $query = DB::connection($this->connection)->table('loans');
        $this->applyCommonFilters($query, $filters, 'loans');

        return $query->select($this->safeSelect('loans', $this->exportColumns('loans')))->orderByDesc('id')->get();
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
        return $type === 'payments'
            ? ['id', 'payment_ref_no', 'loan_id', 'customer_id', 'schedule_id', 'channel', 'amount', 'paid_at', 'status', 'note']
            : ['id', 'loan_number', 'customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'product_name_snapshot', 'imei_snapshot', 'principal_amount', 'interest_amount', 'total_amount', 'paid_amount', 'balance_amount', 'installment_count', 'loan_date', 'status', 'currency'];
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

        $path = $file->store('loan-management/imports');

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

    protected function decimal($value): float
    {
        return round((float) str_replace(',', '', (string) ($value ?? 0)), 2);
    }

    protected function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $time = strtotime((string) $value);

        return $time ? date('Y-m-d', $time) : null;
    }
}
