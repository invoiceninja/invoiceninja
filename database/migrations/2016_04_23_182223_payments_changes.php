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
        Schema::dropIfExists('card_types');
        
        Schema::create('payment_statuses', function($table)
        {
            $table->increments('id');
            $table->string('name');
        });
        
        Schema::create('card_types', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('code');
        });
        
        (new \PaymentStatusSeeder())->run();
        (new \CardTypesSeeder())->run();
        
        Schema::table('payments', function($table)
        {
            $table->decimal('refunded', 13, 2);
            $table->unsignedInteger('payment_status_id')->default(3);
            $table->foreign('payment_status_id')->references('id')->on('payment_statuses');
            
            $table->unsignedInteger('routing_number')->nullable();
            $table->smallInteger('last4')->unsigned()->nullable();
            $table->date('expiration')->nullable();
            $table->unsignedInteger('card_type_id')->nullable();
            $table->foreign('card_type_id')->references('id')->on('card_types');
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
            $table->dropForeign('card_type_id_foreign');
            $table->dropColumn('card_type_id');
        });
        
        Schema::dropIfExists('payment_statuses');
        Schema::dropIfExists('card_types');
    }
}
