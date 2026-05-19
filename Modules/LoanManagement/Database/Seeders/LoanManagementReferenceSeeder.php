<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanManagementReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::connection('mysql_loan')->hasTable('loan_currencies')) {
            $this->seedLoanCurrencies();
        }

        if (Schema::connection('mysql_loan')->hasTable('loan_payment_methods')) {
            $this->seedLoanPaymentMethods();
        }
    }

    private function seedLoanCurrencies(): void
    {
        $columns = [
            'name' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'name'),
            'exchange_rate' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'exchange_rate'),
            'is_default' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'is_default'),
            'is_active' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'is_active'),
            'updated_at' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'updated_at'),
            'created_at' => Schema::connection('mysql_loan')->hasColumn('loan_currencies', 'created_at'),
        ];

        $now = now();
        $rows = [
            'USD' => ['name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => 1, 'is_active' => 1],
            'KHR' => ['name' => 'Cambodian Riel', 'exchange_rate' => 4100, 'is_default' => 0, 'is_active' => 1],
        ];

        foreach ($rows as $code => $data) {
            $updateData = [];
            foreach ($data as $key => $value) {
                if (! empty($columns[$key])) {
                    $updateData[$key] = $value;
                }
            }
            if ($columns['updated_at']) {
                $updateData['updated_at'] = $now;
            }
            if ($columns['created_at']) {
                $updateData['created_at'] = $now;
            }

            DB::connection('mysql_loan')->table('loan_currencies')->updateOrInsert(
                ['code' => $code],
                $updateData
            );
        }
    }

    private function seedLoanPaymentMethods(): void
    {
        $hasIsActive = Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'is_active');
        $hasUpdatedAt = Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'updated_at');
        $hasCreatedAt = Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'created_at');
        $now = now();

        foreach (['Cash', 'ABA', 'ACLEDA', 'Wing', 'Bank Transfer', 'QR', 'Credit Adjustment'] as $index => $name) {
            $updateData = [];
            if (Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'code')) {
                $updateData['code'] = strtolower(str_replace(' ', '_', $name));
            }
            if ($hasIsActive) {
                $updateData['is_active'] = 1;
            }
            if (Schema::connection('mysql_loan')->hasColumn('loan_payment_methods', 'sort_order')) {
                $updateData['sort_order'] = $index + 1;
            }
            if ($hasUpdatedAt) {
                $updateData['updated_at'] = $now;
            }
            if ($hasCreatedAt) {
                $updateData['created_at'] = $now;
            }

            DB::connection('mysql_loan')->table('loan_payment_methods')->updateOrInsert(
                ['name' => $name],
                $updateData
            );
        }
    }
}
