<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['business_id', 'type', 'deleted_at'], 'upos_contacts_biz_type_del');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['business_id', 'contact_id'], 'upos_txn_biz_contact');
            $table->index(['contact_id', 'type', 'status', 'transaction_date'], 'upos_txn_contact_type_status_date');
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->index(['transaction_id', 'is_return'], 'upos_txn_payments_txn_is_return');
        });
    }

    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('upos_contacts_biz_type_del');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('upos_txn_biz_contact');
            $table->dropIndex('upos_txn_contact_type_status_date');
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropIndex('upos_txn_payments_txn_is_return');
        });
    }
};
