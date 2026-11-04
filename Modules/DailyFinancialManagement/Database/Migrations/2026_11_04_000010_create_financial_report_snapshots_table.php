<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_report_snapshots')) {
            Schema::create('financial_report_snapshots', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('business_location_id')->nullable()->index();
                $table->string('report_type', 50)->index();
                $table->date('period_start')->index();
                $table->date('period_end')->index();
                $table->json('metrics_json');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'report_type', 'period_start', 'period_end'], 'idx_fin_snapshot_query');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_report_snapshots');
    }
};

