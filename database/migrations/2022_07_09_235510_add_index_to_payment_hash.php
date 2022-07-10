<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToPaymentHash extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_hashes', function (Blueprint $table) {
            $table->string('hash', 255)->index()->change();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['quote_id', 'company_id']);
            $table->index(['recurring_invoice_id', 'company_id']);
            $table->index(['purchase_order_id', 'company_id']);
            $table->index(['vendor_contact_id', 'company_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['company_id', 'user_id', 'assigned_user_id', 'updated_at'],'pro_co_us_up_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
