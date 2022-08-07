<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vendor_contacts', function ($table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamp('last_login')->nullable();
            $table->smallInteger('failed_logins')->nullable();
            $table->string('oauth_user_id', 100)->nullable()->unique();
            $table->unsignedInteger('oauth_provider_id')->nullable()->unique();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 255)->nullable();
            $table->string('avatar_type', 255)->nullable();
            $table->string('avatar_size', 255)->nullable();
            $table->string('password');
            $table->string('token')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->string('contact_key')->nullable();
            $table->rememberToken();
            $table->index(['company_id', 'email', 'deleted_at']);
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
