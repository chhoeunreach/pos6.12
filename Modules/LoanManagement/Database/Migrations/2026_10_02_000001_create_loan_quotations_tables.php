<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('loan_quotations')) {
            Schema::connection($this->connection)->create('loan_quotations', function (Blueprint $table) {
                $table->id();
                $table->string('quotation_no', 100)->unique();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('customer_name_snapshot', 191)->nullable();
                $table->string('customer_phone_snapshot', 50)->nullable();
                $table->text('customer_address_snapshot')->nullable();
                $table->unsignedBigInteger('business_location_id')->nullable()->index();
                $table->string('location_name_snapshot', 191)->nullable();
                $table->date('quotation_date')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 50)->default('draft')->index(); // draft, sent, accepted, rejected, expired, converted, cancelled
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('down_payment', 14, 2)->default(0);
                $table->decimal('loan_amount', 14, 2)->default(0);
                $table->decimal('interest_rate', 8, 2)->default(0);
                $table->string('interest_type', 50)->default('flat_rate'); // flat_rate, declining_balance
                $table->integer('duration_months')->default(12);
                $table->string('payment_frequency', 50)->default('monthly'); // daily, weekly, monthly
                $table->date('first_due_date')->nullable();
                $table->decimal('installment_amount', 14, 2)->default(0);
                $table->decimal('total_interest', 14, 2)->default(0);
                $table->decimal('total_payable', 14, 2)->default(0);
                $table->text('terms')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('converted_loan_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('loan_quotation_items')) {
            Schema::connection($this->connection)->create('loan_quotation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quotation_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name_snapshot', 191);
                $table->string('sku_snapshot', 100)->nullable();
                $table->string('serial_number_snapshot', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('photo_path', 255)->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('quotation_id')->references('id')->on('loan_quotations')->onDelete('cascade');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('loan_quotation_schedules')) {
            Schema::connection($this->connection)->create('loan_quotation_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quotation_id')->index();
                $table->integer('installment_no');
                $table->date('due_date');
                $table->decimal('principal_amount', 14, 2)->default(0);
                $table->decimal('interest_amount', 14, 2)->default(0);
                $table->decimal('schedule_amount', 14, 2)->default(0);
                $table->decimal('balance_amount', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('quotation_id')->references('id')->on('loan_quotations')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('loan_quotation_schedules');
        Schema::connection($this->connection)->dropIfExists('loan_quotation_items');
        Schema::connection($this->connection)->dropIfExists('loan_quotations');
    }
};
