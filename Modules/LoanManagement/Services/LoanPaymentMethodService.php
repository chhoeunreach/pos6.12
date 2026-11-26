<?php

namespace Modules\LoanManagement\Services;

use App\Utils\TransactionUtil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanPaymentMethodService
{
    /**
     * Normalized alias groups mapped to canonical method keys.
     */
    protected const ALIAS_MAP = [
        'cash' => ['cash', 'cashmoney', 'cashinhand', 'សាច់ប្រាក់', 'សាច់ប្រាក់សុទ្ធ', 'សាច់ប្រាក់សុទ្ធcash'],
        'aba' => ['aba', 'ababank', 'abapay', 'abapayway', 'ធនាគារអេប៊ីអេ', 'ធនាគារអេប៊ីអេaba', 'ធនាគារអេប៊ីអេababank'],
        'wing' => ['wing', 'wingmoney', 'wingbank', 'វីង', 'វីងវេលុយ', 'វីងវេលុយwing', 'វីងវេលុយwingmoney'],
        'acleda' => ['acleda', 'acledabank', 'acledaunity', 'ធនាគារអេស៊ីលីដា', 'ធនាគារអេស៊ីលីដាacleda', 'អេស៊ីលីដា'],
        'card' => ['card', 'creditcard', 'debitcard', 'កាតឥណទានឥណពន្ធcard', 'កាតឥណទាន', 'កាត'],
        'cheque' => ['cheque', 'check', 'មូលប្បទានប័ត្រcheque', 'មូលប្បទានប័ត្រ'],
        'bank_transfer' => ['banktransfer', 'transfer', 'bank', 'et', 'ផ្ទេរប្រាក់តាមធនាគារbanktransfer', 'ផ្ទេរប្រាក់តាមធនាគារ'],
        'advance' => ['advance', 'advancepayment', 'ប្រាក់បង់មុនបុរេប្រទានadvance', 'ប្រាក់បង់មុន'],
        'other' => ['other', 'others', 'ផ្សេងៗother', 'ផ្សេងៗ'],
    ];

    public function defaultLabels(?bool $isKhmer = null): array
    {
        $isKhmer ??= session('user.language', config('app.locale')) === 'km';

        return [
            'advance' => $isKhmer ? 'ប្រាក់បង់មុន / បុរេប្រទាន (Advance)' : 'Advance Payment',
            'cash' => $isKhmer ? 'សាច់ប្រាក់សុទ្ធ (Cash)' : 'Cash',
            'card' => $isKhmer ? 'កាតឥណទាន / ឥណពន្ធ (Card)' : 'Card',
            'cheque' => $isKhmer ? 'មូលប្បទានប័ត្រ (Cheque)' : 'Cheque',
            'bank_transfer' => $isKhmer ? 'ផ្ទេរប្រាក់តាមធនាគារ (Bank Transfer)' : 'Bank Transfer',
            'aba' => 'ធនាគារអេប៊ីអេ (ABA Bank)',
            'wing' => 'វីងវេលុយ (Wing Money)',
            'acleda' => 'ធនាគារអេស៊ីលីដា (ACLEDA)',
            'custom_pay_1' => $isKhmer ? 'វិធីទូទាត់ពិសេស ១' : 'Custom Payment 1',
            'custom_pay_2' => $isKhmer ? 'វិធីទូទាត់ពិសេស ២' : 'Custom Payment 2',
            'custom_pay_3' => $isKhmer ? 'វិធីទូទាត់ពិសេស ៣' : 'Custom Payment 3',
            'custom_pay_4' => $isKhmer ? 'វិធីទូទាត់ពិសេស ៤' : 'Custom Payment 4',
            'custom_pay_5' => $isKhmer ? 'វិធីទូទាត់ពិសេស ៥' : 'Custom Payment 5',
            'custom_pay_6' => $isKhmer ? 'វិធីទូទាត់ពិសេស ៦' : 'Custom Payment 6',
            'custom_pay_7' => $isKhmer ? 'វិធីទូទាត់ពិសេស ៧' : 'Custom Payment 7',
            'other' => $isKhmer ? 'ផ្សេងៗ (Other)' : 'Other',
        ];
    }

    /**
     * Unified active payment method options [key => displayLabel].
     */
    public function options(?object $loan = null, string $connection = 'mysql_loan', bool $includeInactive = false): array
    {
        $known = $this->defaultLabels();
        $cleaned = [];
        $inactiveNormalized = [];

        foreach ($this->loadConfiguredMethods($connection) as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                continue;
            }

            $code = trim((string) ($row->code ?? ''));
            $key = $code !== '' ? strtolower($code) : $this->slugKey($name);

            $isActive = ! property_exists($row, 'is_active') || $row->is_active === null || (bool) $row->is_active;
            if (! $isActive && ! $includeInactive) {
                $inactiveNormalized[] = $this->normalize($key);
                $inactiveNormalized[] = $this->normalize($name);
                continue;
            }

            $cleaned[$key] = $name;
        }

        foreach ($this->loadPosPaymentTypes($loan) as $rawKey => $rawLabel) {
            $keyStr = strtolower(trim((string) $rawKey));
            if ($keyStr === '') {
                continue;
            }

            $labelStr = $this->cleanTranslationLabel((string) $rawLabel, $keyStr, $known);
            $normKey = $this->normalize($keyStr);
            $normLabel = $this->normalize($labelStr);

            if (in_array($normKey, $inactiveNormalized, true) || ($normLabel !== '' && in_array($normLabel, $inactiveNormalized, true))) {
                continue;
            }

            if (array_key_exists($keyStr, $cleaned)) {
                continue;
            }

            // Preserve custom POS labels (e.g. custom_pay_1 => 'ABA Pay') while normalizing generic built-in labels.
            $isCustomSlot = str_starts_with($keyStr, 'custom_pay_');
            $isGenericCustom = $isCustomSlot && preg_match('/^custom\s+payment\s+\d+$/i', $labelStr);

            if ($isCustomSlot && $labelStr !== '' && ! $isGenericCustom) {
                $cleaned[$keyStr] = $labelStr;
            } elseif (isset($known[$keyStr])) {
                $cleaned[$keyStr] = $known[$keyStr];
            } else {
                $cleaned[$keyStr] = $labelStr !== '' ? $labelStr : $this->humanize($keyStr);
            }
        }

        return ! empty($cleaned) ? $cleaned : ['cash' => $known['cash']];
    }

    /**
     * Quick-add presets for the Business Settings Payment Methods tab.
     */
    public function presetChannels(): array
    {
        return [
            ['code' => 'cash', 'name' => 'Cash'],
            ['code' => 'aba', 'name' => 'ABA Bank'],
            ['code' => 'wing', 'name' => 'Wing Money'],
            ['code' => 'acleda', 'name' => 'ACLEDA'],
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['code' => 'card', 'name' => 'Card'],
            ['code' => 'cheque', 'name' => 'Cheque'],
            ['code' => 'other', 'name' => 'Other'],
        ];
    }

    /**
     * Normalize any input (key, raw POS string, Khmer/English label, or legacy snapshot)
     * to a matching key inside $options when possible.
     */
    public function resolveKey(?string $value, array $options = [], string $connection = 'mysql_loan'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (empty($options)) {
            $options = $this->options(null, $connection, true);
        }

        $stripped = $this->stripTranslationPrefix($raw);
        $lower = strtolower($stripped);
        if (array_key_exists($lower, $options)) {
            return $lower;
        }

        $normInput = $this->normalize($stripped);
        if ($normInput === '') {
            return '';
        }

        // Exact normalized match against option keys or labels
        foreach ($options as $key => $label) {
            if ($this->normalize((string) $key) === $normInput || $this->normalize((string) $label) === $normInput) {
                return (string) $key;
            }
        }

        // Match via alias groups (e.g. 'ធនាគារអេប៊ីអេ (ABA)' -> 'aba', or custom_pay labeled 'ABA')
        $canonicalGroup = $this->canonicalAliasGroup($normInput);
        if ($canonicalGroup !== null) {
            if (array_key_exists($canonicalGroup, $options)) {
                return $canonicalGroup;
            }

            foreach ($options as $key => $label) {
                if ($this->canonicalAliasGroup($this->normalize((string) $key)) === $canonicalGroup
                    || $this->canonicalAliasGroup($this->normalize((string) $label)) === $canonicalGroup) {
                    return (string) $key;
                }
            }

            return $canonicalGroup;
        }

        return $lower;
    }

    /**
     * Resolve any stored method key or legacy snapshot into a consistent human-readable label.
     */
    public function resolveDisplayLabel(?string $value, array $options = [], string $connection = 'mysql_loan'): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || strtolower($raw) === 'unknown') {
            return '-';
        }

        if (empty($options)) {
            $options = $this->options(null, $connection, true);
        }

        // Handle pre-formatted compound strings like "Cash + ABA"
        if (str_contains($raw, ' + ')) {
            $parts = array_filter(array_map(
                fn (string $part) => $this->resolveDisplayLabel($part, $options, $connection),
                explode(' + ', $raw)
            ), fn (string $part) => $part !== '' && $part !== '-');

            return ! empty($parts) ? implode(' + ', array_unique($parts)) : '-';
        }

        $resolvedKey = $this->resolveKey($raw, $options, $connection);
        if ($resolvedKey !== '' && array_key_exists($resolvedKey, $options)) {
            return (string) $options[$resolvedKey];
        }

        $stripped = $this->stripTranslationPrefix($raw);
        $known = $this->defaultLabels();
        $lower = strtolower($stripped);
        if (isset($known[$lower])) {
            return $known[$lower];
        }

        $group = $this->canonicalAliasGroup($this->normalize($stripped));
        if ($group !== null && isset($known[$group])) {
            return $known[$group];
        }

        if (str_contains($stripped, '_') && ! str_contains($stripped, ' ')) {
            return $this->humanize($stripped);
        }

        return $stripped;
    }

    /**
     * Canonical label to save into payment_method_snapshot / channel when recording or updating a payment.
     */
    public function canonicalName(string $method, array $options = [], string $connection = 'mysql_loan'): string
    {
        $method = trim($method);
        if ($method === '') {
            $method = array_key_exists('cash', $options) ? 'cash' : (string) (array_key_first($options) ?? 'cash');
        }

        if (empty($options)) {
            $options = $this->options(null, $connection);
        }

        $resolvedKey = $this->resolveKey($method, $options, $connection);
        if ($resolvedKey !== '' && array_key_exists($resolvedKey, $options)) {
            return (string) $options[$resolvedKey];
        }

        $label = $this->resolveDisplayLabel($method, $options, $connection);

        return $label !== '-' ? $label : $this->humanize($method);
    }

    /**
     * Return all normalized search terms matching a selected filter value (key or label).
     */
    public function aliasesForFilter(string $filterValue, array $options = [], string $connection = 'mysql_loan'): array
    {
        $filterValue = trim($filterValue);
        if ($filterValue === '') {
            return [];
        }

        if (empty($options)) {
            $options = $this->options(null, $connection, true);
        }

        $terms = [$filterValue];
        $resolvedKey = $this->resolveKey($filterValue, $options, $connection);
        if ($resolvedKey !== '') {
            $terms[] = $resolvedKey;
            if (isset($options[$resolvedKey])) {
                $terms[] = (string) $options[$resolvedKey];
            }
        }

        $group = $this->canonicalAliasGroup($this->normalize($resolvedKey !== '' ? $resolvedKey : $filterValue));
        if ($group !== null) {
            $terms[] = $group;
            foreach (self::ALIAS_MAP[$group] ?? [] as $alias) {
                $terms[] = $alias;
            }
            $known = $this->defaultLabels();
            $knownEn = $this->defaultLabels(false);
            $knownKm = $this->defaultLabels(true);
            foreach ([$known, $knownEn, $knownKm] as $dict) {
                if (isset($dict[$group])) {
                    $terms[] = $dict[$group];
                }
            }
        }

        foreach ($this->loadConfiguredMethods($connection) as $row) {
            $rowCode = strtolower(trim((string) ($row->code ?? '')));
            $rowName = trim((string) ($row->name ?? ''));
            if ($rowCode !== '' && ($rowCode === $resolvedKey || $this->normalize($rowName) === $this->normalize($filterValue))) {
                $terms[] = $rowCode;
                if ($rowName !== '') {
                    $terms[] = $rowName;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $terms), fn ($v) => $v !== '')));
    }

    /**
     * Filter a loan_payments query (aliased as $alias) cleanly across snapshot/channel/method and loan_payment_details.
     */
    public function applyMethodFilter($query, string $filterValue, string $connection = 'mysql_loan', array $options = [], string $alias = 'p'): void
    {
        $terms = $this->aliasesForFilter($filterValue, $options, $connection);
        if (empty($terms)) {
            return;
        }

        $paymentCols = $this->existingColumns($connection, 'loan_payments', ['payment_method_snapshot', 'channel', 'method']);
        $detailCols = Schema::connection($connection)->hasTable('loan_payment_details')
            ? $this->existingColumns($connection, 'loan_payment_details', ['payment_method_snapshot', 'method'])
            : [];

        if (empty($paymentCols) && empty($detailCols)) {
            return;
        }

        $query->where(function ($outer) use ($terms, $paymentCols, $detailCols, $connection, $alias) {
            $first = true;
            foreach ($paymentCols as $col) {
                foreach ($terms as $term) {
                    $needle = '%'.mb_strtolower($term).'%';
                    if ($first) {
                        $outer->whereRaw("LOWER(COALESCE({$alias}.{$col}, '')) LIKE ?", [$needle]);
                        $first = false;
                    } else {
                        $outer->orWhereRaw("LOWER(COALESCE({$alias}.{$col}, '')) LIKE ?", [$needle]);
                    }
                }
            }

            if (! empty($detailCols) && Schema::connection($connection)->hasColumn('loan_payment_details', 'payment_id')) {
                $subClause = function ($sub) use ($terms, $detailCols, $alias) {
                    $sub->selectRaw('1')
                        ->from('loan_payment_details as d_filter')
                        ->whereColumn('d_filter.payment_id', $alias.'.id')
                        ->where(function ($inner) use ($terms, $detailCols) {
                            $innerFirst = true;
                            foreach ($detailCols as $col) {
                                foreach ($terms as $term) {
                                    $needle = '%'.mb_strtolower($term).'%';
                                    if ($innerFirst) {
                                        $inner->whereRaw("LOWER(COALESCE(d_filter.{$col}, '')) LIKE ?", [$needle]);
                                        $innerFirst = false;
                                    } else {
                                        $inner->orWhereRaw("LOWER(COALESCE(d_filter.{$col}, '')) LIKE ?", [$needle]);
                                    }
                                }
                            }
                        });
                };

                $first ? $outer->whereExists($subClause) : $outer->orWhereExists($subClause);
            }
        });
    }

    /**
     * Batch-enrich a collection of payment rows with unified method labels and split breakdowns.
     */
    public function enrichPaymentsWithMethods(iterable $payments, string $connection = 'mysql_loan', array $options = []): void
    {
        $collection = $payments instanceof Collection ? $payments : collect($payments);
        if ($collection->isEmpty()) {
            return;
        }

        if (empty($options)) {
            $options = $this->options(null, $connection, true);
        }

        $detailsByPayment = collect();
        $paymentIds = $collection->pluck('id')->filter()->unique()->values()->all();

        if (! empty($paymentIds)
            && Schema::connection($connection)->hasTable('loan_payment_details')
            && Schema::connection($connection)->hasColumn('loan_payment_details', 'payment_id')) {
            $detailsByPayment = DB::connection($connection)
                ->table('loan_payment_details')
                ->whereIn('payment_id', $paymentIds)
                ->orderBy('id')
                ->get()
                ->groupBy('payment_id');
        }

        foreach ($collection as $payment) {
            $details = $detailsByPayment->get($payment->id, collect());
            $this->enrichSinglePayment($payment, $details, $connection, $options);
        }
    }

    /**
     * Enrich a single payment object (plus optional detail collection) with unified display fields.
     */
    public function enrichSinglePayment(object $payment, $details = null, string $connection = 'mysql_loan', array $options = []): object
    {
        if (empty($options)) {
            $options = $this->options(null, $connection, true);
        }

        $detailRows = $details instanceof Collection ? $details : collect($details ?? []);
        $currency = strtoupper(trim((string) ($payment->currency ?? 'USD'))) ?: 'USD';
        $parts = [];

        if ($detailRows->isNotEmpty()) {
            foreach ($detailRows as $detail) {
                $rawCandidate = trim((string) ($detail->method ?? ''));
                $snapshotCandidate = trim((string) ($detail->payment_method_snapshot ?? ''));
                $sourceValue = $rawCandidate !== '' ? $rawCandidate : $snapshotCandidate;
                $label = $this->resolveDisplayLabel($sourceValue, $options, $connection);
                if ($label === '-' && $snapshotCandidate !== '' && $sourceValue !== $snapshotCandidate) {
                    $label = $this->resolveDisplayLabel($snapshotCandidate, $options, $connection);
                }

                $key = $this->resolveKey($sourceValue !== '' ? $sourceValue : $label, $options, $connection);
                $lineCurrency = strtoupper(trim((string) ($detail->currency ?? $currency))) ?: $currency;
                $lineAmount = (float) ($detail->amount_base ?? $detail->amount ?? 0);
                $formattedAmount = $this->formatAmount($lineAmount, $currency);

                $detail->display_method = $label;
                $detail->resolved_method_key = $key;

                $parts[] = [
                    'key' => $key,
                    'label' => $label,
                    'amount' => $lineAmount,
                    'currency' => $lineCurrency,
                    'formatted_amount' => $formattedAmount,
                    'reference_number' => $detail->reference_number ?? ($detail->transaction_no ?? null),
                    'note' => $detail->note ?? null,
                ];
            }
        }

        if (count($parts) === 1) {
            $payment->payment_method = $parts[0]['label'];
            $payment->payment_method_raw = $parts[0]['key'] ?: $parts[0]['label'];
            $payment->payment_method_parts = $parts;
            $payment->is_split_method = false;

            return $payment;
        }

        if (count($parts) > 1) {
            $payment->payment_method = implode(', ', array_map(
                fn (array $part) => $part['label'].' ('.$part['formatted_amount'].')',
                $parts
            ));
            $payment->payment_method_raw = implode(' ', array_column($parts, 'key'));
            $payment->payment_method_parts = $parts;
            $payment->is_split_method = true;

            return $payment;
        }

        $fallbackRaw = $this->firstNonEmpty([
            $payment->payment_method_snapshot ?? null,
            $payment->channel ?? null,
            $payment->method ?? null,
            $payment->payment_method ?? null,
        ]);

        if ($fallbackRaw === '' || strtolower($fallbackRaw) === 'multiple') {
            $payment->payment_method = $fallbackRaw === '' ? '-' : 'Multiple';
            $payment->payment_method_raw = $fallbackRaw;
            $payment->payment_method_parts = [];
            $payment->is_split_method = false;

            return $payment;
        }

        $resolvedLabel = $this->resolveDisplayLabel($fallbackRaw, $options, $connection);
        $resolvedKey = $this->resolveKey($fallbackRaw, $options, $connection);
        $paymentAmount = (float) ($payment->amount ?? $payment->total_paid_base ?? $payment->total_paid ?? 0);

        $payment->payment_method = $resolvedLabel;
        $payment->payment_method_raw = $resolvedKey ?: $resolvedLabel;
        $payment->payment_method_parts = $resolvedLabel !== '-' ? [[
            'key' => $resolvedKey,
            'label' => $resolvedLabel,
            'amount' => $paymentAmount,
            'currency' => $currency,
            'formatted_amount' => $this->formatAmount($paymentAmount, $currency),
            'reference_number' => $payment->reference_number ?? null,
            'note' => $payment->note ?? null,
        ]] : [];
        $payment->is_split_method = str_contains($resolvedLabel, ' + ');

        return $payment;
    }

    public function normalize(string $value): string
    {
        $clean = $this->stripTranslationPrefix(trim($value));

        return mb_strtolower((string) preg_replace('/[\s\-_\/\(\)\.\[\]]+/u', '', $clean));
    }

    /**
     * Classify any method name/code (English or Khmer) into a reporting bucket.
     */
    public function bucket(string $value, string $connection = 'mysql_loan'): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 'cash';
        }

        $upper = strtoupper($trimmed);
        if (str_contains($upper, 'E&T') || str_contains($upper, 'E T') || $upper === 'ET') {
            return 'et';
        }

        $key = $this->resolveKey($trimmed, [], $connection);
        $norm = $this->normalize($trimmed);

        if ($key === 'cash' || $this->canonicalAliasGroup($norm) === 'cash' || str_contains($upper, 'CASH') || str_contains($trimmed, 'សាច់ប្រាក់')) {
            return 'cash';
        }
        if ($key === 'aba' || $this->canonicalAliasGroup($norm) === 'aba' || str_contains($upper, 'ABA') || str_contains($trimmed, 'អេប៊ីអេ')) {
            return 'aba';
        }
        if ($key === 'acleda' || $this->canonicalAliasGroup($norm) === 'acleda' || str_contains($upper, 'ACLEDA') || str_contains($trimmed, 'អេស៊ីលីដា')) {
            return 'acleda';
        }
        if ($key === 'wing' || $this->canonicalAliasGroup($norm) === 'wing' || str_contains($upper, 'WING') || str_contains($trimmed, 'វីង')) {
            return 'wing';
        }
        if ($key === 'card' || $this->canonicalAliasGroup($norm) === 'card' || str_contains($upper, 'CARD') || str_contains($upper, 'VISA') || str_contains($upper, 'MASTER') || str_contains($trimmed, 'កាត')) {
            return 'card';
        }

        return 'other';
    }

    public function isCash(string $value, string $connection = 'mysql_loan'): bool
    {
        return $this->bucket($value, $connection) === 'cash';
    }

    protected function loadConfiguredMethods(string $connection): Collection
    {
        if (! Schema::connection($connection)->hasTable('loan_payment_methods')) {
            return collect();
        }

        $query = DB::connection($connection)->table('loan_payment_methods');
        if (Schema::connection($connection)->hasColumn('loan_payment_methods', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('name')->get();
    }

    protected function loadPosPaymentTypes(?object $loan): array
    {
        try {
            $types = app(TransactionUtil::class)->payment_types(
                $loan->main_location_id ?? null,
                true,
                (int) (session('user.business_id') ?? 0)
            );

            return is_array($types) ? $types : [];
        } catch (\Throwable $e) {
            return [
                'cash' => 'Cash',
                'aba' => 'ABA Bank',
                'wing' => 'Wing Money',
            ];
        }
    }

    protected function cleanTranslationLabel(string $rawLabel, string $keyStr, array $known): string
    {
        $labelStr = trim($rawLabel);
        if ($labelStr === '') {
            return $known[$keyStr] ?? $this->humanize($keyStr);
        }

        if (str_starts_with($labelStr, 'lang_v1.') || str_starts_with($labelStr, 'messages.')) {
            $translated = __($labelStr);
            if (is_string($translated) && $translated !== $labelStr && trim($translated) !== '') {
                return trim($translated);
            }

            $subKey = strtolower($this->stripTranslationPrefix($labelStr));

            return $known[$subKey] ?? $known[$keyStr] ?? $this->humanize($subKey);
        }

        return $labelStr;
    }

    protected function stripTranslationPrefix(string $value): string
    {
        if (str_starts_with($value, 'lang_v1.') || str_starts_with($value, 'messages.')) {
            return (string) preg_replace('/^(lang_v1|messages)\./i', '', $value);
        }

        return $value;
    }

    protected function canonicalAliasGroup(string $normalizedValue): ?string
    {
        if ($normalizedValue === '') {
            return null;
        }

        foreach (self::ALIAS_MAP as $group => $aliases) {
            if (in_array($normalizedValue, $aliases, true)) {
                return $group;
            }
        }

        return null;
    }

    protected function slugKey(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));

        return $slug !== '' ? $slug : strtolower(str_replace(' ', '_', $name));
    }

    protected function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', trim($value)));
    }

    protected function formatAmount(float $amount, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'KHR' => '៛',
            'THB' => '฿',
            default => '$',
        };

        return $symbol.number_format($amount, 2);
    }

    protected function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    protected function existingColumns(string $connection, string $table, array $candidates): array
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return [];
        }

        $columns = Schema::connection($connection)->getColumnListing($table);

        return array_values(array_intersect($candidates, $columns));
    }
}
