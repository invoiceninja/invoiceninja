<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubdomainToLookups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookup_accounts', function ($table) {
            $table->string('subdomain')->nullable()->unique();
        });

        Schema::table('payments', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1);
            $table->unsignedInteger('exchange_currency_id')->nullable(false);
        });

        Schema::table('expenses', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1)->change();
        });

        Schema::table('clients', function ($table) {
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->unsignedInteger('shipping_country_id')->nullable();
        });

        Schema::table('clients', function ($table) {
            $table->foreign('shipping_country_id')->references('id')->on('countries');
        });

        Schema::table('account_gateways', function ($table) {
            $table->boolean('show_shipping_address')->default(false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lookup_accounts', function ($table) {
            $table->dropColumn('subdomain');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('exchange_rate');
            $table->dropColumn('exchange_currency_id');
        });

        Schema::table('clients', function ($table) {
            $table->dropForeign('clients_shipping_country_id_foreign');
            $table->dropColumn('shipping_address1');
            $table->dropColumn('shipping_address2');
            $table->dropColumn('shipping_city');
            $table->dropColumn('shipping_state');
            $table->dropColumn('shipping_postal_code');
            $table->dropColumn('shipping_country_id');
        });

        Schema::table('account_gateways', function ($table) {
            $table->dropColumn('show_shipping_address');
        });
    }
}
