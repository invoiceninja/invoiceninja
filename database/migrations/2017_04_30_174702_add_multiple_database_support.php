<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMultipleDatabaseSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookup_companies', function ($table) {
            $table->unsignedInteger('company_id')->index();
        });

        Schema::table('lookup_companies', function ($table) {
            $table->unique(['db_server_id', 'company_id']);
        });

        Schema::table('lookup_accounts', function ($table) {
            $table->string('account_key')->change()->unique();
        });

        Schema::table('lookup_users', function ($table) {
            $table->string('email')->change()->nullable()->unique();
            $table->string('confirmation_code')->nullable()->unique();
            $table->unsignedInteger('user_id')->index();
        });

        Schema::table('lookup_users', function ($table) {
            $table->unique(['lookup_account_id', 'user_id']);
        });

        Schema::table('lookup_contacts', function ($table) {
            $table->string('contact_key')->change()->unique();
        });

        Schema::table('lookup_invitations', function ($table) {
            $table->string('invitation_key')->change()->unique();
            $table->string('message_id')->change()->nullable()->unique();
        });

        Schema::table('lookup_tokens', function ($table) {
            $table->string('token')->change()->unique();
        });

        Schema::rename('lookup_tokens', 'lookup_account_tokens');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lookup_companies', function ($table) {
            $table->dropColumn('company_id');
        });

        Schema::table('lookup_users', function ($table) {
            $table->dropColumn('confirmation_code');
        });

        Schema::rename('lookup_account_tokens', 'lookup_tokens');
    }
}
