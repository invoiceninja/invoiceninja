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

        Schema::create('languages', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('locale');
        });

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
            $table->integer('gateway_type_id');
            $table->timestamps();
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
            $table->timestamps();
        });

        Schema::create('gateways', function ($table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name');
            $table->string('provider');
            $table->boolean('visible')->default(true);
        });

        Schema::create('accounts', function ($table) {

            $table->increments('id');

            $table->enum('plan', ['pro', 'enterprise', 'white_label'])->nullable();
            $table->enum('plan_term', ['month', 'year'])->nullable();
            $table->date('plan_started')->nullable();
            $table->date('plan_paid')->nullable();
            $table->date('plan_expires')->nullable();

            $table->unsignedInteger('payment_id')->nullable()->index();
            $table->unsignedInteger('default_company_id');

            $table->date('trial_started')->nullable();
            $table->enum('trial_plan', ['pro', 'enterprise'])->nullable();

            $table->enum('pending_plan', ['pro', 'enterprise', 'free'])->nullable();
            $table->enum('pending_term', ['month', 'year'])->nullable();

            $table->decimal('plan_price', 7, 2)->nullable();
            $table->decimal('pending_plan_price', 7, 2)->nullable();
            $table->smallInteger('num_users')->default(1);
            $table->smallInteger('pending_num_users')->default(1);

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->float('discount');
            $table->date('discount_expires')->nullable();

            $table->enum('bluevine_status', ['ignored', 'signed_up'])->nullable();
            $table->string('referral_code')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('industry_id')->nullable();
            $table->string('ip');
            $table->string('company_key',100)->unique();
            $table->timestamp('last_login')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('work_email')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('subdomain')->nullable();
            $table->string('db')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();
            $table->unsignedInteger('size_id')->nullable();
            $table->text('settings');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');


        });


        Schema::create('company_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id')->index();
            $table->text('permissions');
            $table->text('settings');
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_admin');
            $table->boolean('is_locked')->default(false); // locks user out of account
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $table->index(['account_id', 'company_id']);

        });


        
        Schema::create('users', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email',100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->integer('theme_id')->nullable();
            $table->smallInteger('failed_logins')->nullable();
            $table->string('referral_code')->nullable();
            $table->string('oauth_user_id',100)->nullable()->unique();
            $table->unsignedInteger('oauth_provider_id')->nullable()->unique();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 100)->default('');
            $table->unsignedInteger('avatar_width')->nullable();
            $table->unsignedInteger('avatar_height')->nullable();
            $table->unsignedInteger('avatar_size')->nullable();
            $table->text('signature');
            $table->string('password');
            $table->rememberToken();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

        });


        Schema::create('company_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id')->index();
            $table->string('token')->nullable();
            $table->string('name')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
        
        Schema::create('clients', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
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

            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->unsignedInteger('shipping_country_id')->nullable();
            $table->text('settings');


            $table->boolean('is_deleted')->default(false);
            $table->string('payment_terms')->nullable();  //todo type? depends how we are storing this
            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('currency_id')->references('id')->on('currencies');

        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('client_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();
            $table->string('email',100);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('confirmed')->default(false);
            $table->smallInteger('failed_logins')->nullable();
            $table->string('oauth_user_id',100)->nullable()->unique();
            $table->unsignedInteger('oauth_provider_id')->nullable()->unique();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 255)->nullable();
            $table->unsignedInteger('avatar_width')->nullable();
            $table->unsignedInteger('avatar_height')->nullable();
            $table->unsignedInteger('avatar_size')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            //$table->unique(['company_id', 'email']);
        });


        Schema::create('account_gateways', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('company_id')->unique();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('gateway_id');
            $table->boolean('show_address')->default(true)->nullable();
            $table->boolean('update_address')->default(true)->nullable();
            $table->text('config');

            $table->timestamps();
            $table->softDeletes();


            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('gateway_id')->references('id')->on('gateways');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });


        Schema::create('invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('invoice_status_id');

            $t->string('invoice_number');
            $t->float('discount');
            $t->boolean('is_amount_discount');

            $t->string('po_number');
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->text('line_items')->nullable();
            $t->text('settings')->nullable();
            $t->text('backup')->nullable();

            $t->string('tax_name1');
            $t->decimal('tax_rate1', 13, 3);

            $t->string('tax_name2');
            $t->decimal('tax_rate2', 13, 3);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);
            $t->decimal('partial', 13, 2)->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['company_id', 'invoice_number']);
        });

        Schema::create('invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('inviteable_id');
            $t->string('inviteable_type');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->string('invitation_key',100)->index()->unique();
            $t->timestamps();
            $t->softDeletes();

            $t->string('transaction_reference')->nullable();
            $t->timestamp('sent_date')->nullable();
            $t->timestamp('viewed_date')->nullable();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });


        Schema::create('tax_rates', function ($t) {

            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name',100)->unique();
            $t->decimal('rate', 13, 3);

            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });


        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id');

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3);
            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3);

            $t->boolean('is_deleted')->default(false);

            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');


            $t->timestamps();
            $t->softDeletes();
        });


        Schema::create('payments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->nullable()->index(); //todo handle payments where there is no invoice OR we are paying MULTIPLE invoices
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('client_contact_id')->nullable();
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
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade');
            $t->foreign('account_gateway_id')->references('id')->on('account_gateways')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;
            $t->foreign('payment_type_id')->references('id')->on('payment_types');

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

        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();

            $table->string('description')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_running')->default(false);
            $table->text('time_log')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
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

        Schema::create('bank_companies', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('bank_id');
            $table->unsignedInteger('user_id');
            $table->string('username');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_id')->references('id')->on('banks');

        });


        Schema::create('bank_subcompanies', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bank_company_id');

            $table->string('account_name');
            $table->string('account_number');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_company_id')->references('id')->on('bank_companies')->onDelete('cascade');

        });

        Schema::create('payment_terms', function ($table) {
            $table->increments('id');
            $table->integer('num_days');
            $table->string('name');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });


        Schema::create('activities', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('activity_type_id');
            
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {


    }


}