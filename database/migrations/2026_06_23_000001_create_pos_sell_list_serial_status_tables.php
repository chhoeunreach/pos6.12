<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pos_sell_list_serial_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hr_sell_out_report_id');
            $table->unsignedBigInteger('hr_sell_out_report_line_id')->unique();
            $table->string('serial_number')->index();
            $table->string('status')->default('active')->index();
            $table->string('invoice_key')->nullable()->index();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('transaction_sell_line_id')->nullable()->index();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_sell_list_serial_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serial_status_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('invoice_key')->nullable()->index();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('changed_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('serial_status_id', 'pos_serial_status_history_status_fk')
                ->references('id')
                ->on('pos_sell_list_serial_statuses')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pos_sell_list_serial_status_histories');
        Schema::dropIfExists('pos_sell_list_serial_statuses');
    }
};
