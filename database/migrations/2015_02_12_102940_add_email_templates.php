<?php

use Illuminate\Database\Migrations\Migration;

class AddEmailTemplates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->text('email_template_invoice')->nullable();
            $table->text('email_template_quote')->nullable();
            $table->text('email_template_payment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('accounts', 'email_template_invoice')) {
            Schema::table('accounts', function ($table) {
                $table->dropColumn('email_template_invoice');
                $table->dropColumn('email_template_quote');
                $table->dropColumn('email_template_payment');
            });
        }
    }
}
