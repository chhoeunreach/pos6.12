<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_loan';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('loan_activity_logs')) {
            return;
        }

        Schema::connection($this->connection)->create('loan_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name_snapshot')->nullable();
            $table->string('action', 120)->index();
            $table->string('method', 10)->index();
            $table->string('route_name')->nullable()->index();
            $table->string('url', 500);
            $table->string('source', 80)->nullable()->index();
            $table->string('subject_type', 80)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedSmallInteger('response_status')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('request_payload_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('loan_activity_logs');
    }
};
