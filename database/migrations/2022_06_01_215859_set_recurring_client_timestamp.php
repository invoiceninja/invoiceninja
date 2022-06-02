<?php

use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SetRecurringClientTimestamp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->datetime('next_send_date_client')->nullable();
        });

        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->datetime('next_send_date_client')->nullable();
        });


        RecurringInvoice::whereNotNull('next_send_date')->cursor()->each(function ($recurring_invoice){
            $recurring_invoice->next_send_date_client = $recurring_invoice->next_send_date;
            $recurring_invoice->saveQuietly();
        });
    
        RecurringExpense::whereNotNull('next_send_date')->cursor()->each(function ($recurring_expense){
            $recurring_expense->next_send_date_client = $recurring_expense->next_send_date;
            $recurring_expense->saveQuietly();
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
}
