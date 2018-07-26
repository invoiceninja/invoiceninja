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
