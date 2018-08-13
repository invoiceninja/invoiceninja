<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixPaymentContactForeignKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            Schema::table('payments', function ($table) {
                $table->dropForeign('payments_contact_id_foreign');
            });

            Schema::table('payments', function ($table) {
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            });

            Schema::table('licenses', function ($table) {
                $table->unsignedInteger('affiliate_id')->nullable()->change();
                $table->string('first_name')->nullable()->change();
                $table->string('last_name')->nullable()->change();
                $table->string('email')->nullable()->change();
                $table->string('license_key')->unique()->nullable()->change();
                $table->boolean('is_claimed')->nullable()->change();
                $table->string('transaction_reference')->nullable()->change();
            });
        } catch (Exception $exception) {
            // do nothing, change only needed for invoiceninja servers
        }
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
}
