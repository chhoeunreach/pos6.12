<?php

namespace Modules\LoanManagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanActivityLogController extends Controller
{
    protected string $connection = 'mysql_loan';

    protected array $tableExistsCache = [];

    protected array $tableColumnsCache = [];

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'event' => trim((string) $request->input('event', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];

        $rows = collect()
            ->merge($this->activityRows())
            ->merge($this->loanRows())
            ->merge($this->paymentRows())
            ->merge($this->statusRows())
            ->merge($this->fileRows())
            ->merge($this->collectionVisitRows())
            ->merge($this->telegramRows())
            ->merge($this->importRows())
            ->merge($this->syncRows());

        $rows = $this->attachActors($rows)
            ->filter(fn ($row) => $this->matchesFilters($row, $filters))
            ->sortByDesc('sort_at')
            ->values();

        $eventOptions = $rows->pluck('event')->unique()->sort()->values();
        $summary = [
            'total' => $rows->count(),
            'recorded' => $rows->where('source', 'loan_activity_logs')->count(),
            'loans' => $rows->where('source', 'loans')->count(),
            'payments' => $rows->where('source', 'loan_payments')->count(),
            'files' => $rows->where('source', 'loan_files')->count(),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $logs = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('loanmanagement::activity_logs.index', compact('logs', 'filters', 'eventOptions', 'summary'));
    }

    protected function activityRows(): Collection
    {
        if (! $this->tableExists('loan_activity_logs')) {
            return collect();
        }

        return $this->recent('loan_activity_logs', [
            'id', 'user_id', 'user_name_snapshot', 'action', 'method', 'route_name', 'url', 'source',
            'subject_type', 'subject_id', 'response_status', 'ip_address', 'created_at',
        ])->map(function ($activity) {
            $reference = $activity->subject_type && $activity->subject_id
                ? Str::title(str_replace('_', ' ', $activity->subject_type)).' #'.$activity->subject_id
                : 'Activity #'.$activity->id;
            $details = $this->joinDetails([
                'Method: '.$this->display($activity->method),
                'Route: '.$this->display($activity->route_name),
                'URL: '.$this->display($activity->url),
                'IP: '.$this->display($activity->ip_address),
            ]);

            return $this->row($activity->action ?: 'Loan Activity', $reference, $details, $activity->user_id, $activity->created_at, $activity->response_status, 'loan_activity_logs', $activity->user_name_snapshot);
        });
    }

    protected function loanRows(): Collection
    {
        if (! $this->tableExists('loans')) {
            return collect();
        }

        return $this->recent('loans', [
            'id', 'loan_number', 'customer_name_snapshot', 'customer_phone_snapshot', 'product_name_snapshot',
            'principal_amount', 'total_amount', 'paid_amount', 'balance_amount', 'installment_count',
            'status', 'created_by', 'approved_by', 'created_at', 'updated_at',
        ])->flatMap(function ($loan) {
            $reference = $loan->loan_number ?: 'Loan #'.$loan->id;
            $details = $this->joinDetails([
                'Customer: '.$this->display($loan->customer_name_snapshot),
                'Phone: '.$this->display($loan->customer_phone_snapshot),
                'Product: '.$this->display($loan->product_name_snapshot),
                'Total: '.$this->money($loan->total_amount),
                'Balance: '.$this->money($loan->balance_amount),
                'Months: '.$this->display($loan->installment_count),
            ]);

            $rows = collect([
                $this->row('Loan Created', $reference, $details, $loan->created_by, $loan->created_at, $loan->status, 'loans'),
            ]);

            if (! empty($loan->updated_at) && ! empty($loan->created_at) && (string) $loan->updated_at !== (string) $loan->created_at) {
                $rows->push($this->row('Loan Updated', $reference, $details, $loan->created_by, $loan->updated_at, $loan->status, 'loans'));
            }

            return $rows;
        });
    }

    protected function paymentRows(): Collection
    {
        if (! $this->tableExists('loan_payments')) {
            return collect();
        }

        return $this->recent('loan_payments', [
            'id', 'payment_ref_no', 'loan_id', 'customer_id', 'received_by', 'received_by_name_snapshot',
            'channel', 'amount', 'penalty_amount', 'discount_amount', 'paid_at', 'status', 'note', 'created_at',
        ])->map(function ($payment) {
            $reference = $payment->payment_ref_no ?: 'Payment #'.$payment->id;
            $details = $this->joinDetails([
                'Loan ID: '.$this->display($payment->loan_id),
                'Amount: '.$this->money($payment->amount),
                'Method: '.$this->display($payment->channel),
                'Penalty: '.$this->money($payment->penalty_amount),
                'Discount: '.$this->money($payment->discount_amount),
                $payment->note ? 'Note: '.$payment->note : null,
            ]);

            return $this->row('Payment Recorded', $reference, $details, $payment->received_by, $payment->paid_at ?: $payment->created_at, $payment->status, 'loan_payments', $payment->received_by_name_snapshot);
        });
    }

    protected function statusRows(): Collection
    {
        if (! $this->tableExists('loan_status_logs')) {
            return collect();
        }

        return $this->recent('loan_status_logs', [
            'id', 'loan_id', 'from_status', 'to_status', 'changed_by', 'changed_by_name_snapshot', 'note', 'changed_at', 'created_at',
        ], 'changed_at')->map(function ($log) {
            $details = $this->joinDetails([
                $this->display($log->from_status, 'None').' -> '.$this->display($log->to_status),
                $log->note ? 'Note: '.$log->note : null,
            ]);

            return $this->row('Status Changed', 'Loan #'.$log->loan_id, $details, $log->changed_by, $log->changed_at ?: $log->created_at, $log->to_status, 'loan_status_logs', $log->changed_by_name_snapshot);
        });
    }

    protected function fileRows(): Collection
    {
        if (! $this->tableExists('loan_files')) {
            return collect();
        }

        return $this->recent('loan_files', [
            'id', 'fileable_type', 'fileable_id', 'category', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by', 'created_at',
        ])->map(function ($file) {
            $details = $this->joinDetails([
                'Type: '.$this->display($file->fileable_type),
                'Category: '.$this->display($file->category),
                'File: '.$this->display($file->original_name),
                'Size: '.$this->fileSize($file->size_bytes),
            ]);

            return $this->row('File Uploaded', '#'.$file->fileable_id, $details, $file->uploaded_by, $file->created_at, $file->category, 'loan_files');
        });
    }

    protected function collectionVisitRows(): Collection
    {
        if (! $this->tableExists('loan_collection_visits')) {
            return collect();
        }

        return $this->recent('loan_collection_visits', [
            'id', 'loan_id', 'customer_id', 'collector_id', 'collector_name_snapshot', 'address_snapshot', 'visited_at', 'result', 'note', 'created_at',
        ], 'visited_at')->map(function ($visit) {
            $details = $this->joinDetails([
                'Customer ID: '.$this->display($visit->customer_id),
                'Address: '.$this->display($visit->address_snapshot),
                $visit->note ? 'Note: '.$visit->note : null,
            ]);

            return $this->row('Collection Visit', 'Loan #'.$visit->loan_id, $details, $visit->collector_id, $visit->visited_at ?: $visit->created_at, $visit->result, 'loan_collection_visits', $visit->collector_name_snapshot);
        });
    }

    protected function telegramRows(): Collection
    {
        if (! $this->tableExists('loan_telegram_notifications')) {
            return collect();
        }

        return $this->recent('loan_telegram_notifications', [
            'id', 'loan_id', 'customer_id', 'event_code', 'chat_id', 'message', 'status', 'sent_at', 'created_at',
        ], 'sent_at')->map(function ($telegram) {
            $details = $this->joinDetails([
                'Event: '.$this->display($telegram->event_code),
                'Chat: '.$this->display($telegram->chat_id),
                'Message: '.Str::limit((string) $telegram->message, 120),
            ]);

            return $this->row('Telegram Notification', $telegram->loan_id ? 'Loan #'.$telegram->loan_id : 'Notification #'.$telegram->id, $details, null, $telegram->sent_at ?: $telegram->created_at, $telegram->status, 'loan_telegram_notifications');
        });
    }

    protected function importRows(): Collection
    {
        if (! $this->tableExists('loan_import_batches')) {
            return collect();
        }

        return $this->recent('loan_import_batches', [
            'id', 'batch_code', 'file_name', 'uploaded_by', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'imported_rows', 'note', 'created_at', 'updated_at',
        ])->map(function ($batch) {
            $details = $this->joinDetails([
                'File: '.$this->display($batch->file_name),
                'Rows: '.$this->display($batch->total_rows),
                'Imported: '.$this->display($batch->imported_rows),
                'Invalid: '.$this->display($batch->invalid_rows),
                $batch->note ? 'Note: '.$batch->note : null,
            ]);

            return $this->row('Import Batch', $batch->batch_code ?: 'Import #'.$batch->id, $details, $batch->uploaded_by, $batch->updated_at ?: $batch->created_at, $batch->status, 'loan_import_batches');
        });
    }

    protected function syncRows(): Collection
    {
        if (! $this->tableExists('loan_sync_logs')) {
            return collect();
        }

        return $this->recent('loan_sync_logs', [
            'id', 'source', 'sync_type', 'status', 'source_id', 'target_id', 'error_message', 'synced_at', 'created_at',
        ], 'synced_at')->map(function ($sync) {
            $details = $this->joinDetails([
                'Source: '.$this->display($sync->source),
                'Type: '.$this->display($sync->sync_type),
                'Source ID: '.$this->display($sync->source_id),
                'Target ID: '.$this->display($sync->target_id),
                $sync->error_message ? 'Error: '.Str::limit($sync->error_message, 120) : null,
            ]);

            return $this->row('Sync Log', 'Sync #'.$sync->id, $details, null, $sync->synced_at ?: $sync->created_at, $sync->status, 'loan_sync_logs');
        });
    }

    protected function recent(string $table, array $columns, ?string $dateColumn = null, int $limit = 250): Collection
    {
        $selected = $this->safeColumns($table, $columns);
        if (empty($selected)) {
            return collect();
        }

        $orderColumn = $dateColumn && $this->hasColumn($table, $dateColumn)
            ? $dateColumn
            : ($this->hasColumn($table, 'created_at') ? 'created_at' : 'id');

        return DB::connection($this->connection)
            ->table($table)
            ->select($selected)
            ->when($this->hasColumn($table, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get();
    }

    protected function row(string $event, string $reference, string $details, $actorId, $occurredAt, $status, string $source, ?string $actorSnapshot = null): array
    {
        $date = $this->parseDate($occurredAt);

        return [
            'event' => $event,
            'reference' => $reference ?: '-',
            'details' => $details ?: '-',
            'actor_id' => $actorId,
            'actor_snapshot' => $actorSnapshot,
            'actor' => $actorSnapshot ?: null,
            'occurred_at' => $date ? $date->format('Y-m-d H:i:s') : '-',
            'sort_at' => $date ? $date->timestamp : 0,
            'status' => $status ?: '-',
            'source' => $source,
        ];
    }

    protected function attachActors(Collection $rows): Collection
    {
        $actorIds = $rows->pluck('actor_id')->filter()->unique()->values();
        if ($actorIds->isEmpty() || ! Schema::hasTable('users')) {
            return $rows->map(function ($row) {
                $row['actor'] = $row['actor_snapshot'] ?: ($row['actor_id'] ? 'User #'.$row['actor_id'] : '-');
                return $row;
            });
        }

        $columns = Schema::getColumnListing('users');
        $select = array_values(array_intersect(['id', 'first_name', 'last_name', 'username', 'name', 'email'], $columns));
        $users = DB::table('users')->select($select)->whereIn('id', $actorIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $row['actor_id'] ? $users->get($row['actor_id']) : null;
            $name = $row['actor_snapshot'];

            if (! $name && $user) {
                $name = trim(implode(' ', array_filter([
                    $user->first_name ?? null,
                    $user->last_name ?? null,
                ]))) ?: ($user->username ?? $user->name ?? $user->email ?? null);
            }

            $row['actor'] = $name ?: ($row['actor_id'] ? 'User #'.$row['actor_id'] : '-');

            return $row;
        });
    }

    protected function matchesFilters(array $row, array $filters): bool
    {
        if ($filters['event'] !== '' && $row['event'] !== $filters['event']) {
            return false;
        }

        if ($filters['date_from'] !== '') {
            $from = $this->parseDate($filters['date_from']);
            if ($from && $row['sort_at'] < $from->startOfDay()->timestamp) {
                return false;
            }
        }

        if ($filters['date_to'] !== '') {
            $to = $this->parseDate($filters['date_to']);
            if ($to && $row['sort_at'] > $to->endOfDay()->timestamp) {
                return false;
            }
        }

        if ($filters['search'] !== '') {
            $haystack = Str::lower(implode(' ', [
                $row['event'], $row['reference'], $row['details'], $row['actor'], $row['status'], $row['source'],
            ]));

            return Str::contains($haystack, Str::lower($filters['search']));
        }

        return true;
    }

    protected function tableExists(string $table): bool
    {
        if (! array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = Schema::connection($this->connection)->hasTable($table);
        }

        return $this->tableExistsCache[$table];
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    protected function tableColumns(string $table): array
    {
        if (! array_key_exists($table, $this->tableColumnsCache)) {
            $this->tableColumnsCache[$table] = $this->tableExists($table)
                ? Schema::connection($this->connection)->getColumnListing($table)
                : [];
        }

        return $this->tableColumnsCache[$table];
    }

    protected function safeColumns(string $table, array $columns): array
    {
        return array_values(array_intersect($columns, $this->tableColumns($table)));
    }

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function joinDetails(array $parts): string
    {
        return implode(' | ', array_values(array_filter($parts, fn ($part) => $part !== null && trim((string) $part) !== '')));
    }

    protected function display($value, string $fallback = '-'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    protected function money($value): string
    {
        return number_format((float) ($value ?: 0), 2);
    }

    protected function fileSize($bytes): string
    {
        if (empty($bytes)) {
            return '-';
        }

        $bytes = (float) $bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 2).' KB';
    }
}
