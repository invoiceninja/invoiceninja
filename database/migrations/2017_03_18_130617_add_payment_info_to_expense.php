<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPaymentInfoToExpense extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expenses', function($table)
        {
            $table->unsignedInteger('payment_type_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->foreign('payment_type_id')->references('id')->on('payment_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('expenses', function($table)
        {
            $table->dropColumn('payment_type_id');
            $table->dropColumn('payment_date');
            $table->dropColumn('transaction_reference');
        });
    }
}
