<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'mysql_loan';
    protected string $table = 'loan_customers';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'main_contact_id', fn () => $table->unsignedBigInteger('main_contact_id')->nullable()->after('id'));
            $this->addColumnIfMissing($table, 'customer_code', fn () => $table->string('customer_code', 50)->nullable()->after('main_contact_id'));
            $this->addColumnIfMissing($table, 'business_location_id', fn () => $table->unsignedBigInteger('business_location_id')->nullable()->after('customer_code'));
            $this->addColumnIfMissing($table, 'business_location_name_snapshot', fn () => $table->string('business_location_name_snapshot')->nullable()->after('business_location_id'));
            $this->addColumnIfMissing($table, 'name', fn () => $table->string('name')->nullable()->after('business_location_name_snapshot'));
            $this->addColumnIfMissing($table, 'khmer_name', fn () => $table->string('khmer_name')->nullable()->after('name'));
            $this->addColumnIfMissing($table, 'alternate_phone', fn () => $table->string('alternate_phone', 50)->nullable()->after('phone'));
            $this->addColumnIfMissing($table, 'telegram', fn () => $table->string('telegram')->nullable()->after('alternate_phone'));
            $this->addColumnIfMissing($table, 'facebook', fn () => $table->string('facebook')->nullable()->after('telegram'));
            $this->addColumnIfMissing($table, 'passport_number', fn () => $table->string('passport_number')->nullable()->after('id_card_number'));
            $this->addColumnIfMissing($table, 'province', fn () => $table->string('province')->nullable()->after('address'));
            $this->addColumnIfMissing($table, 'district', fn () => $table->string('district')->nullable()->after('province'));
            $this->addColumnIfMissing($table, 'commune', fn () => $table->string('commune')->nullable()->after('district'));
            $this->addColumnIfMissing($table, 'village', fn () => $table->string('village')->nullable()->after('commune'));
            $this->addColumnIfMissing($table, 'latitude', fn () => $table->decimal('latitude', 10, 7)->nullable()->after('village'));
            $this->addColumnIfMissing($table, 'longitude', fn () => $table->decimal('longitude', 10, 7)->nullable()->after('latitude'));
            $this->addColumnIfMissing($table, 'family_contact_name', fn () => $table->string('family_contact_name')->nullable()->after('longitude'));
            $this->addColumnIfMissing($table, 'family_contact_phone', fn () => $table->string('family_contact_phone', 50)->nullable()->after('family_contact_name'));
            $this->addColumnIfMissing($table, 'spouse_name', fn () => $table->string('spouse_name')->nullable()->after('family_contact_phone'));
            $this->addColumnIfMissing($table, 'spouse_phone', fn () => $table->string('spouse_phone', 50)->nullable()->after('spouse_name'));
            $this->addColumnIfMissing($table, 'workplace', fn () => $table->string('workplace')->nullable()->after('spouse_phone'));
            $this->addColumnIfMissing($table, 'monthly_income', fn () => $table->decimal('monthly_income', 14, 2)->nullable()->after('workplace'));
            $this->addColumnIfMissing($table, 'customer_type', fn () => $table->string('customer_type', 100)->nullable()->after('monthly_income'));
            $this->addColumnIfMissing($table, 'customer_photo_file_id', fn () => $table->unsignedBigInteger('customer_photo_file_id')->nullable()->after('customer_type'));
            $this->addColumnIfMissing($table, 'id_front_file_id', fn () => $table->unsignedBigInteger('id_front_file_id')->nullable()->after('customer_photo_file_id'));
            $this->addColumnIfMissing($table, 'id_back_file_id', fn () => $table->unsignedBigInteger('id_back_file_id')->nullable()->after('id_front_file_id'));
            $this->addColumnIfMissing($table, 'blacklist_reason', fn () => $table->text('blacklist_reason')->nullable()->after('blacklist_status'));
            $this->addColumnIfMissing($table, 'created_by_name_snapshot', fn () => $table->string('created_by_name_snapshot')->nullable()->after('created_by'));
            $this->addColumnIfMissing($table, 'customer_group_name_snapshot', fn () => $table->string('customer_group_name_snapshot')->nullable()->after('customer_photo_file_id'));
            $this->addColumnIfMissing($table, 'business_name_snapshot', fn () => $table->string('business_name_snapshot')->nullable()->after('customer_group_name_snapshot'));
            $this->addColumnIfMissing($table, 'synced_at', fn () => $table->timestamp('synced_at')->nullable()->after('updated_at'));
            $this->addColumnIfMissing($table, 'deleted_at', fn () => $table->softDeletes());
        });

        if (Schema::connection($this->connection)->hasColumn($this->table, 'customer_code')) {
            try {
                Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                    $table->unique('customer_code', 'loan_customers_customer_code_unique');
                });
            } catch (\Throwable $e) {
                // Ignore if unique index already exists.
            }
        }
    }

    public function down(): void
    {
        // Keep migration non-destructive.
    }

    protected function addColumnIfMissing(Blueprint $table, string $name, callable $creator): void
    {
        if (! Schema::connection($this->connection)->hasColumn($this->table, $name)) {
            $creator();
        }
    }
};
