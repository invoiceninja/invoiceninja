<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        require_once app_path() . '/Constants.php';

        Schema::create('countries', function ($table) {
            $table->increments('id');
            $table->string('capital', 255)->nullable();
            $table->string('citizenship', 255)->nullable();
            $table->string('country_code', 3)->default('');
            $table->string('currency', 255)->nullable();
            $table->string('currency_code', 255)->nullable();
            $table->string('currency_sub_unit', 255)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('iso_3166_2', 2)->default('');
            $table->string('iso_3166_3', 3)->default('');
            $table->string('name', 255)->default('');
            $table->string('region_code', 3)->default('');
            $table->string('sub_region_code', 3)->default('');
            $table->boolean('eea')->default(0);
            $table->boolean('swap_postal_code')->default(0);
            $table->boolean('swap_currency_symbol')->default(false);
            $table->string('thousand_separator')->nullable();
            $table->string('decimal_separator')->nullable();
        });

        Schema::create('payment_types', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('timezones', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('location');
            $table->integer('utc_offset');
        });

        Schema::create('currencies', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('symbol');
            $table->string('precision');
            $table->string('thousand_separator');
            $table->string('decimal_separator');
            $table->string('code');
            $table->boolean('swap_currency_symbol')->default(false);

        });

        Schema::create('sizes', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('industries', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('gateways', function ($table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name');
            $table->string('provider');
            $table->boolean('visible')->default(true);
        });

        
        Schema::create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('timezone_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('ip');
            $table->string('account_key')->unique();
            $table->timestamp('last_login')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('work_email')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->unsignedInteger('industry_id')->nullable();
            $table->unsignedInteger('size_id')->nullable();
            $table->string('subdomain')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('timezone_id')->references('id')->on('timezones');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
        });

        Schema::create('user_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->text('permissions');
            $table->boolean('is_owner');
            $table->boolean('is_admin');
            $table->boolean('is_locked'); // locks user out of account
            $table->boolean('is_default'); //default account to present to the user

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');


        });
        
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->boolean('registered')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->integer('theme_id')->nullable();
            $table->smallInteger('failed_logins')->nullable();
            $table->string('referral_code')->nullable();
            $table->string('oauth_user_id')->nullable()->unique();
            $table->unsignedInteger('oauth_provider_id')->nullable()->unique();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 255)->default('');
            $table->unsignedInteger('avatar_width')->nullable();
            $table->unsignedInteger('avatar_height')->nullable();
            $table->unsignedInteger('avatar_size')->nullable();
            $table->text('signature');
            $table->string('password');
            $table->rememberToken();
            
            $table->timestamps();
            $table->softDeletes();

            //$table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

        });

        Schema::create('clients', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('user_id')->index();

            $table->string('name')->nullable();
            $table->string('website')->nullable();
            $table->text('private_notes')->nullable();
            $table->decimal('balance', 13, 2)->nullable();
            $table->decimal('paid_to_date', 13, 2)->nullable();
            $table->timestamp('last_login')->nullable();
            $table->unsignedInteger('industry_id')->nullable();
            $table->unsignedInteger('size_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->string('payment_terms')->nullable();  //todo type? depends how we are storing this

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('currency_id')->references('id')->on('currencies');

        });

        Schema::create('client_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->index();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('description')->nullable();
            $table->text('private_notes')->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries');


        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('client_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique(); //todo handle one contact across many accounts ?
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->boolean('registered')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->smallInteger('failed_logins')->nullable();
            $table->string('oauth_user_id')->nullable()->unique();
            $table->unsignedInteger('oauth_provider_id')->nullable()->unique();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 255)->default('');
            $table->unsignedInteger('avatar_width')->nullable();
            $table->unsignedInteger('avatar_height')->nullable();
            $table->unsignedInteger('avatar_size')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

        });


        Schema::create('account_gateways', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('account_id')->unique();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('gateway_id');
            $table->boolean('show_address')->default(true)->nullable();
            $table->boolean('update_address')->default(true)->nullable();
            $table->text('config');

            $table->timestamps();
            $table->softDeletes();


            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('gateway_id')->references('id')->on('gateways');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });


        Schema::create('invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('invoice_status_id');

            $t->string('invoice_number');
            $t->float('discount');
            $t->boolean('is_amount_discount');

            $t->string('po_number');
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->text('line_items')->nullable();
            $t->text('options')->nullable();
            $t->text('backup')->nullable();

            $t->string('tax_name1');
            $t->decimal('tax_rate1', 13, 3);

            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);
            $t->decimal('partial', 13, 2)->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['account_id', 'client_id']);
        });

        Schema::create('invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('contact_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->string('invitation_key')->index()->unique();
            $t->timestamps();
            $t->softDeletes();

            $t->string('transaction_reference')->nullable();
            $t->timestamp('sent_date')->nullable();
            $t->timestamp('viewed_date')->nullable();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

        });


        Schema::create('tax_rates', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name')->unique();
            $t->decimal('rate', 13, 3);

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });


        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');


            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->unsignedInteger('stock_level');
            $t->unsignedInteger('min_stock_level');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');


            $t->timestamps();
            $t->softDeletes();
        });


        Schema::create('payments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->nullable()->index(); //todo handle payments where there is no invoice OR we are paying MULTIPLE invoices
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('account_gateway_id')->nullable();
            $t->unsignedInteger('payment_type_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->boolean('is_deleted')->default(false);
            $t->decimal('amount', 13, 2);
            $t->date('payment_date')->nullable();
            $t->string('transaction_reference')->nullable();
            $t->string('payer_id')->nullable();

            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('account_gateway_id')->references('id')->on('account_gateways')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;
            $t->foreign('payment_type_id')->references('id')->on('payment_types');

        });

        Schema::create('languages', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('locale');
        });

        Schema::table('accounts', function ($table) {
            $table->unsignedInteger('language_id')->default(1);
            $table->foreign('language_id')->references('id')->on('languages');
        });

        Schema::create('payment_libraries', function ($t) {
            $t->increments('id');
            $t->timestamps();

            $t->string('name');
            $t->boolean('visible')->default(true);
        });

        Schema::table('gateways', function ($table) {
            $table->unsignedInteger('payment_library_id')->default(1);
            $table->unsignedInteger('sort_order')->default(10000);
            $table->boolean('recommended')->default(0);
            $table->string('site_url', 200)->nullable();
            $table->boolean('is_offsite');
            $table->boolean('is_secure');
        });

        DB::table('gateways')->update(['payment_library_id' => 1]);

        Schema::table('gateways', function ($table) {
            $table->foreign('payment_library_id')->references('id')->on('payment_libraries')->onDelete('cascade');
        });

        Schema::table('accounts', function ($table) {
            $table->string('custom_label1')->nullable();
            $table->string('custom_value1')->nullable();

            $table->string('custom_label2')->nullable();
            $table->string('custom_value2')->nullable();

            $table->string('custom_client_label1')->nullable();
            $table->string('custom_client_label2')->nullable();
        });

        Schema::table('clients', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->string('vat_number')->nullable();
        });

        Schema::table('clients', function ($table) {
            $table->string('vat_number')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->string('id_number')->nullable();
        });

        Schema::table('clients', function ($table) {
            $table->string('id_number')->nullable();
        });

        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->string('description')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_running')->default(false);
            $table->text('time_log')->nullable();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

        });

        Schema::create('banks', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('remote_id');
            $table->integer('bank_library_id')->default(BANK_LIBRARY_OFX);
            $table->text('config');
        });

        Schema::create('bank_accounts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('bank_id');
            $table->unsignedInteger('user_id');
            $table->string('username');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_id')->references('id')->on('banks');

        });


        Schema::create('bank_subaccounts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bank_account_id');

            $table->string('account_name');
            $table->string('account_number');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::dropIfExists('bank_subaccounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('payment_libraries');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('products');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('user_accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
    }


}