<?php
use Illuminate\Database\Migrations\Migration;

class ConfideSetupUsersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('credits');        
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
        

        Schema::create('countries', function($table)
        {           
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

        Schema::create('timezones', function($t)
        {
            $t->increments('id');
            $t->string('name');
            $t->string('location');
        });

        Schema::create('accounts', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('timezone_id')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('ip');
            $t->string('logo_path');
            $t->string('key')->unique();
            $t->timestamp('last_login');
            
            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->unsignedInteger('country_id')->nullable();     

            $t->foreign('timezone_id')->references('id')->on('timezones');
            $t->foreign('country_id')->references('id')->on('countries');       
        });     

        
        Schema::create('gateways', function($t)
        {
            $t->increments('id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('provider');
            $t->boolean('visible')->default(true);
        });     

        Schema::create('account_gateways', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('gateway_id');
            $t->timestamps();
            $t->softDeletes();

            $t->text('config');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('gateway_id')->references('id')->on('gateways');
        }); 

        Schema::create('users', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('first_name');
            $t->string('last_name');
            $t->string('phone');
            $t->string('username');
            $t->string('email');
            $t->string('password');
            $t->string('confirmation_code');
            $t->boolean('registered')->default(false);
            $t->boolean('confirmed')->default(false);
            
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        Schema::create('password_reminders', function($t)
        {
            $t->string('email');
            $t->timestamps();
            
            $t->string('token');
        });        

        Schema::create('clients', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('country_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->string('work_phone');
            $t->text('notes');
            $t->decimal('balance', 10, 2);
            $t->timestamp('last_login');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');         
            $t->foreign('country_id')->references('id')->on('countries');       
        });     

        Schema::create('contacts', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('client_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('first_name');
            $t->string('last_name');
            $t->string('email');
            $t->string('phone');
            $t->timestamp('last_login');

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade'); 
        });     

        Schema::create('invoice_statuses', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });


        Schema::create('invoices', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('client_id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('invoice_status_id')->default(1);
            $t->timestamps();
            $t->softDeletes();

            $t->string('invoice_number');
            $t->float('discount');
            $t->date('invoice_date');
            $t->date('due_date');

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade'); 
            $t->foreign('account_id')->references('id')->on('accounts'); 
            $t->foreign('invoice_status_id')->references('id')->on('invoice_statuses');
        });


        Schema::create('invitations', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('contact_id');
            $t->unsignedInteger('invoice_id');
            $t->string('key')->unique();
            $t->timestamps();
            $t->softDeletes();

            $t->timestamp('viewed_date');

            $t->foreign('user_id')->references('id')->on('users');
            $t->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        Schema::create('products', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('key');
            $t->string('notes');
            $t->decimal('cost', 10, 2);
            $t->integer('qty');
            
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade'); 
        });


        Schema::create('invoice_items', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('invoice_id');
            $t->unsignedInteger('product_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->string('notes');
            $t->decimal('cost', 10, 2);
            $t->integer('qty');

            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('payments', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('invoice_id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id');
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
            
            $t->decimal('amount', 10, 2);
            $t->date('payment_date');
            $t->string('transaction_reference');
            $t->string('payer_id');

            $t->foreign('invoice_id')->references('id')->on('invoices');
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts');
            $t->foreign('user_id')->references('id')->on('users');
        });     

        Schema::create('credits', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->unsignedInteger('contact_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
            
            $t->decimal('amount', 10, 2);
            $t->date('credit_date');
            $t->string('credit_number');
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts');
        });     

        Schema::create('activities', function($t)
        {
            $t->increments('id');
            $t->timestamps();

            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('contact_id');
            $t->unsignedInteger('payment_id');
            $t->unsignedInteger('invoice_id');
            $t->unsignedInteger('credit_id');
            $t->unsignedInteger('invitation_id');
            
            $t->text('message');
            $t->integer('activity_type_id');            
            $t->decimal('adjustment', 10, 2);
            $t->decimal('balance', 10, 2);
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credits');        
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');
    }
}
