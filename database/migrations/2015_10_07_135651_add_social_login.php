<?php

use Illuminate\Database\Migrations\Migration;

class AddSocialLogin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('oauth_user_id')->nullable();
            $table->unsignedInteger('oauth_provider_id')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->string('custom_invoice_text_label1')->nullable();
            $table->string('custom_invoice_text_label2')->nullable();
        });

        Schema::table('invoices', function ($table) {
            $table->string('custom_text_value1')->nullable();
            $table->string('custom_text_value2')->nullable();
        });

        Schema::table('invitations', function ($table) {
            $table->timestamp('opened_date')->nullable();
            $table->string('message_id')->nullable();
            $table->text('email_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('oauth_user_id');
            $table->dropColumn('oauth_provider_id');
        });
        
        Schema::table('accounts', function ($table) {
            $table->dropColumn('custom_invoice_text_label1');
            $table->dropColumn('custom_invoice_text_label2');
        });

        Schema::table('invoices', function ($table) {
            $table->dropColumn('custom_text_value1');
            $table->dropColumn('custom_text_value2');
        });

        Schema::table('invitations', function ($table) {
            $table->dropColumn('opened_date');
            $table->dropColumn('message_id');
            $table->dropColumn('email_error');
        });
    }
}
