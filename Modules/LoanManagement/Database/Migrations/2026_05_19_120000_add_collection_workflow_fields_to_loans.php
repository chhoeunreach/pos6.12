<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            $this->stringColumn($table, 'collection_status', 50, 'status');
            $this->stringColumn($table, 'risk_level', 50, 'collection_status');
            $this->unsignedTinyIntegerColumn($table, 'collection_priority', 'risk_level');
            $this->dateColumn($table, 'ptp_date', 'collection_priority');
            $this->decimalColumn($table, 'ptp_amount', 'ptp_date');
            $this->textColumn($table, 'ptp_note', 'ptp_amount');
            $this->stringColumn($table, 'ptp_status', 30, 'ptp_note');
            $this->unsignedIntegerColumn($table, 'broken_ptp_count', 'ptp_status');
            $this->dateTimeColumn($table, 'last_contact_at', 'broken_ptp_count');
            $this->stringColumn($table, 'last_contact_result', 100, 'last_contact_at');
            $this->dateTimeColumn($table, 'next_followup_at', 'last_contact_result');
            $this->booleanColumn($table, 'field_visit_required', 'next_followup_at');
            $this->stringColumn($table, 'skip_level', 30, 'field_visit_required');
            $this->stringColumn($table, 'legal_stage', 100, 'skip_level');
            $this->stringColumn($table, 'recovery_stage', 100, 'legal_stage');
            $this->stringColumn($table, 'repossession_status', 100, 'recovery_stage');
            $this->dateTimeColumn($table, 'blacklisted_at', 'repossession_status');
            $this->dateTimeColumn($table, 'written_off_at', 'blacklisted_at');
            $this->unsignedBigIntegerColumn($table, 'assigned_collector_id', 'written_off_at');
            $this->stringColumn($table, 'assigned_collection_team', 100, 'assigned_collector_id');
            $this->integerColumn($table, 'days_past_due', 'assigned_collection_team');
            $this->stringColumn($table, 'overdue_bucket', 30, 'days_past_due');
            $this->unsignedIntegerColumn($table, 'contact_attempt_count', 'overdue_bucket');
            $this->dateColumn($table, 'last_payment_date', 'contact_attempt_count');
            $this->decimalColumn($table, 'last_payment_amount', 'last_payment_date');
            $this->unsignedSmallIntegerColumn($table, 'recovery_score', 'last_payment_amount');
        });

        $this->addIndex('loans', 'collection_status');
        $this->addIndex('loans', 'risk_level');
        $this->addIndex('loans', 'overdue_bucket');
        $this->addIndex('loans', 'skip_level');
        $this->addIndex('loans', 'assigned_collector_id');
        $this->addIndex('loans', 'ptp_date');
        $this->addIndex('loans', 'next_followup_at');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loans')) {
            return;
        }

        Schema::connection($this->connection)->table('loans', function (Blueprint $table) {
            foreach ([
                'collection_status', 'risk_level', 'collection_priority', 'ptp_date', 'ptp_amount',
                'ptp_note', 'ptp_status', 'broken_ptp_count', 'last_contact_at', 'last_contact_result',
                'next_followup_at', 'field_visit_required', 'skip_level', 'legal_stage', 'recovery_stage',
                'repossession_status', 'blacklisted_at', 'written_off_at', 'assigned_collector_id',
                'assigned_collection_team', 'days_past_due', 'overdue_bucket', 'contact_attempt_count',
                'last_payment_date', 'last_payment_amount', 'recovery_score',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function stringColumn(Blueprint $table, string $name, int $length, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->string($name, $length)->nullable()->after($after);
        }
    }

    protected function textColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->text($name)->nullable()->after($after);
        }
    }

    protected function dateColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->date($name)->nullable()->after($after);
        }
    }

    protected function dateTimeColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->dateTime($name)->nullable()->after($after);
        }
    }

    protected function decimalColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->decimal($name, 18, 2)->nullable()->after($after);
        }
    }

    protected function booleanColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->boolean($name)->default(false)->after($after);
        }
    }

    protected function unsignedBigIntegerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->unsignedBigInteger($name)->nullable()->after($after);
        }
    }

    protected function unsignedIntegerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->unsignedInteger($name)->default(0)->after($after);
        }
    }

    protected function unsignedTinyIntegerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->unsignedTinyInteger($name)->default(0)->after($after);
        }
    }

    protected function unsignedSmallIntegerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->unsignedSmallInteger($name)->default(0)->after($after);
        }
    }

    protected function integerColumn(Blueprint $table, string $name, string $after): void
    {
        if (! Schema::connection($this->connection)->hasColumn('loans', $name)) {
            $table->integer($name)->default(0)->after($after);
        }
    }

    protected function addIndex(string $table, string $column): void
    {
        if (Schema::connection($this->connection)->hasColumn($table, $column)) {
            try {
                Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->index($column);
                });
            } catch (\Throwable $e) {
                // Index may already exist on upgraded installations.
            }
        }
    }
};
