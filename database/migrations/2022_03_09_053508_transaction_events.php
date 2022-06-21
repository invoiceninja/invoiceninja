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
        Schema::create('transaction_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->index();
            $table->unsignedInteger('invoice_id');
            $table->unsignedInteger('payment_id');
            $table->unsignedInteger('credit_id');
            $table->decimal('client_balance', 16, 4)->default(0);
            $table->decimal('client_paid_to_date', 16, 4)->default(0);
            $table->decimal('client_credit_balance', 16, 4)->default(0);
            $table->decimal('invoice_balance', 16, 4)->default(0);
            $table->decimal('invoice_amount', 16, 4)->default(0);
            $table->decimal('invoice_partial', 16, 4)->default(0);
            $table->decimal('invoice_paid_to_date', 16, 4)->default(0);
            $table->unsignedInteger('invoice_status')->nullable();
            $table->decimal('payment_amount', 16, 4)->default(0);
            $table->decimal('payment_applied', 16, 4)->default(0);
            $table->decimal('payment_refunded', 16, 4)->default(0);
            $table->unsignedInteger('payment_status')->nullable();
            $table->mediumText('paymentables')->nullable();
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('timestamp');
            $table->mediumText('payment_request')->nullable();
            $table->mediumText('metadata')->nullable();
            $table->decimal('credit_balance', 16, 4)->default(0);
            $table->decimal('credit_amount', 16, 4)->default(0);
            $table->unsignedInteger('credit_status')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
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
