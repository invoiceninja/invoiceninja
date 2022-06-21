<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('company_gateways', function (Blueprint $table) {
            $table->renameColumn('show_billing_address', 'require_billing_address');
            $table->renameColumn('show_shipping_address', 'require_shipping_address');
            $table->boolean('require_client_name')->default(false);
            $table->boolean('require_zip')->default(false);
            $table->boolean('require_client_phone')->default(false);
            $table->boolean('require_contact_name')->default(false);
            $table->boolean('require_contact_email')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
