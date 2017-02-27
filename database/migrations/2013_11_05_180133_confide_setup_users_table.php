<?php

use Illuminate\Database\Migrations\Migration;

class ConfideSetupUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('frequencies');
        Schema::dropIfExists('date_formats');
        Schema::dropIfExists('datetime_formats');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payment_types');

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
        });

        Schema::create('themes', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('payment_types', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('payment_terms', function ($t) {
            $t->increments('id');
            $t->integer('num_days');
            $t->string('name');
        });

        Schema::create('timezones', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->string('location');
        });

        Schema::create('date_formats', function ($t) {
            $t->increments('id');
            $t->string('format');
            $t->string('picker_format');
            $t->string('label');
        });

        Schema::create('datetime_formats', function ($t) {
            $t->increments('id');
            $t->string('format');
            $t->string('label');
        });

        Schema::create('currencies', function ($t) {
            $t->increments('id');

            $t->string('name');
            $t->string('symbol');
            $t->string('precision');
            $t->string('thousand_separator');
            $t->string('decimal_separator');
            $t->string('code');
        });

        Schema::create('sizes', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('industries', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('accounts', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('timezone_id')->nullable();
            $t->unsignedInteger('date_format_id')->nullable();
            $t->unsignedInteger('datetime_format_id')->nullable();
            $t->unsignedInteger('currency_id')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->string('name')->nullable();
            $t->string('ip');
            $t->string('account_key')->unique();
            $t->timestamp('last_login')->nullable();

            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->unsignedInteger('country_id')->nullable();
            $t->text('invoice_terms')->nullable();
            $t->text('email_footer')->nullable();
            $t->unsignedInteger('industry_id')->nullable();
            $t->unsignedInteger('size_id')->nullable();

            $t->boolean('invoice_taxes')->default(true);
            $t->boolean('invoice_item_taxes')->default(false);

            $t->foreign('timezone_id')->references('id')->on('timezones');
            $t->foreign('date_format_id')->references('id')->on('date_formats');
            $t->foreign('datetime_format_id')->references('id')->on('datetime_formats');
            $t->foreign('country_id')->references('id')->on('countries');
            $t->foreign('currency_id')->references('id')->on('currencies');
            $t->foreign('industry_id')->references('id')->on('industries');
            $t->foreign('size_id')->references('id')->on('sizes');
        });

        Schema::create('gateways', function ($t) {
            $t->increments('id');
            $t->timestamps();

            $t->string('name');
            $t->string('provider');
            $t->boolean('visible')->default(true);
        });

        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->timestamps();
            $t->softDeletes();

            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('username')->unique();
            $t->string('email')->nullable();
            $t->string('password');
            $t->string('confirmation_code')->nullable();
            $t->boolean('registered')->default(false);
            $t->boolean('confirmed')->default(false);
            $t->integer('theme_id')->nullable();

            $t->boolean('notify_sent')->default(true);
            $t->boolean('notify_viewed')->default(false);
            $t->boolean('notify_paid')->default(true);

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $t->unsignedInteger('public_id')->nullable();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('account_gateways', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('gateway_id');
            $t->timestamps();
            $t->softDeletes();

            $t->text('config');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('gateway_id')->references('id')->on('gateways');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('password_reminders', function ($t) {
            $t->string('email');
            $t->timestamps();

            $t->string('token');
        });

        Schema::create('clients', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('currency_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->unsignedInteger('country_id')->nullable();
            $t->string('work_phone')->nullable();
            $t->text('private_notes')->nullable();
            $t->decimal('balance', 13, 2)->nullable();
            $t->decimal('paid_to_date', 13, 2)->nullable();
            $t->timestamp('last_login')->nullable();
            $t->string('website')->nullable();
            $t->unsignedInteger('industry_id')->nullable();
            $t->unsignedInteger('size_id')->nullable();
            $t->boolean('is_deleted')->default(false);
            $t->integer('payment_terms')->nullable();

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('country_id')->references('id')->on('countries');
            $t->foreign('industry_id')->references('id')->on('industries');
            $t->foreign('size_id')->references('id')->on('sizes');
            $t->foreign('currency_id')->references('id')->on('currencies');

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('contacts', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_id')->index();
            $t->timestamps();
            $t->softDeletes();

            $t->boolean('is_primary')->default(0);
            $t->boolean('send_invoice')->default(0);
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->timestamp('last_login')->nullable();

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;

            $t->unsignedInteger('public_id')->nullable();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('invoice_statuses', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('frequencies', function ($t) {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('invoices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('invoice_status_id')->default(1);
            $t->timestamps();
            $t->softDeletes();

            $t->string('invoice_number');
            $t->float('discount');
            $t->string('po_number');
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();
            $t->text('terms');
            $t->text('public_notes');
            $t->boolean('is_deleted')->default(false);
            $t->boolean('is_recurring')->default(false);
            $t->unsignedInteger('frequency_id');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamp('last_sent_date')->nullable();
            $t->unsignedInteger('recurring_invoice_id')->index()->nullable();

            $t->string('tax_name1');
            $t->decimal('tax_rate1', 13, 3);

            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('invoice_status_id')->references('id')->on('invoice_statuses');
            $t->foreign('recurring_invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
            $t->unique(['account_id', 'invoice_number']);
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
            ;
            $t->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('tax_rates', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->decimal('rate', 13, 3);

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;

            $t->unsignedInteger('public_id');
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;

            $t->unsignedInteger('public_id');
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('invoice_items', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->unsignedInteger('product_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->text('notes');
            $t->decimal('cost', 13, 2);
            $t->decimal('qty', 13, 2)->nullable();

            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 13, 3)->nullable();

            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;

            $t->unsignedInteger('public_id');
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('payments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->index();
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

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('credits', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->boolean('is_deleted')->default(false);
            $t->decimal('amount', 13, 2);
            $t->decimal('balance', 13, 2);
            $t->date('credit_date')->nullable();
            $t->string('credit_number')->nullable();
            $t->text('private_notes');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            ;

            $t->unsignedInteger('public_id')->index();
            $t->unique(['account_id', 'public_id']);
        });

        Schema::create('activities', function ($t) {
            $t->increments('id');
            $t->timestamps();

            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('payment_id')->nullable();
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('credit_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();

            $t->text('message')->nullable();
            $t->text('json_backup')->nullable();
            $t->integer('activity_type_id');
            $t->decimal('adjustment', 13, 2)->nullable();
            $t->decimal('balance', 13, 2)->nullable();

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('credits');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('frequencies');
        Schema::dropIfExists('date_formats');
        Schema::dropIfExists('datetime_formats');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payment_types');
    }
}
