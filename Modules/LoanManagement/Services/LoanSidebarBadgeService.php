<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanSidebarBadgeService
{
    protected string $connection = 'mysql_loan';

    public function overdueCount(): int
    {
        if (! $this->hasTable('loan_payment_schedules')) {
            return 0;
        }

        return (int) DB::connection($this->connection)
            ->table('loan_payment_schedules')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', ['pending', 'unpaid', 'partial', 'late'])
            ->count();
    }

    public function unreadChatCount(): int
    {
        if (! $this->hasTable('loan_chat_messages')) {
            return 0;
        }

        return (int) DB::connection($this->connection)
            ->table('loan_chat_messages')
            ->where(function ($q) {
                $q->whereNull('is_read')->orWhere('is_read', 0);
            })
            ->count();
    }

    public function pendingVisitsCount(): int
    {
        if (! $this->hasTable('loan_collection_visits')) {
            return 0;
        }

        try {
            $q = DB::connection($this->connection)->table('loan_collection_visits');

            if ($this->hasColumn('loan_collection_visits', 'result')) {
                $q->whereIn('result', ['pending', 'follow_up', 'rescheduled']);
            } elseif ($this->hasColumn('loan_collection_visits', 'status')) {
                $q->whereIn('status', ['pending', 'follow_up', 'rescheduled']);
            } elseif ($this->hasColumn('loan_collection_visits', 'visited_at')) {
                $q->whereNull('visited_at');
            } else {
                return 0;
            }

            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function hasTable(string $table): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
