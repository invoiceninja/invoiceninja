<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PaymentsChanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('payment_statuses');
        
        Schema::create('payment_statuses', function($table)
        {
            $table->increments('id');
            $table->string('name');
        });
        
        (new \PaymentStatusSeeder())->run();
        
        Schema::table('payments', function($table)
        {
            $table->decimal('refunded', 13, 2);
            $table->unsignedInteger('payment_status_id')->default(3);
            $table->foreign('payment_status_id')->references('id')->on('payment_statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function($table)
        {
            $table->dropColumn('refunded');
            $table->dropForeign('payments_payment_status_id_foreign');
            $table->dropColumn('payment_status_id');
        });
        
        Schema::dropIfExists('payment_statuses');
    }
}
