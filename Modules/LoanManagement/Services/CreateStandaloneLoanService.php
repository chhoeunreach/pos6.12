<?php

namespace Modules\LoanManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CreateStandaloneLoanService
{
    public function searchCustomers(string $keyword): \Illuminate\Support\Collection
    {
        $query = DB::connection('mysql_loan')->table('loan_customers')
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('phone', 'like', '%'.$keyword.'%')
                    ->orWhere('customer_code', 'like', '%'.$keyword.'%');
                if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'khmer_name')) {
                    $q->orWhere('khmer_name', 'like', '%'.$keyword.'%');
                }
            })
            ->whereNull('deleted_at');

        if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'status')) {
            $query->where('status', 'active');
        }

        $select = [
            'id', 'customer_code', 'name', 'phone', 'address',
            'email', 'gender', 'date_of_birth', 'id_card_number',
        ];
        if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'khmer_name')) {
            $select[] = 'khmer_name';
        }
        if (Schema::connection('mysql_loan')->hasColumn('loan_customers', 'alternate_phone')) {
            $select[] = 'alternate_phone';
        }
        foreach (['province', 'province_code', 'district', 'district_code', 'commune', 'commune_code', 'village', 'village_code'] as $column) {
            if (Schema::connection('mysql_loan')->hasColumn('loan_customers', $column)) {
                $select[] = $column;
            }
        }

        return $query->select($select)->limit(20)->get();
    }

    public function getCustomerById(int $customerId): ?object
    {
        return DB::connection('mysql_loan')->table('loan_customers')
            ->where('id', $customerId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function previewSchedule(array $data): array
    {
        $data = $this->normalizeStandaloneLoanAmounts($data);
        $principal = (float) ($data['principal_amount'] ?? 0);
        $months = max(1, (int) ($data['duration_months'] ?? 1));
        $rate = (float) ($data['interest_rate'] ?? 0) / 100;
        $interestType = in_array(($data['interest_type'] ?? 'flat'), ['flat', 'reducing_balance'], true)
            ? $data['interest_type']
            : 'flat';
        $firstDue = Carbon::parse($data['first_due_date'] ?? Carbon::today()->addMonth()->toDateString());
        $frequency = $data['payment_frequency'] ?? 'monthly';

        $rows = [];
        $remaining = $principal;
        $principalPer = round($principal / $months, 2);
        $flatInterestPer = round($principal * $rate, 2);

        for ($i = 1; $i <= $months; $i++) {
            if ($frequency === 'weekly') {
                $dueDate = $firstDue->copy()->addWeeks($i - 1)->toDateString();
            } elseif ($frequency === 'daily') {
                $dueDate = $firstDue->copy()->addDays($i - 1)->toDateString();
            } else {
                $dueDate = $firstDue->copy()->addMonths($i - 1)->toDateString();
            }

            $principalPart = ($i === $months) ? round($remaining, 2) : $principalPer;
            $interest = $interestType === 'reducing_balance'
                ? round($remaining * $rate, 2)
                : $flatInterestPer;
            $total = round($principalPart + $interest, 2);
            $remaining = max(0, round($remaining - $principalPart, 2));

            $rows[] = [
                'schedule_no' => $i,
                'due_date' => $dueDate,
                'principal' => $principalPart,
                'interest' => $interest,
                'total' => $total,
                'balance' => $remaining,
            ];
        }

        return $rows;
    }

    public function createStandaloneLoan(array $data): int
    {
        $data = $this->normalizeStandaloneLoanAmounts($data);

        $loanId = DB::connection('mysql_loan')->transaction(function () use ($data) {
            $customerId = $this->resolveOrCreateCustomer($data);

            $effectiveDownPayment = $this->totalInitialPaymentAmount($data);
            if ($effectiveDownPayment <= 0) {
                $effectiveDownPayment = (float) ($data['down_payment'] ?? 0);
            }
            $durationMonths = max(1, (int) ($data['duration_months'] ?? 1));
            $interestRate = max(0, (float) ($data['interest_rate'] ?? 0));
            $resolvedInterestType = in_array(($data['interest_type'] ?? 'flat'), ['flat', 'reducing_balance'], true)
                ? $data['interest_type']
                : 'flat';

            $loanMeta = [
                'interest_rate' => $interestRate,
                'interest_type' => $resolvedInterestType,
                'duration_months' => $durationMonths,
                'payment_frequency' => (string) ($data['payment_frequency'] ?? 'monthly'),
                'first_due_date' => $data['first_due_date'] ?? null,
            ];

            $schedule = $this->previewSchedule($data);
            $scheduleInterestTotal = round(collect($schedule)->sum('interest'), 2);
            $scheduleAmountTotal = round(collect($schedule)->sum('total'), 2);

            $requestedLoanNumber = trim((string) ($data['loan_number'] ?? ''));
            if ($requestedLoanNumber !== '' && $this->loanNumberExists($requestedLoanNumber)) {
                throw new \RuntimeException('Loan invoice number already exists.');
            }

            $locationId = $this->resolveLocationId($data);
            $resolvedCustomerName = $this->resolveCustomerDisplayName($data);
            $resolvedCustomerPhone = trim((string) ($data['customer_phone'] ?? ''));
            $resolvedCustomerAddress = $this->resolveCustomerAddress($data);

            $loanPayload = $this->filterColumns('loans', array_merge([
                'loan_number' => $requestedLoanNumber !== '' ? $requestedLoanNumber : $this->generateUniqueLoanNumber($locationId),
                'customer_id' => $customerId,
                'customer_name_snapshot' => $resolvedCustomerName,
                'customer_phone_snapshot' => $resolvedCustomerPhone,
                'customer_address_snapshot' => $resolvedCustomerAddress,
                'customer_province_snapshot' => trim((string) ($data['province_name'] ?? '')),
                'customer_province_code_snapshot' => trim((string) ($data['province_code'] ?? '')),
                'customer_district_snapshot' => trim((string) ($data['district_name'] ?? '')),
                'customer_district_code_snapshot' => trim((string) ($data['district_code'] ?? '')),
                'customer_commune_snapshot' => trim((string) ($data['commune_name'] ?? '')),
                'customer_commune_code_snapshot' => trim((string) ($data['commune_code'] ?? '')),
                'customer_village_snapshot' => trim((string) ($data['village_name'] ?? '')),
                'customer_village_code_snapshot' => trim((string) ($data['village_code'] ?? '')),
                'customer_group_name_snapshot' => $this->resolveCustomerGroupName($data),
                'business_location_id' => $locationId,
                'location_name_snapshot' => $this->resolveLocationName($data),
                'loan_date' => $data['loan_date'],
                'principal_amount' => $data['principal_amount'],
                'interest_amount' => $scheduleInterestTotal,
                'total_amount' => $scheduleAmountTotal > 0 ? $scheduleAmountTotal : (float) $data['principal_amount'],
                'down_payment' => $effectiveDownPayment,
                'paid_amount' => $effectiveDownPayment,
                'balance_amount' => $scheduleAmountTotal > 0 ? $scheduleAmountTotal : max(0, (float) $data['principal_amount']),
                'total_payable_amount' => $scheduleAmountTotal > 0 ? $scheduleAmountTotal : $data['principal_amount'],
                'interest_rate' => $interestRate,
                'interest_type' => $resolvedInterestType,
                'duration_months' => $durationMonths,
                'installment_count' => $durationMonths,
                'payment_frequency' => $loanMeta['payment_frequency'],
                'first_due_date' => $data['first_due_date'],
                'currency' => $data['currency'] ?? 'USD',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'penalty_type' => $data['penalty_type'] ?? null,
                'penalty_amount' => $data['penalty_amount'] ?? 0,
                'assigned_to' => $data['assigned_collector_id'] ?? null,
                'collector_id' => $data['assigned_collector_id'] ?? null,
                'source_type' => 'manual',
                'source_transaction_id' => null,
                'source_invoice_no' => null,
                'source_created_at' => null,
                'stock_already_deducted' => 0,
                'sell_final_total_snapshot' => null,
                'sell_paid_amount_snapshot' => null,
                'sell_due_amount_snapshot' => null,
                'status' => $data['action_type'] === 'create_approve' ? 'active' : ($data['action_type'] === 'draft' ? 'draft' : 'pending'),
                'created_by' => auth()->id(),
                'created_by_name_snapshot' => $this->resolveAuthUserName(),
                'meta_json' => json_encode($loanMeta, JSON_UNESCAPED_UNICODE),
                'note' => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            try {
                $loanId = (int) DB::connection('mysql_loan')->table('loans')->insertGetId($loanPayload);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($requestedLoanNumber !== '') {
                    throw $e;
                }
                $loanPayload['loan_number'] = $this->generateUniqueLoanNumber($locationId);
                $loanId = (int) DB::connection('mysql_loan')->table('loans')->insertGetId($loanPayload);
            }

            $this->insertLoanItems($loanId, $data);

            if (Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
                foreach ($schedule as $index => $row) {
                    $amountDue = round((float) ($row['total'] ?? 0), 2);
                    DB::connection('mysql_loan')->table('loan_payment_schedules')->insert($this->filterColumns('loan_payment_schedules', [
                        'loan_id' => $loanId,
                        'installment_no' => $index + 1,
                        'due_date' => $row['due_date'],
                        'schedule_amount' => $amountDue,
                        'principal_amount' => $row['principal'],
                        'principal_due' => $row['principal'],
                        'interest_amount' => $row['interest'],
                        'interest_due' => $row['interest'],
                        'amount_due' => $amountDue,
                        'paid_amount' => 0,
                        'amount_paid' => 0,
                        'balance_amount' => $amountDue,
                        'amount_balance' => $amountDue,
                        'status' => 'unpaid',
                        'paid_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            if (Schema::connection('mysql_loan')->hasTable('loan_status_logs')) {
                $status = $loanPayload['status'] ?? 'pending';
                DB::connection('mysql_loan')->table('loan_status_logs')->insert($this->filterColumns('loan_status_logs', [
                    'loan_id' => $loanId,
                    'status' => $status,
                    'from_status' => null,
                    'to_status' => $status,
                    'changed_by' => auth()->id(),
                    'changed_by_name_snapshot' => $this->resolveAuthUserName(),
                    'note' => $data['action_type'] === 'create_approve'
                        ? 'Loan created manually (standalone). Approved.'
                        : 'Loan created manually (standalone).',
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $this->storeInitialPaymentInfo($loanId, $loanPayload, $data);
            $this->storeCustomerDocuments($customerId, $loanId, $data);

            return $loanId;
        });

        if (($data['action_type'] ?? '') !== 'draft') {
            $this->notifyLocationTelegram($loanId, 'installment');
        }

        return $loanId;
    }

    protected function resolveOrCreateCustomer(array $data): ?int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_customers')) {
            return null;
        }

        $existingCustomerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;

        if ($existingCustomerId) {
            $existing = DB::connection('mysql_loan')->table('loan_customers')
                ->where('id', $existingCustomerId)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->updateCustomerAddressFields($existingCustomerId, $data);
                return $existingCustomerId;
            }
        }

        $phone = trim((string) ($data['customer_phone'] ?? ''));
        if ($phone !== '' && $phone !== '-') {
            $existing = DB::connection('mysql_loan')->table('loan_customers')
                ->where('phone', $phone)
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $this->updateCustomerAddressFields((int) $existing->id, $data);
                return (int) $existing->id;
            }
        }

        $payload = [
            'customer_code' => $this->generateUniqueCustomerCode(),
            'name' => $this->resolveCustomerName($data),
            'khmer_name' => trim((string) ($data['customer_khmer_name'] ?? '')),
            'phone' => $phone !== '' ? $phone : '-',
            'alternate_phone' => trim((string) ($data['alternate_phone'] ?? '')) ?: null,
            'address' => $this->resolveCustomerAddress($data),
            'province' => trim((string) ($data['province_name'] ?? '')),
            'province_code' => trim((string) ($data['province_code'] ?? '')),
            'district' => trim((string) ($data['district_name'] ?? '')),
            'district_code' => trim((string) ($data['district_code'] ?? '')),
            'commune' => trim((string) ($data['commune_name'] ?? '')),
            'commune_code' => trim((string) ($data['commune_code'] ?? '')),
            'village' => trim((string) ($data['village_name'] ?? '')),
            'village_code' => trim((string) ($data['village_code'] ?? '')),
            'id_card_number' => trim((string) ($data['id_card_number'] ?? '')),
            'business_location_id' => $this->resolveLocationId($data),
            'business_location_name_snapshot' => $this->resolveLocationName($data),
            'created_by' => auth()->id(),
            'created_by_name_snapshot' => $this->resolveAuthUserName(),
            'status' => 'active',
            'blacklist_status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $filtered = $this->filterColumns('loan_customers', $payload);
        return (int) DB::connection('mysql_loan')->table('loan_customers')->insertGetId($filtered);
    }

    protected function updateCustomerAddressFields(int $customerId, array $data): void
    {
        $payload = $this->filterColumns('loan_customers', [
            'name' => $this->resolveCustomerName($data),
            'khmer_name' => trim((string) ($data['customer_khmer_name'] ?? '')),
            'address' => $this->resolveCustomerAddress($data),
            'alternate_phone' => trim((string) ($data['alternate_phone'] ?? '')) ?: null,
            'province' => trim((string) ($data['province_name'] ?? '')),
            'province_code' => trim((string) ($data['province_code'] ?? '')),
            'district' => trim((string) ($data['district_name'] ?? '')),
            'district_code' => trim((string) ($data['district_code'] ?? '')),
            'commune' => trim((string) ($data['commune_name'] ?? '')),
            'commune_code' => trim((string) ($data['commune_code'] ?? '')),
            'village' => trim((string) ($data['village_name'] ?? '')),
            'village_code' => trim((string) ($data['village_code'] ?? '')),
            'updated_at' => now(),
        ]);

        $payload = array_filter($payload, function ($value) {
            return $value !== null && $value !== '';
        });

        if (! empty($payload)) {
            DB::connection('mysql_loan')->table('loan_customers')
                ->where('id', $customerId)
                ->update($payload);
        }
    }

    protected function resolveCustomerName(array $data): string
    {
        $englishName = trim((string) ($data['customer_english_name'] ?? ''));
        if ($englishName !== '') {
            return $englishName;
        }

        return trim((string) ($data['customer_name'] ?? ''));
    }

    protected function resolveCustomerDisplayName(array $data): string
    {
        $khmerName = trim((string) ($data['customer_khmer_name'] ?? ''));
        if ($khmerName !== '') {
            return $khmerName;
        }

        return $this->resolveCustomerName($data);
    }

    protected function resolveCustomerAddress(array $data): string
    {
        $manualAddress = trim((string) ($data['customer_address'] ?? ''));
        $standardAddress = trim(implode(', ', array_filter([
            trim((string) ($data['village_name'] ?? '')),
            trim((string) ($data['commune_name'] ?? '')),
            trim((string) ($data['district_name'] ?? '')),
            trim((string) ($data['province_name'] ?? '')),
        ])));

        return $standardAddress !== '' ? $standardAddress : $manualAddress;
    }

    protected function insertLoanItems(int $loanId, array $data): void
    {
        $items = $data['items'] ?? [];
        if (empty($items) || ! Schema::connection('mysql_loan')->hasTable('loan_items')) {
            return;
        }

        foreach ($items as $index => $item) {
            $productName = trim((string) ($item['product_name'] ?? ''));
            if ($productName === '') {
                continue;
            }

            $sku = trim((string) ($item['sku'] ?? ''));
            $imei = trim((string) ($item['imei'] ?? ''));
            $color = trim((string) ($item['color'] ?? ''));
            $storage = trim((string) ($item['storage'] ?? ''));
            $serialNumber = trim((string) ($item['serial_number'] ?? ''));
            if ($serialNumber === '') {
                $serialNumber = $imei;
            }

            $qty = max(1, (int) ($item['qty'] ?? 1));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
            $lineTotal = round($qty * $unitPrice, 2);
            $photoPath = $this->storeLoanItemPhoto(
                (string) ($item['product_photo'] ?? ''),
                $loanId,
                (int) $index
            );
            if ($photoPath !== null) {
                $this->storeLoanPhotoFile($photoPath, $loanId, 'product_photo', 'loan-product-'.$loanId.'-'.($index + 1).'.'.pathinfo($photoPath, PATHINFO_EXTENSION));
            }

            DB::connection('mysql_loan')->table('loan_items')->insert($this->filterColumns('loan_items', [
                'loan_id' => $loanId,
                'product_name_snapshot' => $productName,
                'sku_snapshot' => $sku ?: null,
                'imei_snapshot' => $imei ?: null,
                'color_snapshot' => $color ?: null,
                'storage_snapshot' => $storage ?: null,
                'serial_number_snapshot' => $serialNumber ?: null,
                'product_name' => $productName,
                'sku' => $sku ?: null,
                'color' => $color ?: null,
                'storage' => $storage ?: null,
                'serial_number' => $serialNumber ?: null,
                'product_photo_path' => $photoPath,
                'product_ocr_raw_text' => trim((string) ($item['product_ocr_raw_text'] ?? '')) ?: null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'total_price' => $lineTotal,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function storeLoanItemPhoto(string $dataUri, int $loanId, int $index): ?string
    {
        if ($dataUri === '') {
            return null;
        }

        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'image/jpeg';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = str_contains($mimeType, 'png') ? 'png' : 'jpg';
        $path = 'loan-product-photos/'.$loanId.'/item-'.($index + 1).'-'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    protected function storeLoanPhotoFile(string $path, int $loanId, string $category, string $originalName): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_files')) {
            return;
        }

        $fullPath = Storage::disk('public')->path($path);
        if (! is_readable($fullPath)) {
            return;
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'image/jpeg') : 'image/jpeg';

        DB::connection('mysql_loan')->table('loan_files')->insert($this->filterColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\Loan::class,
            'fileable_id' => $loanId,
            'category' => $category,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => filesize($fullPath) ?: null,
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function resolveLocationId(array $data): ?int
    {
        if (! empty($data['business_location_id'])) {
            return (int) $data['business_location_id'];
        }

        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return null;
        }

        $first = DB::connection('mysql_loan')->table('loan_business_locations')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        return $first ? (int) $first : null;
    }

    protected function resolveLocationName(array $data): ?string
    {
        if (! empty($data['business_location_id']) && Schema::hasTable('business_locations')) {
            $name = DB::table('business_locations')->where('id', $data['business_location_id'])->value('name');
            if ($name) {
                return $name;
            }
        }

        if (! empty($data['business_location_id']) && Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            $name = DB::connection('mysql_loan')->table('loan_business_locations')
                ->where('main_location_id', $data['business_location_id'])
                ->value('name');
            if ($name) {
                return $name;
            }
        }

        return null;
    }

    protected function resolveCustomerGroupName(array $data): string
    {
        $groupName = trim((string) ($data['customer_group_name'] ?? ''));
        return $groupName !== '' ? $groupName : 'រំលស់';
    }

    protected function resolveAuthUserName(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        $full = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));
        return $full !== '' ? $full : ($user->username ?? null);
    }

    protected function filterColumns(string $table, array $payload): array
    {
        if (! Schema::connection('mysql_loan')->hasTable($table)) {
            return [];
        }
        $columns = Schema::connection('mysql_loan')->getColumnListing($table);
        return Arr::only($payload, $columns);
    }

    protected function storeCustomerDocuments(?int $customerId, int $loanId, array $data): void
    {
        if (empty($customerId) || ! Schema::connection('mysql_loan')->hasTable('loan_files')) {
            return;
        }

        $profileImage = (string) ($data['customer_profile_image'] ?? '');
        if ($profileImage !== '') {
            $fileId = $this->storeDataUriFile($profileImage, $customerId, 'customer_photo', 'customer-profile-'.$loanId.'.jpg');
            if ($fileId && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'customer_photo_file_id')) {
                DB::connection('mysql_loan')->table('loan_customers')->where('id', $customerId)->update([
                    'customer_photo_file_id' => $fileId,
                    'updated_at' => now(),
                ]);
            }
        }

        $idCardImage = (string) ($data['id_card_image'] ?? '');
        if ($idCardImage !== '') {
            $fileId = $this->storeDataUriFile($idCardImage, $customerId, 'id_front', 'id-card-front-'.$loanId.'.jpg');
            if ($fileId && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'id_front_file_id')) {
                DB::connection('mysql_loan')->table('loan_customers')->where('id', $customerId)->update([
                    'id_front_file_id' => $fileId,
                    'updated_at' => now(),
                ]);
            }

            if ($fileId && Schema::connection('mysql_loan')->hasTable('loan_id_card_scans')) {
                DB::connection('mysql_loan')->table('loan_id_card_scans')->insert($this->filterColumns('loan_id_card_scans', [
                    'customer_id' => $customerId,
                    'loan_file_id' => $fileId,
                    'side' => 'front',
                    'ocr_raw_text' => $data['id_card_ocr_raw_text'] ?? null,
                    'ocr_structured_json' => ! empty($data['id_card_ocr_fields']) ? json_encode($data['id_card_ocr_fields'], JSON_UNESCAPED_UNICODE) : null,
                    'provider' => 'tesseract',
                    'status' => ! empty($data['id_card_ocr_raw_text']) ? 'completed' : 'pending',
                    'scanned_at' => ! empty($data['id_card_ocr_raw_text']) ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        foreach ((array) ($data['documents'] ?? []) as $index => $document) {
            if (is_string($document) && $document !== '') {
                $ext = 'jpg';
                if (preg_match('/^data:([^;]+);/', $document, $m)) {
                    $mt = $m[1];
                    if (str_contains($mt, 'pdf')) {
                        $ext = 'pdf';
                    } elseif (str_contains($mt, 'png')) {
                        $ext = 'png';
                    } elseif (str_contains($mt, 'text')) {
                        $ext = 'txt';
                    }
                }
                $this->storeDataUriFile($document, $customerId, 'document', 'customer-document-'.$loanId.'-'.($index + 1).'.'.$ext);
            }
        }

        $documentText = trim((string) ($data['document_text'] ?? ''));
        if ($documentText !== '') {
            $this->storeTextDocument($documentText, $customerId, 'customer-document-note-'.$loanId.'.txt');
        }

        foreach ((array) ($data['document_links'] ?? []) as $index => $link) {
            $link = trim((string) $link);
            if ($link !== '') {
                $this->storeTextDocument($link, $customerId, 'customer-document-link-'.$loanId.'-'.($index + 1).'.txt');
            }
        }
    }

    protected function storeTextDocument(string $text, int $customerId, string $originalName): ?int
    {
        $dataUri = 'data:text/plain;base64,'.base64_encode($text);

        return $this->storeDataUriFile($dataUri, $customerId, 'document', $originalName);
    }

    protected function storeDataUriFile(string $dataUri, int $customerId, string $category, string $originalName): ?int
    {
        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $match)) {
            $mimeType = $match[1];
            $dataUri = substr($dataUri, strpos($dataUri, ',') + 1);
        } else {
            $mimeType = 'image/jpeg';
        }

        $binary = base64_decode($dataUri, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ];
        $extension = $extensionMap[$mimeType] ?? (str_contains($mimeType, 'png') ? 'png' : (str_contains($mimeType, 'pdf') ? 'pdf' : 'jpg'));

        $path = 'loan-customers/'.$customerId.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return (int) DB::connection('mysql_loan')->table('loan_files')->insertGetId($this->filterColumns('loan_files', [
            'fileable_type' => \Modules\LoanManagement\Entities\LoanCustomer::class,
            'fileable_id' => $customerId,
            'category' => $category,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($binary),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function generateUniqueLoanNumber(?int $locationId = null): string
    {
        $prefix = $this->normalizeLoanInvoicePrefix($this->loanInvoicePrefixForLocation($locationId));
        $prefix = $prefix.Carbon::now()->format('Ymd').'-';
        $nextNumber = $this->nextLoanNumberSequence($prefix);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $width = max(2, strlen((string) $nextNumber));
            $candidate = $prefix.str_pad((string) $nextNumber, $width, '0', STR_PAD_LEFT);

            $exists = DB::connection('mysql_loan')->table('loans')
                ->where('loan_number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $nextNumber++;
        }

        return $prefix.Carbon::now()->format('His');
    }

    protected function nextLoanNumberSequence(string $prefix): int
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')
            || ! Schema::connection('mysql_loan')->hasColumn('loans', 'loan_number')) {
            return 1;
        }

        $loanNumbers = DB::connection('mysql_loan')->table('loans')
            ->where('loan_number', 'like', $prefix.'%')
            ->pluck('loan_number');

        $max = 0;
        foreach ($loanNumbers as $loanNumber) {
            $suffix = Str::after((string) $loanNumber, $prefix);

            // Ignore old random invoice numbers like KY-20260714-647692.
            if (! preg_match('/^\d{1,4}$/', $suffix)) {
                continue;
            }

            $max = max($max, (int) $suffix);
        }

        return $max + 1;
    }

    protected function loanInvoicePrefixForLocation(?int $locationId = null): ?string
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_business_locations')
            || ! Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'loan_invoice_prefix')) {
            return null;
        }

        $query = DB::connection('mysql_loan')->table('loan_business_locations')
            ->whereNotNull('loan_invoice_prefix')
            ->where('loan_invoice_prefix', '!=', '');

        if (! empty($locationId)) {
            $query->where(function ($q) use ($locationId) {
                $q->orWhere('id', $locationId)
                    ->orWhere('main_location_id', $locationId);
            });
        }

        return $query->value('loan_invoice_prefix');
    }

    protected function normalizeLoanInvoicePrefix(?string $prefix): string
    {
        $prefix = trim((string) $prefix);
        $prefix = preg_replace('/\s+/', '', $prefix) ?: '';
        if ($prefix === '') {
            $prefix = 'LN';
        }
        return rtrim($prefix, '-/').'-';
    }

    protected function loanNumberExists(string $loanNumber): bool
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')
            || ! Schema::connection('mysql_loan')->hasColumn('loans', 'loan_number')) {
            return false;
        }
        return DB::connection('mysql_loan')->table('loans')
            ->where('loan_number', $loanNumber)
            ->exists();
    }

    protected function generateUniqueCustomerCode(): string
    {
        do {
            $code = 'LC-'.strtoupper(Str::random(8));
            $exists = DB::connection('mysql_loan')->table('loan_customers')->where('customer_code', $code)->exists();
        } while ($exists);
        return $code;
    }

    protected function storeInitialPaymentInfo(int $loanId, array $loanPayload, array $data): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return;
        }

        $payments = $this->initialPaymentRows($data);
        if (empty($payments)) {
            return;
        }

        $paymentTypes = app(\App\Utils\TransactionUtil::class)->payment_types(
            $loanPayload['business_location_id'] ?? null,
            true,
            (int) (session('user.business_id') ?? 0)
        );

        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $paymentMethodId = ! empty($payment['payment_method_id']) ? (int) $payment['payment_method_id'] : null;
            $paymentMethod = trim((string) ($payment['method'] ?? ''));
            $paymentMethodName = 'Unknown';

            if ($paymentMethod !== '') {
                $paymentMethodName = (string) ($paymentTypes[$paymentMethod] ?? ucfirst(str_replace('_', ' ', $paymentMethod)));
            } elseif (! empty($paymentMethodId) && Schema::hasTable('payment_methods')) {
                $paymentMethodName = (string) (DB::table('payment_methods')->where('id', $paymentMethodId)->value('name') ?: 'Unknown');
            }

            $paidDate = ! empty($payment['paid_date']) ? $payment['paid_date'] : ($data['loan_date'] ?? now()->toDateString());
            $exchangeRate = (float) ($payment['exchange_rate'] ?? ($data['exchange_rate'] ?? 1));
            if ($exchangeRate <= 0) {
                $exchangeRate = 1;
            }
            $currency = (string) ($payment['currency'] ?? ($data['currency'] ?? 'USD'));
            $paymentStatus = (string) ($payment['status'] ?? 'completed');
            $combinedNote = trim((string) ($payment['note'] ?? ''));

            $paymentPayload = $this->filterColumns('loan_payments', [
                'loan_id' => $loanId,
                'payment_type' => 'loan',
                'payment_number' => $this->generateUniquePaymentNumber($loanId),
                'receipt_number' => 'RCP-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-'.random_int(10, 99),
                'loan_number_snapshot' => $loanPayload['loan_number'] ?? null,
                'customer_id' => $loanPayload['customer_id'] ?? null,
                'customer_name_snapshot' => $loanPayload['customer_name_snapshot'] ?? null,
                'customer_phone_snapshot' => $loanPayload['customer_phone_snapshot'] ?? null,
                'payment_method_snapshot' => $paymentMethodName,
                'paid_date' => $paidDate,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'total_paid' => $amount,
                'total_paid_base' => round($amount * $exchangeRate, 4),
                'reference_number' => $payment['reference_number'] ?? null,
                'note' => $combinedNote ?: null,
                'status' => $paymentStatus,
                'received_by' => auth()->id(),
                'received_by_name_snapshot' => $this->resolveAuthUserName(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (empty($paymentPayload)) {
                continue;
            }

            $paymentId = 0;
            $attempt = 0;
            do {
                try {
                    if ($attempt > 0) {
                        if (array_key_exists('payment_number', $paymentPayload)) {
                            $paymentPayload['payment_number'] = $this->generateUniquePaymentNumber($loanId);
                        }
                        if (array_key_exists('receipt_number', $paymentPayload)) {
                            $paymentPayload['receipt_number'] = 'RCP-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-'.random_int(10, 99);
                        }
                    }
                    $paymentId = (int) DB::connection('mysql_loan')->table('loan_payments')->insertGetId($paymentPayload);
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    $attempt++;
                    if ($attempt >= 3) {
                        throw $e;
                    }
                    usleep(150000);
                }
            } while ($attempt < 3);

            if (! Schema::connection('mysql_loan')->hasTable('loan_payment_details')) {
                continue;
            }

            DB::connection('mysql_loan')->table('loan_payment_details')->insert($this->filterColumns('loan_payment_details', [
                'payment_id' => $paymentId,
                'payment_method_id' => $paymentMethodId,
                'payment_method_snapshot' => $paymentMethodName,
                'method' => $paymentMethod !== '' ? $paymentMethod : $paymentMethodName,
                'currency' => $currency,
                'amount' => $amount,
                'exchange_rate' => $exchangeRate,
                'amount_base' => round($amount * $exchangeRate, 4),
                'reference_number' => $payment['reference_number'] ?? null,
                'note' => $combinedNote ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function totalInitialPaymentAmount(array $data): float
    {
        return round(collect($this->initialPaymentRows($data))->sum(fn ($payment) => (float) ($payment['amount'] ?? 0)), 2);
    }

    protected function normalizeStandaloneLoanAmounts(array $data): array
    {
        $productTotal = $this->productItemsTotal($data);

        if ($productTotal <= 0) {
            return $data;
        }

        $paymentTotal = $this->totalInitialPaymentAmount($data);
        if ($paymentTotal <= 0) {
            $paymentTotal = (float) ($data['down_payment'] ?? 0);
        }

        $data['principal_amount'] = max(0, round($productTotal - $paymentTotal, 2));
        $data['down_payment'] = round($paymentTotal, 2);

        return $data;
    }

    protected function productItemsTotal(array $data): float
    {
        $total = 0;

        foreach ((array) ($data['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productName = trim((string) ($item['product_name'] ?? ''));
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));

            if ($productName === '' || $unitPrice <= 0) {
                continue;
            }

            $total += round($qty * $unitPrice, 2);
        }

        return round($total, 2);
    }

    protected function initialPaymentRows(array $data): array
    {
        $payments = collect((array) ($data['payments'] ?? []))
            ->filter(fn ($payment) => is_array($payment) && (float) ($payment['amount'] ?? 0) > 0)
            ->values()
            ->all();

        if (! empty($payments)) {
            return $payments;
        }

        $legacyPayment = (array) ($data['payment'] ?? []);
        if ((float) ($legacyPayment['amount'] ?? 0) > 0) {
            return [$legacyPayment];
        }

        $downPayment = (float) ($data['down_payment'] ?? 0);
        if ($downPayment <= 0) {
            return [];
        }

        return [[
            'amount' => $downPayment,
            'paid_date' => $data['loan_date'] ?? now()->toDateString(),
            'method' => 'cash',
            'currency' => $data['currency'] ?? 'USD',
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'status' => 'completed',
        ]];
    }

    protected function generateUniquePaymentNumber(int $loanId): string
    {
        $prefix = 'PAY-'.Carbon::now()->format('YmdHis').'-'.$loanId.'-';
        $attempt = 0;
        $referenceColumn = null;

        if (Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            foreach (['payment_number', 'payment_ref_no', 'receipt_number', 'reference_number'] as $column) {
                if (Schema::connection('mysql_loan')->hasColumn('loan_payments', $column)) {
                    $referenceColumn = $column;
                    break;
                }
            }
        }

        do {
            $candidate = $prefix.random_int(1000, 9999);
            $exists = $referenceColumn
                ? DB::connection('mysql_loan')->table('loan_payments')
                    ->where($referenceColumn, $candidate)
                    ->exists()
                : false;
            $attempt++;
        } while ($exists && $attempt < 10);

        if ($exists) {
            $candidate = $prefix.uniqid();
        }

        return $candidate;
    }

    protected function notifyLocationTelegram(int $loanId, string $event): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans') || ! Schema::connection('mysql_loan')->hasTable('loan_business_locations')) {
            return;
        }

        $loan = DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->first();
        if (! $loan) {
            return;
        }

        $location = $this->resolveLoanTelegramLocation($loan);
        if (! $location) {
            return;
        }

        $notifyColumn = $event === 'payment' ? 'telegram_notify_payment' : 'telegram_notify_installment';
        $notifyEnabled = Schema::connection('mysql_loan')->hasColumn('loan_business_locations', $notifyColumn)
            ? ! empty($location->{$notifyColumn})
            : true;

        if (! $notifyEnabled) {
            return;
        }

        $chatId = $event === 'payment'
            ? ($location->telegram_payment_chat_id ?? null)
            : ($location->telegram_installment_chat_id ?? null);
        $chatId = trim((string) ($chatId ?: ($location->telegram_chat_id ?? '')));

        if ($chatId === '') {
            return;
        }

        $message = $this->buildLoanTelegramMessage($loan);
        $photoPaths = $this->resolveLoanTelegramPhotoPaths($loanId);

        $status = 'sent';
        $response = null;
        $sentAt = now();

        try {
            $result = $this->sendLoanTelegramNotice(
                $chatId,
                $message,
                $photoPaths
            );

            $response = json_encode($result);
            if (empty($result['success'])) {
                $status = 'failed';
                $sentAt = null;
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $sentAt = null;
            $response = $e->getMessage();
            Log::warning('Loan Telegram notification failed after create.', [
                'loan_id' => $loanId,
                'event' => $event,
                'chat_id' => $chatId,
                'photo_paths' => $photoPaths,
                'error' => $e->getMessage(),
            ]);
        }

        if (Schema::connection('mysql_loan')->hasTable('loan_telegram_notifications')) {
            DB::connection('mysql_loan')->table('loan_telegram_notifications')->insert($this->filterColumns('loan_telegram_notifications', [
                'loan_id' => $loanId,
                'customer_id' => $loan->customer_id ?? null,
                'event_code' => $event,
                'chat_id' => $chatId,
                'message' => $message,
                'status' => $status,
                'response' => $response,
                'response_payload' => $response,
                'sent_at' => $sentAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    protected function buildLoanTelegramMessage(object $loan): string
    {
        $items = $this->loanTelegramItems((int) $loan->id);
        $payments = $this->loanTelegramPayments((int) $loan->id);
        $firstDueDate = $this->loanTelegramFirstDueDate((int) $loan->id);
        $currency = (string) ($loan->currency ?? 'USD');
        $symbol = $currency === 'USD' ? '$' : $currency.' ';

        $productCodes = $items->map(function ($item) {
            return trim((string) ($item->sku_snapshot ?? $item->sku ?? $item->serial_number_snapshot ?? $item->serial_number ?? $item->imei_snapshot ?? ''));
        })->filter()->implode(', ');
        $productNames = $items->map(function ($item) {
            return trim((string) ($item->product_name_snapshot ?? $item->product_name ?? ''));
        })->filter()->implode(', ');
        $productLine = trim(($productCodes !== '' ? $productCodes.':' : '').($productNames !== '' ? $productNames : '-'));

        $productTotal = round($items->sum(function ($item) {
            return (float) ($item->line_total ?? $item->total_price ?? 0);
        }), 2);
        if ($productTotal <= 0) {
            $productTotal = round(((float) ($loan->principal_amount ?? 0)) + ((float) ($loan->down_payment ?? 0)), 2);
        }

        $customerName = $this->loanTelegramCustomerName($loan);
        $documentLines = $this->loanTelegramDocumentLines($loan);

        $lines = [
            'កាលបរិច្ឆេទ:'.$this->formatTelegramDate($loan->loan_date ?? null),
            'វិក័យប័ត្រ        :'.($loan->loan_number ?? $loan->id),
            'អតិថិជនឈ្មោះ :'.$customerName,
            'លេខទូរស័ព្ទ      :'.($loan->customer_phone_snapshot ?? '-'),
            'លេខសម្គាល់ទំនិញ:'.$productLine,
            'តម្លៃទំនិញ  :'.$this->formatTelegramMoney($productTotal, $symbol),
            'ចូលរួមមុន  '.$this->formatTelegramPayments($payments, $symbol, (float) ($loan->down_payment ?? 0)),
            'កម្ចីសរុប '.$this->formatTelegramMoney((float) ($loan->principal_amount ?? 0), $symbol),
            'ថ្ងៃខែសងប្រាក់ លើកទី1:'.$this->formatTelegramDate($firstDueDate ?: ($loan->first_due_date ?? null)),
            'ចំនួនខែកម្ចី       :'.(int) ($loan->duration_months ?? $loan->installment_count ?? 0),
            'ដោយ:'.($loan->created_by_name_snapshot ?? $this->resolveAuthUserName() ?? '-'),
        ];

        if (! empty($documentLines)) {
            $lines[] = 'Documents:';
            foreach ($documentLines as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    protected function loanTelegramCustomerName(object $loan): string
    {
        foreach (['customer_khmer_name_snapshot', 'khmer_name_snapshot', 'khmer_name'] as $field) {
            $value = trim((string) ($loan->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (
            ! empty($loan->customer_id)
            && Schema::connection('mysql_loan')->hasTable('loan_customers')
            && Schema::connection('mysql_loan')->hasColumn('loan_customers', 'khmer_name')
        ) {
            $khmerName = trim((string) DB::connection('mysql_loan')->table('loan_customers')
                ->where('id', $loan->customer_id)
                ->value('khmer_name'));

            if ($khmerName !== '') {
                return $khmerName;
            }
        }

        $snapshotName = trim((string) ($loan->customer_name_snapshot ?? ''));

        return $snapshotName !== '' ? $snapshotName : '-';
    }

    protected function loanTelegramItems(int $loanId): \Illuminate\Support\Collection
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_items')) {
            return collect();
        }

        return DB::connection('mysql_loan')->table('loan_items')
            ->where('loan_id', $loanId)
            ->orderBy('id')
            ->get();
    }

    protected function loanTelegramPayments(int $loanId): \Illuminate\Support\Collection
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return collect();
        }

        $query = DB::connection('mysql_loan')->table('loan_payments')
            ->where('loan_id', $loanId)
            ->orderBy('id');

        if (Schema::connection('mysql_loan')->hasColumn('loan_payments', 'payment_type')) {
            $query->whereIn('payment_type', ['loan', 'initial', 'down_payment', 'downpayment', 'deposit']);
        } elseif (Schema::connection('mysql_loan')->hasColumn('loan_payments', 'schedule_id')) {
            $query->whereNull('schedule_id');
        }

        return $query->get();
    }

    protected function loanTelegramFirstDueDate(int $loanId): ?string
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return null;
        }

        return DB::connection('mysql_loan')->table('loan_payment_schedules')
            ->where('loan_id', $loanId)
            ->orderBy('installment_no')
            ->orderBy('due_date')
            ->value('due_date');
    }

    protected function loanTelegramDocumentLines(object $loan): array
    {
        if (empty($loan->customer_id) || ! Schema::connection('mysql_loan')->hasTable('loan_files')) {
            return [];
        }

        $hasOriginalName = Schema::connection('mysql_loan')->hasColumn('loan_files', 'original_name');
        $query = DB::connection('mysql_loan')->table('loan_files')
            ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
            ->where('fileable_id', $loan->customer_id)
            ->where('category', 'document')
            ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($hasOriginalName, function ($query) use ($loan) {
                $query->where(function ($nameQuery) use ($loan) {
                    $nameQuery->where('original_name', 'like', 'customer-document-note-'.$loan->id.'.%')
                        ->orWhere('original_name', 'like', 'customer-document-link-'.$loan->id.'-%');
                });
            });

        if (Schema::connection('mysql_loan')->hasColumn('loan_files', 'mime_type')) {
            $query->where('mime_type', 'text/plain');
        }

        return $query->orderBy('id')
            ->get($hasOriginalName ? ['path', 'original_name'] : ['path'])
            ->map(function ($file) use ($hasOriginalName) {
                $path = $this->readablePublicDiskPath($file->path ?? null);
                if ($path === null) {
                    return null;
                }

                $content = trim((string) @file_get_contents($path));
                if ($content === '') {
                    return null;
                }

                $label = $hasOriginalName && str_contains((string) ($file->original_name ?? ''), 'link') ? 'Link' : 'Note';

                return '- '.$label.': '.Str::limit($content, 350);
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function formatTelegramDate($date): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('m/d/Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    protected function formatTelegramMoney(float $amount, string $symbol): string
    {
        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return $symbol === '$' ? $formatted.'$' : $symbol.$formatted;
    }

    protected function formatTelegramPayments(\Illuminate\Support\Collection $payments, string $symbol, float $fallbackAmount): string
    {
        if ($payments->isEmpty()) {
            return 'លុយ '.$this->formatTelegramMoney($fallbackAmount, $symbol);
        }

        $totalAmount = (float) $payments->sum(function ($payment) {
            return (float) ($payment->total_paid ?? $payment->amount ?? 0);
        });

        if ($totalAmount <= 0) {
            return 'លុយ '.$this->formatTelegramMoney($fallbackAmount, $symbol);
        }

        $methodBreakdown = $payments->map(function ($payment) use ($symbol) {
            $method = trim((string) ($payment->payment_method_snapshot ?? $payment->method ?? ''));
            $amount = (float) ($payment->total_paid ?? $payment->amount ?? 0);

            if ($amount <= 0) {
                return null;
            }

            if ($method === '' || in_array(Str::lower($method), ['cash', 'លុយ'], true)) {
                return 'លុយ '.$this->formatTelegramMoney($amount, $symbol);
            }

            return $method.' '.$this->formatTelegramMoney($amount, $symbol);
        })->filter()->values();

        if ($methodBreakdown->count() === 1) {
            return $methodBreakdown->first();
        }

        return 'សរុប '.$this->formatTelegramMoney($totalAmount, $symbol).' ('.$methodBreakdown->implode(' + ').')';
    }

    protected function resolveLoanTelegramPhotoPaths(int $loanId): array
    {
        $photoPaths = [];

        if (Schema::connection('mysql_loan')->hasTable('loan_items')
            && Schema::connection('mysql_loan')->hasColumn('loan_items', 'product_photo_path')) {
            $paths = DB::connection('mysql_loan')->table('loan_items')
                ->where('loan_id', $loanId)
                ->whereNotNull('product_photo_path')
                ->where('product_photo_path', '!=', '')
                ->orderBy('id')
                ->pluck('product_photo_path');

            foreach ($paths as $path) {
                $fullPath = $this->readablePublicDiskPath($path);
                if ($fullPath !== null) {
                    $photoPaths[] = $fullPath;
                }
            }
        }

        if (Schema::connection('mysql_loan')->hasTable('loan_files')) {
            $loanFilePaths = DB::connection('mysql_loan')->table('loan_files')
                ->where('fileable_type', \Modules\LoanManagement\Entities\Loan::class)
                ->where('fileable_id', $loanId)
                ->whereIn('category', ['product_photo', 'document', 'customer_photo', 'id_front'])
                ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'mime_type'), fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                ->orderByRaw("CASE category WHEN 'product_photo' THEN 0 WHEN 'document' THEN 1 WHEN 'customer_photo' THEN 2 WHEN 'id_front' THEN 3 ELSE 4 END")
                ->orderBy('id')
                ->pluck('path');

            foreach ($loanFilePaths as $path) {
                $fullPath = $this->readablePublicDiskPath($path);
                if ($fullPath !== null) {
                    $photoPaths[] = $fullPath;
                }
            }

            $customerId = Schema::connection('mysql_loan')->hasTable('loans')
                ? DB::connection('mysql_loan')->table('loans')->where('id', $loanId)->value('customer_id')
                : null;

            if (! empty($customerId)) {
                $customerFilePaths = DB::connection('mysql_loan')->table('loan_files')
                    ->where('fileable_type', \Modules\LoanManagement\Entities\LoanCustomer::class)
                    ->where('fileable_id', $customerId)
                    ->whereIn('category', ['customer_photo', 'id_front', 'document'])
                    ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                    ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'mime_type'), fn ($query) => $query->where('mime_type', 'like', 'image/%'))
                    ->when(Schema::connection('mysql_loan')->hasColumn('loan_files', 'original_name'), function ($query) use ($loanId) {
                        $query->where(function ($nameQuery) use ($loanId) {
                            $nameQuery->where('original_name', 'like', 'customer-profile-'.$loanId.'.%')
                                ->orWhere('original_name', 'like', 'id-card-front-'.$loanId.'.%')
                                ->orWhere('original_name', 'like', 'customer-document-'.$loanId.'-%');
                        });
                    })
                    ->orderByRaw("CASE category WHEN 'customer_photo' THEN 0 WHEN 'id_front' THEN 1 WHEN 'document' THEN 2 ELSE 3 END")
                    ->orderByDesc('id')
                    ->pluck('path');

                foreach ($customerFilePaths as $path) {
                    $fullPath = $this->readablePublicDiskPath($path);
                    if ($fullPath !== null) {
                        $photoPaths[] = $fullPath;
                    }
                }
            }
        }

        return $this->uniquePhotoPaths($photoPaths);
    }

    protected function uniquePhotoPaths(array $photoPaths): array
    {
        $unique = [];
        $seen = [];

        foreach ($photoPaths as $photoPath) {
            $key = is_readable($photoPath) ? (@md5_file($photoPath) ?: $photoPath) : $photoPath;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $photoPath;
        }

        return $unique;
    }

    protected function readablePublicDiskPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $fullPath = Storage::disk('public')->path($path);

        return is_readable($fullPath) ? $fullPath : null;
    }

    protected function sendLoanTelegramNotice(string $chatId, string $message, array $photoPaths = []): array
    {
        $token = (string) config('notificationcenter.telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        if (trim($chatId) === '' || $token === '') {
            return ['success' => false, 'status' => 'failed', 'error' => 'Telegram not configured or empty chat_id'];
        }

        if (empty($photoPaths)) {
            $textResponse = Http::timeout(15)->retry(2, 250)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            return $textResponse->successful()
                ? ['success' => true, 'status' => 'sent', 'with_text' => true, 'with_photo' => false, 'photo_count' => 0, 'text_mode' => 'message']
                : ['success' => false, 'status' => 'failed', 'with_text' => false, 'with_photo' => false, 'photo_count' => 0, 'error' => 'Text failed HTTP '.$textResponse->status().': '.$textResponse->body()];
        }

        if (count($photoPaths) === 1) {
            $singleResult = $this->sendTelegramPhotoPath($token, $chatId, $photoPaths[0], $message);

            return [
                'success' => $singleResult['success'],
                'status' => $singleResult['success'] ? 'sent' : 'failed',
                'with_text' => $singleResult['success'],
                'with_photo' => $singleResult['success'],
                'photo_count' => $singleResult['success'] ? 1 : 0,
                'photo_total' => 1,
                'photo_mode' => 'single',
                'text_mode' => $singleResult['success'] ? 'caption' : 'none',
                'errors' => $singleResult['success'] ? [] : [$singleResult['error']],
            ];
        }

        $groupResult = $this->sendTelegramPhotoGroups($token, $chatId, $photoPaths, $message);
        if ($groupResult['success']) {
            return $groupResult;
        }

        $sentPhotos = 0;
        $errors = $groupResult['errors'] ?? [];

        foreach ($photoPaths as $index => $photoPath) {
            $result = $this->sendTelegramPhotoPath($token, $chatId, $photoPath, $index === 0 ? $message : null);
            if ($result['success']) {
                $sentPhotos++;
            } else {
                $errors[] = $result['error'];
            }
        }

        return [
            'success' => $sentPhotos > 0 || empty($photoPaths),
            'status' => $sentPhotos > 0 ? 'sent' : 'failed',
            'with_text' => true,
            'with_photo' => $sentPhotos > 0,
            'photo_count' => $sentPhotos,
            'photo_total' => count($photoPaths),
            'photo_mode' => 'individual_fallback',
            'text_mode' => $sentPhotos > 0 ? 'first_photo_caption' : 'none',
            'errors' => $errors,
        ];
    }

    protected function sendTelegramPhotoGroups(string $token, string $chatId, array $photoPaths, string $caption): array
    {
        $sentPhotos = 0;
        $errors = [];
        $chunks = array_chunk($photoPaths, 10);

        foreach ($chunks as $chunkIndex => $chunk) {
            if (count($chunk) < 2) {
                $result = $this->sendTelegramPhotoPath($token, $chatId, $chunk[0], $chunkIndex === 0 ? $caption : null);
                if ($result['success']) {
                    $sentPhotos++;
                } else {
                    $errors[] = $result['error'];
                }
                continue;
            }

            $media = [];
            $handles = [];
            $validPaths = [];
            $request = Http::timeout(45)->retry(2, 250);

            foreach ($chunk as $index => $photoPath) {
                if (! is_readable($photoPath)) {
                    $errors[] = 'Photo not readable: '.$photoPath;
                    continue;
                }

                $handle = fopen($photoPath, 'rb');
                if ($handle === false) {
                    $errors[] = 'Unable to open photo: '.$photoPath;
                    continue;
                }

                $field = 'photo_'.$chunkIndex.'_'.$index;
                $handles[] = $handle;
                $validPaths[] = $photoPath;
                $request = $request->attach($field, $handle, basename($photoPath));

                $item = [
                    'type' => 'photo',
                    'media' => 'attach://'.$field,
                ];

                if ($chunkIndex === 0 && $index === 0 && trim($caption) !== '') {
                    $item['caption'] = mb_substr($caption, 0, 1024);
                }

                $media[] = $item;
            }

            if (count($media) < 2) {
                foreach ($handles as $handle) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
                if (count($validPaths) === 1) {
                    $result = $this->sendTelegramPhotoPath($token, $chatId, $validPaths[0], $chunkIndex === 0 ? $caption : null);
                    if ($result['success']) {
                        $sentPhotos++;
                    } else {
                        $errors[] = $result['error'];
                    }
                }
                continue;
            }

            try {
                $response = $request->post("https://api.telegram.org/bot{$token}/sendMediaGroup", [
                    'chat_id' => $chatId,
                    'media' => json_encode($media),
                ]);
            } finally {
                foreach ($handles as $handle) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }

            if ($response->successful()) {
                $sentPhotos += count($media);
            } else {
                $errors[] = 'sendMediaGroup failed HTTP '.$response->status().': '.$response->body();
            }
        }

        return [
            'success' => $sentPhotos > 0,
            'status' => $sentPhotos > 0 ? 'sent' : 'failed',
            'with_text' => $sentPhotos > 0,
            'with_photo' => $sentPhotos > 0,
            'photo_count' => $sentPhotos,
            'photo_total' => count($photoPaths),
            'photo_mode' => 'media_group',
            'text_mode' => $sentPhotos > 0 ? 'first_photo_caption' : 'none',
            'errors' => $errors,
        ];
    }

    protected function sendTelegramPhotoPath(string $token, string $chatId, string $photoPath, ?string $caption = null): array
    {
        if (! is_readable($photoPath)) {
            return ['success' => false, 'error' => 'Photo not readable: '.$photoPath];
        }

        $handle = fopen($photoPath, 'rb');
        if ($handle === false) {
            return ['success' => false, 'error' => 'Unable to open photo: '.$photoPath];
        }

        try {
            $payload = ['chat_id' => $chatId];
            if ($caption !== null && trim($caption) !== '') {
                $payload['caption'] = mb_substr($caption, 0, 1024);
            }

            $response = Http::timeout(30)->retry(2, 250)->attach(
                'photo',
                $handle,
                basename($photoPath)
            )->post("https://api.telegram.org/bot{$token}/sendPhoto", $payload);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $response->successful()
            ? ['success' => true]
            : ['success' => false, 'error' => basename($photoPath).' failed HTTP '.$response->status().': '.$response->body()];
    }

    protected function createLoanTelegramPhotoCollage(array $photoPaths): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            return null;
        }

        $images = [];
        foreach ($photoPaths as $photoPath) {
            $image = $this->createImageResourceFromPath($photoPath);
            if ($image !== null) {
                $images[] = ['path' => $photoPath, 'image' => $image];
            }
        }

        if (empty($images)) {
            return null;
        }

        if (count($images) === 1) {
            imagedestroy($images[0]['image']);

            return $images[0]['path'];
        }

        $canvasWidth = 1600;
        $gap = 16;
        $padding = 18;
        $targetRowHeight = 360;
        $contentWidth = $canvasWidth - ($padding * 2);
        $layouts = [];
        $row = [];
        $rowAspect = 0.0;

        foreach ($images as $item) {
            $source = $item['image'];
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                continue;
            }

            $item['width'] = $sourceWidth;
            $item['height'] = $sourceHeight;
            $item['aspect'] = $sourceWidth / $sourceHeight;
            $row[] = $item;
            $rowAspect += $item['aspect'];

            $availableWidth = $contentWidth - ((count($row) - 1) * $gap);
            $rowHeight = $rowAspect > 0 ? $availableWidth / $rowAspect : $targetRowHeight;
            if ($rowHeight <= $targetRowHeight || count($row) >= 4) {
                $layouts[] = ['items' => $row, 'height' => max(180, min(520, (int) floor($rowHeight))), 'justify' => $rowHeight <= 520];
                $row = [];
                $rowAspect = 0.0;
            }
        }

        if (! empty($row)) {
            $availableWidth = $contentWidth - ((count($row) - 1) * $gap);
            $rowHeight = $rowAspect > 0 ? $availableWidth / $rowAspect : $targetRowHeight;
            $layouts[] = ['items' => $row, 'height' => max(200, min($targetRowHeight, (int) floor($rowHeight))), 'justify' => $rowHeight <= $targetRowHeight];
        }

        if (empty($layouts)) {
            foreach ($images as $item) {
                imagedestroy($item['image']);
            }

            return null;
        }

        $canvasHeight = $padding;
        foreach ($layouts as $layout) {
            $canvasHeight += $layout['height'] + $gap;
        }
        $canvasHeight = max(1, $canvasHeight - $gap + $padding);
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $white);

        $y = $padding;
        foreach ($layouts as $layout) {
            $x = $padding;
            $rowItems = $layout['items'];
            $rowHeight = $layout['height'];
            $remainingWidth = $contentWidth - ((count($rowItems) - 1) * $gap);
            $remainingAspect = array_sum(array_map(fn ($item) => $item['aspect'], $rowItems));
            $justifyRow = ! empty($layout['justify']);

            foreach ($rowItems as $index => $item) {
                $source = $item['image'];
                $targetHeight = $rowHeight;
                if ($justifyRow) {
                    $targetWidth = $index === count($rowItems) - 1
                        ? max(1, $remainingWidth)
                        : max(1, (int) floor(($remainingWidth * $item['aspect']) / max($remainingAspect, 0.0001)));
                } else {
                    $targetWidth = max(1, (int) floor($item['aspect'] * $targetHeight));
                }

                imagecopyresampled(
                    $canvas,
                    $source,
                    $x,
                    $y,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $item['width'],
                    $item['height']
                );

                $x += $targetWidth + $gap;
                $remainingWidth -= $targetWidth;
                $remainingAspect -= $item['aspect'];
            }

            $y += $rowHeight + $gap;
        }

        $directory = storage_path('app/temp/loan-telegram-collages');
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (! is_dir($directory) || ! is_writable($directory)) {
            foreach ($images as $item) {
                imagedestroy($item['image']);
            }
            imagedestroy($canvas);

            return null;
        }

        $collagePath = $directory.'/loan-collage-'.Str::uuid().'.jpg';
        $saved = imagejpeg($canvas, $collagePath, 84);

        foreach ($images as $item) {
            imagedestroy($item['image']);
        }
        imagedestroy($canvas);

        return $saved && is_readable($collagePath) ? $collagePath : null;
    }

    protected function createImageResourceFromPath(string $path)
    {
        if (! is_readable($path)) {
            return null;
        }

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';

        if (in_array($mimeType, ['image/jpeg', 'image/jpg'], true) && function_exists('imagecreatefromjpeg')) {
            $image = @imagecreatefromjpeg($path);
        } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($path);
        } elseif ($mimeType === 'image/gif' && function_exists('imagecreatefromgif')) {
            $image = @imagecreatefromgif($path);
        } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($path);
        } elseif (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring((string) @file_get_contents($path));
        } else {
            $image = null;
        }

        return $image ?: null;
    }

    protected function resolveLoanTelegramLocation(object $loan): ?object
    {
        $query = DB::connection('mysql_loan')->table('loan_business_locations');
        if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $loanLocationId = ! empty($loan->business_location_id) ? (int) $loan->business_location_id : null;
        $mainLocationId = ! empty($loan->main_location_id) ? (int) $loan->main_location_id : null;

        if (! empty($loanLocationId) || ! empty($mainLocationId)) {
            $query->where(function ($where) use ($loanLocationId, $mainLocationId) {
                if (! empty($loanLocationId)) {
                    $where->orWhere('id', $loanLocationId);
                    if (Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                        $where->orWhere('main_location_id', $loanLocationId);
                    }
                }

                if (! empty($mainLocationId) && Schema::connection('mysql_loan')->hasColumn('loan_business_locations', 'main_location_id')) {
                    $where->orWhere('main_location_id', $mainLocationId);
                }
            });

            return $query->first();
        }

        return null;
    }
}
