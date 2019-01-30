<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOauthToLookups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookup_users', function ($table) {
            $table->string('oauth_user_key')->nullable()->unique();
            $table->string('referral_code')->nullable()->unique();
        });

        Schema::table('companies', function ($table) {
            $table->string('referral_code')->nullable();
        });

        DB::statement('update companies
            left join accounts on accounts.company_id = companies.id
            left join users on users.id = accounts.referral_user_id
            set companies.referral_code = users.referral_code
            where users.id is not null');

        Schema::table('accounts', function ($table) {
            if (Schema::hasColumn('accounts', 'referral_user_id')) {
                $table->dropColumn('referral_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lookup_users', function ($table) {
            $table->dropColumn('oauth_user_key');
            $table->dropColumn('referral_code');
        });

        Schema::table('companies', function ($table) {
            $table->dropColumn('referral_code');
        });
    }
}
