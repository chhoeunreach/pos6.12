<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        $this->createLoanBusinessLocations();
        $this->createLoanProducts();
        $this->createLoanProductItems();
        $this->createLoanCustomers();
        $this->createLoans();
        $this->createLoanItems();
        $this->createLoanPaymentSchedules();
        $this->createLoanPayments();
        $this->createLoanPaymentDetails();
        $this->createLoanPenalties();
        $this->createLoanStatusLogs();
        $this->createLoanCollectionVisits();
        $this->createLoanStaffLocations();
        $this->createLoanStaffLocationLatest();
        $this->createLoanFiles();
        $this->createLoanTelegramNotifications();
        $this->createLoanAbaPaywayTransactions();
        $this->createLoanIdCardScans();
        $this->createLoanImportBatches();
        $this->createLoanImportRows();
        $this->createLoanMonthlyArchives();
        $this->createLoanSyncLogs();
    }

    public function down(): void
    {
        $tables = [
            'loan_sync_logs',
            'loan_monthly_archives',
            'loan_import_rows',
            'loan_import_batches',
            'loan_id_card_scans',
            'loan_aba_payway_transactions',
            'loan_telegram_notifications',
            'loan_files',
            'loan_staff_location_latest',
            'loan_staff_locations',
            'loan_collection_visits',
            'loan_status_logs',
            'loan_penalties',
            'loan_payment_details',
            'loan_payments',
            'loan_payment_schedules',
            'loan_items',
            'loans',
            'loan_customers',
            'loan_product_items',
            'loan_products',
            'loan_business_locations',
        ];

        foreach ($tables as $table) {
            Schema::connection($this->connection)->dropIfExists($table);
        }
    }

    protected function createLoanBusinessLocations(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_business_locations')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_business_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('main_location_id')->nullable()->index();
            $table->string('name');
            $table->string('location_code')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanProducts(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_products')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('main_product_id')->nullable()->index();
            $table->unsignedBigInteger('main_variation_id')->nullable()->index();
            $table->unsignedBigInteger('main_variation_location_id')->nullable()->index();
            $table->unsignedBigInteger('loan_business_location_id')->nullable()->index();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->string('imei')->nullable()->index();
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->integer('qty_available')->default(0);
            $table->text('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanProductItems(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_product_items')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_product_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_product_id')->index();
            $table->string('serial_no')->nullable()->index();
            $table->string('imei')->nullable()->index();
            $table->string('status', 30)->default('available')->index();
            $table->unsignedBigInteger('current_loan_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanCustomers(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_customers')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('main_contact_id')->nullable()->index();
            $table->string('customer_code')->unique();
            $table->unsignedBigInteger('business_location_id')->nullable()->index();
            $table->string('business_location_name_snapshot')->nullable();
            $table->string('name');
            $table->string('khmer_name')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('login_phone')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('alternate_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('telegram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('gender', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('id_card_number')->nullable();
            $table->string('passport_number')->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('commune')->nullable();
            $table->string('village')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('family_contact_name')->nullable();
            $table->string('family_contact_phone')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_phone')->nullable();
            $table->string('workplace')->nullable();
            $table->decimal('monthly_income', 18, 2)->nullable();
            $table->string('customer_type')->nullable();
            $table->unsignedBigInteger('customer_photo_file_id')->nullable();
            $table->unsignedBigInteger('id_front_file_id')->nullable();
            $table->unsignedBigInteger('id_back_file_id')->nullable();
            $table->boolean('blacklist_status')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->timestamp('blacklist_date')->nullable();
            $table->unsignedBigInteger('blacklist_by')->nullable();
            $table->text('note')->nullable();
            $table->boolean('can_login')->default(false)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->boolean('allow_gps_tracking')->default(false)->index();
            $table->timestamp('gps_tracking_started_at')->nullable();
            $table->timestamp('gps_tracking_stopped_at')->nullable();
            $table->text('gps_tracking_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoans(): void
    {
        if (Schema::connection($this->connection)->hasTable('loans')) {
            return;
        }

        Schema::connection($this->connection)->create('loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('loan_number')->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('business_location_id')->nullable()->index();
            $table->string('business_location_name_snapshot')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('staff_name_snapshot')->nullable();
            $table->unsignedBigInteger('collector_id')->nullable()->index();
            $table->string('collector_name_snapshot')->nullable();
            $table->string('source_type', 30)->default('manual')->index();
            $table->unsignedBigInteger('source_transaction_id')->nullable()->index();
            $table->string('source_invoice_no')->nullable()->index();
            $table->timestamp('source_created_at')->nullable();
            $table->boolean('stock_already_deducted')->default(false);
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->string('invoice_number_snapshot')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('imei_snapshot')->nullable();
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->decimal('down_payment', 18, 2)->default(0);
            $table->integer('installment_count')->default(0);
            $table->string('payment_frequency', 30)->default('monthly');
            $table->date('loan_date')->nullable();
            $table->date('first_due_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('note')->nullable();
            $table->text('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanItems(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_items')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('loan_product_id')->nullable()->index();
            $table->unsignedBigInteger('loan_product_item_id')->nullable()->index();
            $table->string('product_name_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->string('imei_snapshot')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanPaymentSchedules(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_payment_schedules')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_payment_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->index();
            $table->integer('installment_no');
            $table->date('due_date')->index();
            $table->decimal('principal_due', 18, 2)->default(0);
            $table->decimal('interest_due', 18, 2)->default(0);
            $table->decimal('penalty_due', 18, 2)->default(0);
            $table->decimal('amount_due', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('amount_balance', 18, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanPayments(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_payments')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_ref_no')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->string('received_by_name_snapshot')->nullable();
            $table->string('channel', 30)->default('cash')->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('status', 20)->default('confirmed')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanPaymentDetails(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_payment_details')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_payment_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_id')->index();
            $table->string('method', 30)->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('transaction_no')->nullable()->index();
            $table->text('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanPenalties(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_penalties')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_penalties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanStatusLogs(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_status_logs')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_status_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->string('changed_by_name_snapshot')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanCollectionVisits(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_collection_visits')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_collection_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('collector_id')->nullable()->index();
            $table->string('collector_name_snapshot')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_snapshot')->nullable();
            $table->timestamp('visited_at')->nullable()->index();
            $table->string('result', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanStaffLocations(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_staff_locations')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_staff_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('staff_id')->index();
            $table->string('staff_name_snapshot')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->decimal('speed', 10, 2)->nullable();
            $table->decimal('heading', 10, 2)->nullable();
            $table->integer('battery_level')->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanStaffLocationLatest(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_staff_location_latest')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_staff_location_latest', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('staff_id')->unique();
            $table->string('staff_name_snapshot')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->decimal('speed', 10, 2)->nullable();
            $table->decimal('heading', 10, 2)->nullable();
            $table->integer('battery_level')->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamps();
        });
    }

    protected function createLoanFiles(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_files')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id')->index();
            $table->string('category', 50)->nullable()->index();
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanTelegramNotifications(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_telegram_notifications')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_telegram_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('event_code', 80)->index();
            $table->string('chat_id')->nullable()->index();
            $table->text('message');
            $table->string('status', 20)->default('pending')->index();
            $table->text('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanAbaPaywayTransactions(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_aba_payway_transactions')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_aba_payway_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('merchant_ref_no')->index();
            $table->string('aba_transaction_id')->nullable()->index();
            $table->string('payment_option')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('status', 20)->default('pending')->index();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanIdCardScans(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_id_card_scans')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_id_card_scans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('loan_file_id')->nullable()->index();
            $table->string('side', 20)->nullable();
            $table->text('ocr_raw_text')->nullable();
            $table->text('ocr_structured_json')->nullable();
            $table->string('provider')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanImportBatches(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_import_batches')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_import_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_code')->unique();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 20)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('status', 20)->default('uploaded')->index();
            $table->text('column_mapping_json')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanImportRows(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_import_rows')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_import_rows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedInteger('row_no')->index();
            $table->text('raw_row_json')->nullable();
            $table->text('normalized_json')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanMonthlyArchives(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_monthly_archives')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_monthly_archives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('archive_month', 7)->index();
            $table->string('archive_type', 50)->index();
            $table->string('source_table')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->text('payload_json')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createLoanSyncLogs(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_sync_logs')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source', 50)->index();
            $table->string('sync_type', 50)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
