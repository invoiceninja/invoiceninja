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
            $table->unsignedInteger('payment_status_id')->default(PAYMENT_STATUS_COMPLETED);
            $table->foreign('payment_status_id')->references('id')->on('payment_statuses');

            $table->unsignedInteger('routing_number')->nullable();
            $table->smallInteger('last4')->unsigned()->nullable();
            $table->date('expiration')->nullable();
            $table->text('gateway_error')->nullable();
            $table->string('email')->nullable();
        });

        Schema::table('invoices', function($table)
        {
            $table->boolean('client_enable_auto_bill')->default(false);
        });

        \DB::table('invoices')
            ->where('auto_bill', '=', 1)
            ->update(array('client_enable_auto_bill' => 1, 'auto_bill' => AUTO_BILL_OPT_OUT));
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
            $table->dropColumn('gateway_error');
            $table->dropColumn('email');
        });

        \DB::table('invoices')
            ->where(function($query){
                $query->where('auto_bill', '=', AUTO_BILL_ALWAYS);
                $query->orwhere(function($query){
                    $query->where('auto_bill', '!=', AUTO_BILL_OFF);
                    $query->where('client_enable_auto_bill', '=', 1);
                });
            })
            ->update(array('auto_bill' => 1));

        \DB::table('invoices')
            ->where('auto_bill', '!=', 1)
            ->update(array('auto_bill' => 0));

        Schema::table('invoices', function ($table) {
            $table->dropColumn('client_enable_auto_bill');
        });
        
        Schema::dropIfExists('payment_statuses');
    }
}
