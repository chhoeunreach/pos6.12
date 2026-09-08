<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\TelegramSettingsService;

class LoanManagementSystemDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusinessSettings();
        $this->seedTelegramSettings();
    }

    private function seedBusinessSettings(): void
    {
        if (BusinessSettingsService::hasSavedSettings()) {
            return;
        }

        BusinessSettingsService::save([
            'business_name' => 'KY Store',
            'system_name' => 'Loan Management',
            'system_subtitle' => 'Dedicated loan operation workspace',
            'theme_color' => '#6366f1',
            'home_headline' => 'Simple loan service for customers',
            'home_subtitle' => 'Register with KY Store and our team will contact you about your loan request.',
            'home_body' => 'Fast customer registration, clear payment schedules, Telegram updates, and easy support from our branch team.',
        ]);
    }

    private function seedTelegramSettings(): void
    {
        if (! Schema::connection('mysql_loan')->hasTable('loan_telegram_settings')) {
            return;
        }

        $exists = DB::connection('mysql_loan')->table('loan_telegram_settings')
            ->where('id', 1)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection('mysql_loan')->table('loan_telegram_settings')->insert([
            'id' => 1,
            'bot_token' => null,
            'bot_username' => null,
            'webhook_secret' => (string) TelegramSettingsService::webhookSecret(),
            'link_ttl_minutes' => (int) TelegramSettingsService::linkTtlMinutes(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}