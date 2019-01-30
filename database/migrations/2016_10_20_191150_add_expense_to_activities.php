<?php

use Illuminate\Database\Migrations\Migration;

class AddExpenseToActivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function ($table) {
            $table->unsignedInteger('expense_id')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->date('financial_year_start')->nullable();
            $table->smallInteger('enabled_modules')->default(63);
            $table->smallInteger('enabled_dashboard_sections')->default(7);
            $table->boolean('show_accept_invoice_terms')->default(false);
            $table->boolean('show_accept_quote_terms')->default(false);
            $table->boolean('require_invoice_signature')->default(false);
            $table->boolean('require_quote_signature')->default(false);
        });

        Schema::table('payments', function ($table) {
            $table->text('credit_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function ($table) {
            $table->dropColumn('expense_id');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('financial_year_start');
            $table->dropColumn('enabled_modules');
            $table->dropColumn('enabled_dashboard_sections');
            $table->dropColumn('show_accept_invoice_terms');
            $table->dropColumn('show_accept_quote_terms');
            $table->dropColumn('require_invoice_signature');
            $table->dropColumn('require_quote_signature');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('credit_ids');
        });
    }
}
