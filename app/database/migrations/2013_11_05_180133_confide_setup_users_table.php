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
        Schema::dropIfExists('payment_terms');             
        Schema::dropIfExists('themes');        
        Schema::dropIfExists('credits');        
        Schema::dropIfExists('activities');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');        
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('client_sizes');
        Schema::dropIfExists('client_industries');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('currencies');        
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');        
        Schema::dropIfExists('frequencies');        
        Schema::dropIfExists('date_formats');        
        Schema::dropIfExists('datetime_formats');                

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

        Schema::create('themes', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('payment_terms', function($t)
        {
            $t->increments('id');
            $t->integer('num_days');
            $t->string('name');
        });

        Schema::create('timezones', function($t)
        {
            $t->increments('id');
            $t->string('name');
            $t->string('location');
        });

        Schema::create('date_formats', function($t)
        {
            $t->increments('id');
            $t->string('format');    
            $t->string('picker_format');                    
            $t->string('label');            
        });

        Schema::create('datetime_formats', function($t)
        {
            $t->increments('id');
            $t->string('format');            
            $t->string('label');            
        });

        Schema::create('currencies', function($t)
        {
            $t->increments('id');            

            $t->string('name');
            $t->string('symbol');
            $t->string('precision');
            $t->string('thousand_separator');
            $t->string('decimal_separator');
        });  

        Schema::create('accounts', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('timezone_id')->nullable();
            $t->unsignedInteger('date_format_id')->nullable();
            $t->unsignedInteger('datetime_format_id')->nullable();
            $t->unsignedInteger('currency_id')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('ip');
            $t->string('account_key')->unique();
            $t->timestamp('last_login');
            
            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->unsignedInteger('country_id')->nullable();     
            $t->text('invoice_terms');

            $t->boolean('invoice_taxes')->default(true);
            $t->boolean('invoice_item_taxes')->default(false);

            $t->foreign('timezone_id')->references('id')->on('timezones');
            $t->foreign('date_format_id')->references('id')->on('date_formats');
            $t->foreign('datetime_format_id')->references('id')->on('datetime_formats');
            $t->foreign('country_id')->references('id')->on('countries');       
            $t->foreign('currency_id')->references('id')->on('currencies');       
        });     
        
        Schema::create('gateways', function($t)
        {
            $t->increments('id');
            $t->timestamps();            

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
            
            $t->text('config');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('gateway_id')->references('id')->on('gateways');
        }); 

        Schema::create('users', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
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
            $t->integer('theme_id');

            $t->boolean('notify_sent')->default(true);
            $t->boolean('notify_viewed')->default(false);
            $t->boolean('notify_paid')->default(true);

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $t->unsignedInteger('public_id');
            $t->unique( array('account_id','public_id') );
        });

        Schema::create('password_reminders', function($t)
        {
            $t->string('email');
            $t->timestamps();
            
            $t->string('token');
        });        

        Schema::create('client_sizes', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });        

        Schema::create('client_industries', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });        

        Schema::create('clients', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();            
            $t->unsignedInteger('currency_id')->default(1)->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('address1');
            $t->string('address2');
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->unsignedInteger('country_id')->nullable();
            $t->string('work_phone');
            $t->text('private_notes');
            $t->decimal('balance', 13, 4);
            $t->decimal('paid_to_date', 13, 4);
            $t->timestamp('last_login')->nullable();
            $t->string('website');
            $t->unsignedInteger('client_industry_id')->nullable();
            $t->unsignedInteger('client_size_id')->nullable();
            $t->boolean('is_deleted');
            $t->integer('payment_terms');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('country_id')->references('id')->on('countries');       
            $t->foreign('client_industry_id')->references('id')->on('client_industries');       
            $t->foreign('client_size_id')->references('id')->on('client_sizes');       
            $t->foreign('currency_id')->references('id')->on('currencies');
            
            $t->unsignedInteger('public_id')->index();
            $t->unique( array('account_id','public_id') );
        });     

        Schema::create('contacts', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_id')->index();
            $t->timestamps();
            $t->softDeletes();

            $t->boolean('is_primary');
            $t->boolean('send_invoice');
            $t->string('first_name');
            $t->string('last_name');
            $t->string('email');
            $t->string('phone');
            $t->timestamp('last_login');            

            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;

            $t->unsignedInteger('public_id');
            $t->unique( array('account_id','public_id') );
        });     

        Schema::create('invoice_statuses', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('frequencies', function($t)
        {
            $t->increments('id');
            $t->string('name');
        });

        Schema::create('invoices', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('invoice_status_id')->default(1);
            $t->unsignedInteger('currency_id')->default(1);
            $t->timestamps();
            $t->softDeletes();

            $t->string('invoice_number');
            $t->float('discount');
            $t->string('po_number');
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();
            $t->text('terms');
            $t->text('public_notes');
            $t->boolean('is_deleted');            
            $t->boolean('is_recurring');
            $t->unsignedInteger('frequency_id');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamp('last_sent_date')->nullable();    
            $t->unsignedInteger('recurring_invoice_id')->index()->nullable();

            $t->string('tax_name');
            $t->decimal('tax_rate', 13, 4);

            $t->decimal('amount', 13, 4);
            $t->decimal('balance', 13, 4);
        
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade'); 
            $t->foreign('account_id')->references('id')->on('accounts'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');; 
            $t->foreign('invoice_status_id')->references('id')->on('invoice_statuses');
            $t->foreign('currency_id')->references('id')->on('currencies');
            $t->foreign('recurring_invoice_id')->references('id')->on('invoices');

            $t->unsignedInteger('public_id')->index();
            $t->unique( array('account_id','public_id') );
        });


        Schema::create('invitations', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('contact_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->string('invitation_key')->index()->unique();
            $t->timestamps();
            $t->softDeletes();

            $t->timestamp('sent_date');
            $t->timestamp('viewed_date');

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            $t->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $t->unsignedInteger('public_id')->index();
            $t->unique( array('account_id','public_id') );
        });

        Schema::create('tax_rates', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->decimal('rate', 13, 4);
            
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            
            $t->unsignedInteger('public_id');
            $t->unique( array('account_id','public_id') );
        });

        Schema::create('products', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->string('notes');
            $t->decimal('cost', 13, 4);
            $t->decimal('qty', 13, 4);
            
            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            
            $t->unsignedInteger('public_id');
            $t->unique( array('account_id','public_id') );
        });


        Schema::create('invoice_items', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('invoice_id')->index();
            $t->unsignedInteger('product_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('product_key');
            $t->string('notes');
            $t->decimal('cost', 13, 4);
            $t->decimal('qty', 13, 4);            

            $t->string('tax_name');
            $t->decimal('tax_rate', 13, 4);

            $t->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $t->foreign('product_id')->references('id')->on('products');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;

            $t->unsignedInteger('public_id');
            $t->unique( array('account_id','public_id') );
        });

        Schema::create('payments', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('client_id')->index();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('invitation_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('currency_id')->default(1);
            $t->timestamps();
            $t->softDeletes();

            $t->boolean('is_deleted');
            $t->decimal('amount', 13, 4);
            $t->date('payment_date');
            $t->string('transaction_reference');
            $t->string('payer_id');

            $t->foreign('invoice_id')->references('id')->on('invoices');
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            $t->foreign('currency_id')->references('id')->on('currencies');
            
            $t->unsignedInteger('public_id')->index();
            $t->unique( array('account_id','public_id') );
        });     

        Schema::create('credits', function($t)
        {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_id')->index()->nullable();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('currency_id')->default(1);
            $t->timestamps();
            $t->softDeletes();
            
            $t->boolean('is_deleted');
            $t->decimal('amount', 13, 4);
            $t->date('credit_date')->nullable();
            $t->string('credit_number');
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('contact_id')->references('id')->on('contacts');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            $t->foreign('currency_id')->references('id')->on('currencies');

            $t->unsignedInteger('public_id')->index();
            $t->unique( array('account_id','public_id') );
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
            $t->unsignedInteger('currency_id')->default(1);

            $t->text('message');
            $t->text('json_backup');
            $t->integer('activity_type_id');            
            $t->decimal('adjustment', 13, 4);
            $t->decimal('balance', 13, 4);
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $t->foreign('currency_id')->references('id')->on('currencies');
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
        Schema::dropIfExists('account_gateways');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('password_reminders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('client_sizes');
        Schema::dropIfExists('client_industries');
        Schema::dropIfExists('users');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('currencies');        
        Schema::dropIfExists('invoice_statuses');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('timezones');        
        Schema::dropIfExists('frequencies');        
        Schema::dropIfExists('date_formats');        
        Schema::dropIfExists('datetime_formats');                      
    }
}
