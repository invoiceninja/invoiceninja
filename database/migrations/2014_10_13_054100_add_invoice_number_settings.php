<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceNumberSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('invoice_number_prefix')->nullable();
            $table->integer('invoice_number_counter')->default(1)->nullable();

            $table->string('quote_number_prefix')->nullable();
            $table->integer('quote_number_counter')->default(1)->nullable();

            $table->boolean('share_counter')->default(true);
        });

        // set initial counter value for accounts with invoices
    $accounts = DB::table('accounts')->lists('id');

        foreach ($accounts as $accountId) {
            $invoiceNumbers = DB::table('invoices')->where('account_id', $accountId)->lists('invoice_number');
            $max = 0;

            foreach ($invoiceNumbers as $invoiceNumber) {
                $number = intval(preg_replace('/[^0-9]/', '', $invoiceNumber));
                $max = max($max, $number);
            }

            DB::table('accounts')->where('id', $accountId)->update(['invoice_number_counter' => ++$max]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('invoice_number_prefix');
            $table->dropColumn('invoice_number_counter');

            $table->dropColumn('quote_number_prefix');
            $table->dropColumn('quote_number_counter');

            $table->dropColumn('share_counter');
        });
    }
}
