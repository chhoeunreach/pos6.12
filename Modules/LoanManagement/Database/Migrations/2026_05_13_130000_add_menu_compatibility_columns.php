<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateLoans();
        $this->updateSchedules();
        $this->updatePayments();
        $this->updateCollectionVisits();
    }

    public function down(): void
    {
        // Keep non-destructive for live systems.
    }

    private function updateLoans(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loans')) {
            return;
        }

        Schema::connection('mysql_loan')->table('loans', function (Blueprint $table) {
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('balance_amount');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loans', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('collector_id');
            }
        });
    }

    private function updateSchedules(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payment_schedules')) {
            return;
        }

        $hasAmountDue = Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'amount_due');
        $hasAmountPaid = Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'amount_paid');
        $hasAmountBalance = Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'amount_balance');

        Schema::connection('mysql_loan')->table('loan_payment_schedules', function (Blueprint $table) use ($hasAmountDue, $hasAmountPaid, $hasAmountBalance) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'schedule_amount')) {
                $column = $table->decimal('schedule_amount', 18, 2)->default(0);
                if ($hasAmountDue) {
                    $column->after('amount_due');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'paid_amount')) {
                $column = $table->decimal('paid_amount', 18, 2)->default(0);
                if ($hasAmountPaid) {
                    $column->after('amount_paid');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payment_schedules', 'balance_amount')) {
                $column = $table->decimal('balance_amount', 18, 2)->default(0);
                if ($hasAmountBalance) {
                    $column->after('amount_balance');
                }
            }
        });

        if ($hasAmountDue) {
            DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNull('schedule_amount')->update(['schedule_amount' => DB::raw('amount_due')]);
        }
        if ($hasAmountPaid) {
            DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNull('paid_amount')->update(['paid_amount' => DB::raw('amount_paid')]);
        }
        if ($hasAmountBalance) {
            DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNull('balance_amount')->update(['balance_amount' => DB::raw('amount_balance')]);
        }
    }

    private function updatePayments(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_payments')) {
            return;
        }

        $hasPaidAt = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'paid_at');
        $hasPaymentRefNo = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'payment_ref_no');
        $hasChannel = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'channel');
        $hasReceivedByNameSnapshot = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'received_by_name_snapshot');
        $hasCustomerId = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'customer_id');
        $hasLoanId = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'loan_id');
        $hasAmount = Schema::connection('mysql_loan')->hasColumn('loan_payments', 'amount');

        Schema::connection('mysql_loan')->table('loan_payments', function (Blueprint $table) use (
            $hasPaidAt,
            $hasPaymentRefNo,
            $hasChannel,
            $hasReceivedByNameSnapshot,
            $hasCustomerId,
            $hasLoanId
        ) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'paid_date')) {
                $column = $table->date('paid_date')->nullable();
                if ($hasPaidAt) {
                    $column->after('paid_at');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'total_paid_base')) {
                $column = $table->decimal('total_paid_base', 18, 2)->default(0);
                if ($hasAmount) {
                    $column->after('amount');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'receipt_number')) {
                $column = $table->string('receipt_number')->nullable();
                if ($hasPaymentRefNo) {
                    $column->after('payment_ref_no');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'payment_method_snapshot')) {
                $column = $table->string('payment_method_snapshot')->nullable();
                if ($hasChannel) {
                    $column->after('channel');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'collected_by_name_snapshot')) {
                $column = $table->string('collected_by_name_snapshot')->nullable();
                if ($hasReceivedByNameSnapshot) {
                    $column->after('received_by_name_snapshot');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'customer_name_snapshot')) {
                $column = $table->string('customer_name_snapshot')->nullable();
                if ($hasCustomerId) {
                    $column->after('customer_id');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'loan_number_snapshot')) {
                $column = $table->string('loan_number_snapshot')->nullable();
                if ($hasLoanId) {
                    $column->after('loan_id');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'base_currency')) {
                $table->string('base_currency', 10)->default('USD')->after('payment_method_snapshot');
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_payments', 'proof_file_id')) {
                $table->unsignedBigInteger('proof_file_id')->nullable()->after('note');
            }
        });

        if ($hasPaidAt) {
            DB::connection('mysql_loan')->table('loan_payments')->whereNull('paid_date')->update(['paid_date' => DB::raw('DATE(paid_at)')]);
        }
        if ($hasAmount) {
            DB::connection('mysql_loan')->table('loan_payments')->where('total_paid_base', 0)->update(['total_paid_base' => DB::raw('amount')]);
        }
    }

    private function updateCollectionVisits(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_collection_visits')) {
            return;
        }

        $hasResult = Schema::connection('mysql_loan')->hasColumn('loan_collection_visits', 'result');
        $hasNote = Schema::connection('mysql_loan')->hasColumn('loan_collection_visits', 'note');

        Schema::connection('mysql_loan')->table('loan_collection_visits', function (Blueprint $table) use ($hasResult, $hasNote) {
            if (! Schema::connection('mysql_loan')->hasColumn('loan_collection_visits', 'status')) {
                $column = $table->string('status', 30)->nullable();
                if ($hasResult) {
                    $column->after('result');
                }
            }
            if (! Schema::connection('mysql_loan')->hasColumn('loan_collection_visits', 'visit_photo_file_id')) {
                $column = $table->unsignedBigInteger('visit_photo_file_id')->nullable();
                if ($hasNote) {
                    $column->after('note');
                }
            }
        });

        if ($hasResult) {
            DB::connection('mysql_loan')->table('loan_collection_visits')->whereNull('status')->update(['status' => DB::raw("COALESCE(result, 'pending')")]);
        } else {
            DB::connection('mysql_loan')->table('loan_collection_visits')->whereNull('status')->update(['status' => 'pending']);
        }
    }
};
