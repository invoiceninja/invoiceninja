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
            
            $table->unsignedInteger('routing_number')->nullable();
            $table->smallInteger('last4')->unsigned()->nullable();
            $table->date('expiration')->nullable();
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
            
            $table->dropColumn('routing_number');
            $table->dropColumn('last4');
            $table->dropColumn('expiration');
        });
        
        Schema::dropIfExists('payment_statuses');
    }
}
