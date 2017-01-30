<?php

use Illuminate\Database\Migrations\Migration;

class AddDefaultQuoteTerms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->text('quote_terms')->nullable();
        });

        $accounts = DB::table('accounts')
                        ->orderBy('id')
                        ->get(['id', 'invoice_terms']);

        foreach ($accounts as $account) {
            DB::table('accounts')
                ->where('id', $account->id)
                ->update(['quote_terms' => $account->invoice_terms]);
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
            $table->dropColumn('quote_terms');
        });
    }
}
