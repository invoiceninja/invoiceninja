<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSlackNotifications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function ($table) {
            $table->integer('task_id')->unsigned()->change();
            $table->integer('client_id')->unsigned()->nullable()->change();
        });

        DB::statement('UPDATE activities SET client_id = NULL WHERE client_id = 0');

        Schema::table('activities', function ($table) {
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        Schema::table('users', function ($table) {
            $table->string('slack_webhook_url')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->timestamp('accepted_terms_timestamp')->nullable();
            $table->string('accepted_terms_ip')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('auto_archive_invoice')->default(false)->nullable();
            $table->boolean('auto_archive_quote')->default(false)->nullable();
            $table->boolean('auto_email_invoice')->default(true)->nullable();
        });

        Schema::table('expenses', function ($table) {
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        Schema::table('companies', function ($table) {
            $table->dropForeign('companies_payment_id_foreign');
        });

        Schema::table('companies', function ($table) {
            $table->index('payment_id');
        });

        Schema::table('user_accounts', function ($table) {
            $table->dropForeign('user_accounts_user_id1_foreign');
            $table->dropForeign('user_accounts_user_id2_foreign');
            $table->dropForeign('user_accounts_user_id3_foreign');
            $table->dropForeign('user_accounts_user_id4_foreign');
            $table->dropForeign('user_accounts_user_id5_foreign');
        });

        Schema::table('user_accounts', function ($table) {
            $table->index('user_id1');
            $table->index('user_id2');
            $table->index('user_id3');
            $table->index('user_id4');
            $table->index('user_id5');
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
            $table->dropColumn('slack_webhook_url');
            $table->dropColumn('accepted_terms_version');
            $table->dropColumn('accepted_terms_timestamp');
            $table->dropColumn('accepted_terms_ip');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('auto_archive_invoice');
            $table->dropColumn('auto_archive_quote');
            $table->dropColumn('auto_email_invoice');
        });
    }
}
