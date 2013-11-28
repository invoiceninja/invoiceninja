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
        Schema::dropIfExists('activities');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('products');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');

        Schema::create('accounts', function($t)
        {
            $t->increments('id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('ip');
            $t->string('logo_path');
            $t->string('key')->unique();

            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->integer('country_id');            
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
            $t->integer('account_id');
            $t->integer('gateway_id');
            $t->timestamps();
            $t->softDeletes();

            $t->text('config');
        }); 

        Schema::create('users', function($t)
        {
            $t->increments('id');
            $t->integer('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('first_name');
            $t->string('last_name');
            $t->string('phone');
            $t->string('username');
            $t->string('email');
            $t->string('password');
            $t->string('confirmation_code');
            $t->boolean('is_guest')->default(true);
            $t->boolean('confirmed')->default(false);
            
            //$t->foreign('account_id')->references('id')->on('accounts');            
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
            $t->integer('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->integer('country_id');
            $t->string('work_phone');
            $t->text('notes');

            //$t->foreign('account_id')->references('id')->on('accounts');
        });     

        Schema::create('contacts', function($t)
        {
            $t->increments('id');
            $t->integer('client_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('first_name');
            $t->string('last_name');
            $t->string('email');
            $t->string('phone');
            $t->timestamp('last_login');

            //$t->foreign('account_id')->references('id')->on('accounts');
        });     


        Schema::create('invoices', function($t)
        {
            $t->increments('id');
            $t->integer('client_id');
            $t->integer('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('invoice_key')->unique();
            $t->string('invoice_number');
            $t->float('discount');
            $t->date('invoice_date');

            //$t->foreign('account_id')->references('id')->on('accounts');
        });

        Schema::create('invoice_items', function($t)
        {
            $t->increments('id');
            $t->integer('invoice_id');
            $t->timestamps();
            $t->softDeletes();

            $t->integer('product_id');
            $t->string('product_key');
            $t->string('notes');
            $t->decimal('cost', 8, 2);
            $t->integer('qty');

            //$t->foreign('account_id')->references('id')->on('accounts');
        });

        Schema::create('products', function($t)
        {
            $t->increments('id');
            $t->integer('account_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->string('notes');
            $t->decimal('cost', 8, 2);
            $t->integer('qty');
            
            //$t->foreign('account_id')->references('id')->on('accounts');
        });

        Schema::create('payments', function($t)
        {
            $t->increments('id');
            $t->integer('invoice_id');
            $t->integer('account_id');
            $t->integer('contact_id');
            $t->integer('user_id');
            $t->timestamps();
            $t->softDeletes();
            
            $t->decimal('amount', 8, 2);
            $t->string('transaction_reference');
            $t->string('payer_id');

            //$t->foreign('account_id')->references('id')->on('accounts');
        });     

        Schema::create('activities', function($t)
        {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('user_id');
            $t->integer('client_id');
            $t->integer('contact_id');
            $t->integer('invoice_id');
            $t->integer('payment_id');
            $t->timestamps();

            $t->integer('activity_type_id');
            $t->text('message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('products');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
    }

}
