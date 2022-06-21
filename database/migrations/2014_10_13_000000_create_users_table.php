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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::raw('SET GLOBAL innodb_file_per_table=1;');
        DB::raw('SET GLOBAL innodb_file_format=Barracuda;');

        Schema::create('languages', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('locale');
        });

        Schema::create('countries', function ($table) {
            $table->increments('id');
            $table->string('capital', 255)->nullable();
            $table->string('citizenship', 255)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('currency', 255)->nullable();
            $table->string('currency_code', 255)->nullable();
            $table->string('currency_sub_unit', 255)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('iso_3166_2', 2)->nullable();
            $table->string('iso_3166_3', 3)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('region_code', 3)->nullable();
            $table->string('sub_region_code', 3)->nullable();
            $table->boolean('eea')->default(0);
            $table->boolean('swap_postal_code')->default(0);
            $table->boolean('swap_currency_symbol')->default(false);
            $table->string('thousand_separator')->nullable()->default('');
            $table->string('decimal_separator')->nullable()->default('');
        });

        Schema::create('payment_types', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('gateway_type_id')->nullable();
        });

        Schema::create('timezones', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('location');
            $table->integer('utc_offset')->default(0);
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
            $table->decimal('exchange_rate', 13, 6)->default(1);
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
            $table->string('name');
            $table->string('key')->unique();
            $table->string('provider');
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort_order')->default(10000);
            //$table->boolean('recommended')->default(0);
            $table->string('site_url', 200)->nullable();
            $table->boolean('is_offsite')->default(false);
            $table->boolean('is_secure')->default(false);
            $table->mediumText('fields')->nullable();
            $table->unsignedInteger('default_gateway_type_id')->default(1);
            $table->timestamps(6);
        });

        Schema::create('accounts', function ($table) {
            $table->increments('id');

            $table->enum('plan', ['pro', 'enterprise', 'white_label'])->nullable();
            $table->enum('plan_term', ['month', 'year'])->nullable();
            $table->date('plan_started')->nullable();
            $table->date('plan_paid')->nullable();
            $table->date('plan_expires')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('key')->nullable();

            $table->unsignedInteger('payment_id')->nullable()->index();
            $table->unsignedInteger('default_company_id');

            $table->date('trial_started')->nullable();
            $table->enum('trial_plan', ['pro', 'enterprise'])->nullable();

            $table->decimal('plan_price', 7, 2)->nullable();
            $table->smallInteger('num_users')->default(1);

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('latest_version')->default('0.0.0');
            $table->boolean('report_errors')->default(false);

            $table->string('referral_code')->nullable();

            $table->timestamps(6);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            //$table->string('name')->nullable();
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('industry_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('company_key', 100)->unique();
            $table->string('logo')->nullable();
            $table->boolean('convert_products')->default(false);
            $table->boolean('fill_products')->default(true);
            $table->boolean('update_products')->default(true);
            $table->boolean('show_product_details')->default(true);
            $table->boolean('client_can_register')->default(false);

            $table->boolean('custom_surcharge_taxes1')->default(false);
            $table->boolean('custom_surcharge_taxes2')->default(false);
            $table->boolean('custom_surcharge_taxes3')->default(false);
            $table->boolean('custom_surcharge_taxes4')->default(false);
            //$table->boolean('enable_invoice_quantity')->default(true);
            $table->boolean('show_product_cost')->default(false);
            $table->unsignedInteger('enabled_tax_rates')->default(0);
            $table->unsignedInteger('enabled_modules')->default(0);

            $table->boolean('enable_product_cost')->default(0);
            $table->boolean('enable_product_quantity')->default(1);
            $table->boolean('default_quantity')->default(1);

            $table->string('subdomain')->nullable();
            $table->string('db')->nullable();
            $table->unsignedInteger('size_id')->nullable();
            $table->string('first_day_of_week')->nullable();
            $table->string('first_month_of_year')->nullable();
            $table->string('portal_mode')->default('subdomain');
            $table->string('portal_domain')->nullable();

            $table->smallInteger('enable_modules')->default(0);
            $table->mediumText('custom_fields');
            $table->mediumText('settings');

            $table->string('slack_webhook_url');
            $table->string('google_analytics_url');

            $table->timestamps(6);
            //$table->softDeletes('deleted_at', 6);

            //$table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
        });

        //DB::statement('ALTER table companies key_block_size=8 row_format=compressed');

        Schema::create('company_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id')->index();
            $table->mediumText('permissions')->nullable();
            $table->mediumText('notifications')->nullable();
            $table->mediumText('settings')->nullable();
            $table->string('slack_webhook_url');
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_migrating')->default(false);
            $table->boolean('is_locked')->default(false); // locks user out of account

            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            //  $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['company_id', 'user_id']);
            $table->index(['account_id', 'company_id', 'deleted_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->string('url')->nullable();
            $table->string('preview')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('disk')->nullable();
            $table->string('hash', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('is_default')->default(0);
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();
            $table->softDeletes('deleted_at', 6);

            $table->unsignedInteger('documentable_id');
            $table->string('documentable_type');
            $table->timestamps(6);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('ip')->nullable();
            $table->string('device_token')->nullable();
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->integer('theme_id')->nullable();
            $table->smallInteger('failed_logins')->nullable();
            $table->string('referral_code')->nullable();
            $table->string('oauth_user_id', 100)->nullable();
            $table->string('oauth_user_token')->nullable();
            $table->string('oauth_provider_id')->nullable();
            $table->string('google_2fa_secret')->nullable();
            $table->string('accepted_terms_version')->nullable();
            $table->string('avatar', 100)->nullable();
            $table->unsignedInteger('avatar_width')->nullable();
            $table->unsignedInteger('avatar_height')->nullable();
            $table->unsignedInteger('avatar_size')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->datetime('last_login')->nullable();
            $table->mediumText('signature')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['oauth_user_id', 'oauth_provider_id']);

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('company_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->string('token')->nullable();
            $table->string('name')->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('assigned_user_id')->nullable();

            $table->string('name')->nullable();
            $table->string('website')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('client_hash')->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('phone', 255)->nullable();

            $table->decimal('balance', 16, 4)->default(0);
            $table->decimal('paid_to_date', 16, 4)->default(0);
            $table->decimal('credit_balance', 16, 4)->default(0);
            $table->timestamp('last_login')->nullable();
            $table->unsignedInteger('industry_id')->nullable();
            $table->unsignedInteger('size_id')->nullable();
            //  $table->unsignedInteger('currency_id')->nullable();

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
            $table->mediumText('settings')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('group_settings_id')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('industry_id')->references('id')->on('industries');
            $table->foreign('size_id')->references('id')->on('sizes');
            //  $table->foreign('currency_id')->references('id')->on('currencies');
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
            $table->string('email', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->boolean('is_primary')->default(false);
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
            $table->boolean('send_email')->default(true);
            $table->string('contact_key')->nullable();
            $table->rememberToken();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['company_id', 'deleted_at']);
            $table->index(['company_id', 'email', 'deleted_at']);

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            //$table->unique(['company_id', 'email']);
        });

        Schema::create('company_gateways', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->string('gateway_key');
            $table->unsignedInteger('accepted_credit_cards');
            $table->boolean('require_cvv')->default(true);
            $table->boolean('show_billing_address')->default(true)->nullable();
            $table->boolean('show_shipping_address')->default(true)->nullable();
            $table->boolean('update_details')->default(false)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->mediumText('config');
            $table->text('fees_and_limits');
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('gateway_key')->references('key')->on('gateways');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('status_id');
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->unsignedInteger('recurring_id')->nullable();
            $t->unsignedInteger('design_id')->nullable();

            $t->string('number')->nullable();
            $t->float('discount')->default(0);
            $t->boolean('is_amount_discount')->default(0);

            $t->string('po_number')->nullable();
            $t->date('date')->nullable();
            $t->date('last_sent_date')->nullable();

            $t->datetime('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->mediumText('line_items')->nullable();
            $t->mediumText('backup')->nullable();
            $t->text('footer')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('terms')->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);

            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);

            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);

            $t->decimal('total_taxes', 13, 3)->default(0);

            $t->boolean('uses_inclusive_taxes')->default(0);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();
            $t->datetime('next_send_date')->nullable();

            $t->string('custom_surcharge1')->nullable();
            $t->string('custom_surcharge2')->nullable();
            $t->string('custom_surcharge3')->nullable();
            $t->string('custom_surcharge4')->nullable();
            $t->boolean('custom_surcharge_tax1')->default(false);
            $t->boolean('custom_surcharge_tax2')->default(false);
            $t->boolean('custom_surcharge_tax3')->default(false);
            $t->boolean('custom_surcharge_tax4')->default(false);

            $t->decimal('exchange_rate', 13, 6)->default(1);
            $t->decimal('amount', 16, 4);
            $t->decimal('balance', 16, 4);
            $t->decimal('partial', 16, 4)->nullable();
            $t->datetime('partial_due_date')->nullable();

            $t->datetime('last_viewed')->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->index(['company_id', 'deleted_at']);

            $t->unique(['company_id', 'number']);
        });

        Schema::create('credits', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('status_id');
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->unsignedInteger('recurring_id')->nullable();
            $t->unsignedInteger('design_id')->nullable();
            $t->unsignedInteger('invoice_id')->nullable();

            $t->string('number')->nullable();
            $t->float('discount')->default(0);
            $t->boolean('is_amount_discount')->default(0);

            $t->string('po_number')->nullable();
            $t->date('date')->nullable();
            $t->datetime('last_sent_date')->nullable();

            $t->date('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);
            $t->mediumText('line_items')->nullable();
            $t->mediumText('backup')->nullable();
            $t->text('footer')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('terms')->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);

            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);

            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);

            $t->decimal('total_taxes', 13, 3)->default(0);

            $t->boolean('uses_inclusive_taxes')->default(0);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();
            $t->datetime('next_send_date')->nullable();

            $t->string('custom_surcharge1')->nullable();
            $t->string('custom_surcharge2')->nullable();
            $t->string('custom_surcharge3')->nullable();
            $t->string('custom_surcharge4')->nullable();
            $t->boolean('custom_surcharge_tax1')->default(false);
            $t->boolean('custom_surcharge_tax2')->default(false);
            $t->boolean('custom_surcharge_tax3')->default(false);
            $t->boolean('custom_surcharge_tax4')->default(false);

            $t->decimal('exchange_rate', 13, 6)->default(1);
            $t->decimal('amount', 16, 4);
            $t->decimal('balance', 16, 4);
            $t->decimal('partial', 16, 4)->nullable();
            $t->datetime('partial_due_date')->nullable();

            $t->datetime('last_viewed')->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->index(['company_id', 'deleted_at']);

            $t->unique(['company_id', 'number']);
        });

        Schema::create('credit_invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('credit_id')->index();
            $t->string('key')->index();
            $t->string('transaction_reference')->nullable();
            $t->string('message_id')->nullable()->index();
            $t->mediumText('email_error')->nullable();
            $t->text('signature_base64')->nullable();
            $t->datetime('signature_date')->nullable();

            $t->datetime('sent_date')->nullable();
            $t->datetime('viewed_date')->nullable();
            $t->datetime('opened_date')->nullable();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('credit_id')->references('id')->on('credits')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->index(['deleted_at', 'credit_id', 'company_id']);
            $t->unique(['client_contact_id', 'credit_id']);
        });

        Schema::create('recurring_invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();

            $t->unsignedInteger('status_id')->index();
            $t->string('number')->nullable();

            $t->float('discount')->default(0);
            $t->boolean('is_amount_discount')->default(false);

            $t->string('po_number')->nullable();
            $t->date('date')->nullable();
            $t->datetime('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->mediumText('line_items')->nullable();
            $t->mediumText('backup')->nullable();
            $t->text('footer')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('terms')->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);

            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);

            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);

            $t->decimal('total_taxes', 13, 3)->default(0);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->decimal('amount', 16, 4);
            $t->decimal('balance', 16, 4);
            $t->decimal('partial', 16, 4)->nullable();

            $t->datetime('last_viewed')->nullable();

            $t->unsignedInteger('frequency_id');
            $t->datetime('start_date')->nullable();
            $t->datetime('last_sent_date')->nullable();
            $t->datetime('next_send_date')->nullable();
            $t->unsignedInteger('remaining_cycles')->nullable();
            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->index(['company_id', 'deleted_at']);

            $t->unique(['company_id', 'number']);
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('recurring_quotes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->unsignedInteger('status_id')->index();

            $t->float('discount')->default(0);
            $t->boolean('is_amount_discount')->default(false);
            $t->string('number')->nullable();

            $t->string('po_number')->nullable();
            $t->date('date')->nullable();
            $t->datetime('due_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->mediumText('line_items')->nullable();
            $t->mediumText('backup')->nullable();

            $t->text('footer')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('terms')->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);

            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);

            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);

            $t->decimal('total_taxes', 13, 3)->default(0);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->decimal('amount', 16, 4)->default(0);
            $t->decimal('balance', 16, 4)->default(0);

            $t->datetime('last_viewed')->nullable();

            $t->unsignedInteger('frequency_id');
            $t->date('start_date')->nullable();
            $t->datetime('last_sent_date')->nullable();
            $t->datetime('next_send_date')->nullable();
            $t->unsignedInteger('remaining_cycles')->nullable();
            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->index(['company_id', 'deleted_at']);

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('quotes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('status_id');
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->unsignedInteger('recurring_id')->nullable();
            $t->unsignedInteger('design_id')->nullable();
            $t->unsignedInteger('invoice_id')->nullable();

            $t->string('number')->nullable();
            $t->float('discount')->default(0);
            $t->boolean('is_amount_discount')->default(0);

            $t->string('po_number')->nullable();
            $t->date('date')->nullable();
            $t->date('last_sent_date')->nullable();

            $t->datetime('due_date')->nullable();
            $t->datetime('next_send_date')->nullable();

            $t->boolean('is_deleted')->default(false);

            $t->mediumText('line_items')->nullable();
            $t->mediumText('backup')->nullable();

            $t->text('footer')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('terms')->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);

            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);

            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);

            $t->decimal('total_taxes', 13, 3)->default(0);

            $t->boolean('uses_inclusive_taxes')->default(0);

            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->string('custom_surcharge1')->nullable();
            $t->string('custom_surcharge2')->nullable();
            $t->string('custom_surcharge3')->nullable();
            $t->string('custom_surcharge4')->nullable();
            $t->boolean('custom_surcharge_tax1')->default(false);
            $t->boolean('custom_surcharge_tax2')->default(false);
            $t->boolean('custom_surcharge_tax3')->default(false);
            $t->boolean('custom_surcharge_tax4')->default(false);

            $t->decimal('exchange_rate', 13, 6)->default(1);
            $t->decimal('amount', 16, 4);
            $t->decimal('balance', 16, 4);
            $t->decimal('partial', 16, 4)->nullable();
            $t->datetime('partial_due_date')->nullable();

            $t->datetime('last_viewed')->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->index(['company_id', 'deleted_at']);

            $t->unique(['company_id', 'number']);
        });

        Schema::create('invoice_invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->string('key')->index();
            $t->string('transaction_reference')->nullable();
            $t->string('message_id')->nullable()->index();
            $t->mediumText('email_error')->nullable();
            $t->text('signature_base64')->nullable();
            $t->datetime('signature_date')->nullable();

            $t->datetime('sent_date')->nullable();
            $t->datetime('viewed_date')->nullable();
            $t->datetime('opened_date')->nullable();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->index(['deleted_at', 'invoice_id', 'company_id']);
            $t->unique(['client_contact_id', 'invoice_id']);
        });

        Schema::create('quote_invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('quote_id')->index();
            $t->string('key')->index();
            $t->string('transaction_reference')->nullable();
            $t->string('message_id')->nullable()->index();
            $t->mediumText('email_error')->nullable();
            $t->text('signature_base64')->nullable();
            $t->datetime('signature_date')->nullable();

            $t->datetime('sent_date')->nullable();
            $t->datetime('viewed_date')->nullable();
            $t->datetime('opened_date')->nullable();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->index(['deleted_at', 'quote_id', 'company_id']);
            $t->unique(['client_contact_id', 'quote_id']);
        });

        Schema::create('tax_rates', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id')->nullable();
            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->string('name', 100);
            $t->decimal('rate', 13, 3)->default(0);

            $t->index(['company_id', 'deleted_at']);

            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->string('custom_value1')->nullable();
            $t->string('custom_value2')->nullable();
            $t->string('custom_value3')->nullable();
            $t->string('custom_value4')->nullable();

            $t->string('product_key')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('cost', 16, 4)->default(0);
            $t->decimal('price', 16, 4)->default(0);
            $t->decimal('quantity', 16, 4)->default(0);

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->default(0);
            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 13, 3)->default(0);
            $t->string('tax_name3')->nullable();
            $t->decimal('tax_rate3', 13, 3)->default(0);
            $t->softDeletes('deleted_at', 6);
            $t->timestamps(6);

            $t->boolean('is_deleted')->default(false);

            $t->index(['company_id', 'deleted_at']);

            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('payments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('vendor_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('assigned_user_id')->nullable();
            $t->unsignedInteger('client_contact_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();
            $t->unsignedInteger('company_gateway_id')->nullable();
            $t->unsignedInteger('gateway_type_id')->nullable();
            $t->unsignedInteger('type_id')->nullable();
            $t->unsignedInteger('status_id')->index();
            $t->decimal('amount', 16, 4)->default(0);
            $t->decimal('refunded', 16, 4)->default(0);
            $t->decimal('applied', 16, 4)->default(0);
            $t->date('date')->nullable();
            $t->string('transaction_reference')->nullable();
            $t->string('payer_id')->nullable();
            $t->string('number')->nullable();
            $t->text('private_notes')->nullable();
            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);
            $t->boolean('is_deleted')->default(false);
            $t->boolean('is_manual')->default(false);
            $t->decimal('exchange_rate', 13, 6)->default(1);
            $t->unsignedInteger('currency_id');
            $t->unsignedInteger('exchange_currency_id');

            $t->index(['company_id', 'deleted_at']);
            $t->unique(['company_id', 'number']);
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_gateway_id')->references('id')->on('company_gateways')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('paymentables', function ($table) { //allows multiple invoices to one payment
            $table->increments('id');
            $table->unsignedInteger('payment_id');
            $table->unsignedInteger('paymentable_id');
            $table->decimal('amount', 16, 4)->default(0);
            $table->decimal('refunded', 16, 4)->default(0);
            $table->string('paymentable_type');
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('payment_libraries', function ($t) {
            $t->increments('id');
            $t->timestamps(6);

            $t->string('name')->nullable();
            $t->boolean('visible')->default(true);
        });

        Schema::create('banks', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('remote_id')->nullable();
            $table->integer('bank_library_id')->default(1);
            $table->text('config')->nullable();
        });

        Schema::create('bank_companies', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('bank_id');
            $table->unsignedInteger('user_id');
            $table->string('username')->nullable();

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('bank_id')->references('id')->on('banks');
        });

        Schema::create('bank_subcompanies', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bank_company_id');

            $table->string('account_name')->nullable();
            $table->string('website')->nullable();
            $table->string('account_number')->nullable();

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('bank_company_id')->references('id')->on('bank_companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('payment_terms', function ($table) {
            $table->increments('id');
            $table->integer('num_days')->nullable();
            $table->string('name')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('activities', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('client_contact_id')->nullable();
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('credit_id')->nullable();
            $table->unsignedInteger('invitation_id')->nullable();
            $table->unsignedInteger('task_id')->nullable();
            $table->unsignedInteger('expense_id')->nullable();
            $table->unsignedInteger('activity_type_id')->nullable();
            $table->string('ip');
            $table->boolean('is_system')->default(0);

            $table->text('notes');
            $table->timestamps(6);

            $table->index(['vendor_id', 'company_id']);
            $table->index(['project_id', 'company_id']);
            $table->index(['user_id', 'company_id']);
            $table->index(['client_id', 'company_id']);
            $table->index(['payment_id', 'company_id']);
            $table->index(['invoice_id', 'company_id']);
            $table->index(['credit_id', 'company_id']);
            $table->index(['invitation_id', 'company_id']);
            $table->index(['task_id', 'company_id']);
            $table->index(['expense_id', 'company_id']);
            $table->index(['client_contact_id', 'company_id']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            //$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('backups', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('activity_id');
            $table->mediumText('json_backup')->nullable();
            $table->longText('html_backup')->nullable();
            $table->timestamps(6);

            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('company_ledgers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('activity_id')->nullable();

            $table->decimal('adjustment', 16, 4)->nullable();
            $table->decimal('balance', 16, 4)->nullable(); //this is the clients balance carried forward
            $table->text('notes')->nullable();
            $table->text('hash')->nullable();

            $table->unsignedInteger('company_ledgerable_id');
            $table->string('company_ledgerable_type');
            $table->timestamps(6);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('gateway_types', function ($table) {
            $table->increments('id');
            $table->string('alias')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('client_gateway_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->text('token')->nullable();
            $table->text('routing_number')->nullable();
            $table->unsignedInteger('company_gateway_id');
            $table->string('gateway_customer_reference')->nullable();
            $table->unsignedInteger('gateway_type_id');
            $table->boolean('is_default')->default(0);
            $table->text('meta')->nullable();
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('group_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->mediumText('settings')->nullable();
            $table->boolean('is_default')->default(0);
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('date_formats', function ($table) {
            $table->increments('id');
            $table->string('format');
            $table->string('format_moment');
            $table->string('format_dart');
        });

        Schema::create('datetime_formats', function ($table) {
            $table->increments('id');
            $table->string('format');
            $table->string('format_moment');
            $table->string('format_dart');
        });

        Schema::create('system_logs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('event_id')->nullable();
            $table->unsignedInteger('type_id')->nullable();
            $table->mediumText('log');
            $table->timestamps(6);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps(6);
            $table->softDeletes();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('work_phone')->nullable();
            $table->text('private_notes')->nullable();
            $table->string('website')->nullable();
            $table->tinyInteger('is_deleted')->default(0);
            $table->string('vat_number')->nullable();
            $table->string('transaction_name')->nullable();
            $table->string('id_number')->nullable();

            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('vendor_id')->index();
            $table->timestamps(6);
            $table->softDeletes();

            $table->boolean('is_primary')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('expense_categories', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();
            $table->string('name')->nullable();
            $table->timestamps(6);
            $table->softDeletes();

            $table->index(['company_id', 'deleted_at']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps(6);
            $table->softDeletes();

            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
            $table->unsignedInteger('invoice_currency_id')->nullable(false);
            $table->unsignedInteger('expense_currency_id')->nullable(false);
            $table->unsignedInteger('invoice_category_id')->nullable();
            $table->unsignedInteger('payment_type_id')->nullable();
            $table->unsignedInteger('recurring_expense_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->decimal('amount', 13, 2);
            $table->decimal('foreign_amount', 13, 2);
            $table->decimal('exchange_rate', 13, 6)->default(1);
            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 13, 3)->default(0);
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3)->default(0);
            $table->string('tax_name3')->nullable();
            $table->decimal('tax_rate3', 13, 3)->default(0);
            $table->date('expense_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->text('private_notes');
            $table->text('public_notes');
            $table->text('transaction_reference');
            $table->boolean('should_be_invoiced')->default(false);
            $table->boolean('invoice_documents')->default();
            $table->string('transaction_id')->nullable();

            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->index(['company_id', 'deleted_at']);

            // Relations
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('projects', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('assigned_user_id');
            $t->unsignedInteger('company_id')->index();
            $t->unsignedInteger('client_id')->nullable();
            $t->string('name');
            $t->string('description');
            $t->decimal('task_rate', 12, 4)->default(0);
            $t->date('due_date')->nullable();
            $t->text('private_notes')->nullable();
            $t->float('budgeted_hours');
            $t->text('custom_value1')->nullable();
            $t->text('custom_value2')->nullable();
            $t->text('custom_value3')->nullable();
            $t->text('custom_value4')->nullable();
            $t->timestamps(6);
            $t->softDeletes();

            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');

            $t->index(['company_id', 'deleted_at']);

            $t->unique(['company_id', 'name']);
        });

        Schema::create('tasks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('task_status_id')->nullable();
            $table->smallInteger('task_status_sort_order')->nullable();
            $table->timestamps(6);
            $table->softDeletes();

            $table->text('custom_value1')->nullable();
            $table->text('custom_value2')->nullable();
            $table->text('custom_value3')->nullable();
            $table->text('custom_value4')->nullable();

            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_running')->default(false);
            $table->text('time_log')->nullable();

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('designs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->boolean('is_custom')->default(true);
            $table->boolean('is_active')->default(true);
            $table->mediumText('design')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
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
};
