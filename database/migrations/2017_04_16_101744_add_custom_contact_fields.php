<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomContactFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('custom_contact_label1')->nullable();
            $table->string('custom_contact_label2')->nullable();
        });

        Schema::table('contacts', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        // This may fail if the foreign key doesn't exist
        try {
            Schema::table('payment_methods', function ($table) {
                $table->unsignedInteger('account_gateway_token_id')->nullable()->change();
                $table->dropForeign('payment_methods_account_gateway_token_id_foreign');
            });

            Schema::table('payment_methods', function ($table) {
                $table->foreign('account_gateway_token_id')->references('id')->on('account_gateway_tokens')->onDelete('cascade');
            });

            Schema::table('payments', function ($table) {
                $table->dropForeign('payments_payment_method_id_foreign');
            });

            Schema::table('payments', function ($table) {
                $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
            });
        } catch (Exception $e) {
            // do nothing
        }

        Schema::table('expenses', function($table) {
            $table->unsignedInteger('payment_type_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->foreign('payment_type_id')->references('id')->on('payment_types');
            $table->boolean('invoice_documents')->default(true);
        });

        // remove duplicate annual frequency
        if (DB::table('frequencies')->count() == 9) {
            DB::statement('update invoices set frequency_id = 8 where is_recurring = 1 and frequency_id = 9');
            DB::statement('update accounts set reset_counter_frequency_id = 8 where reset_counter_frequency_id = 9');
            DB::statement('update frequencies set name = "Annually" where id = 8');
            DB::statement('delete from frequencies where id = 9');
        }

        Schema::create('db_servers', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('lookup_companies', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('db_server_id');

            $table->foreign('db_server_id')->references('id')->on('db_servers');
        });

        Schema::create('lookup_accounts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_company_id')->index();
            $table->string('account_key');

            $table->foreign('lookup_company_id')->references('id')->on('lookup_companies')->onDelete('cascade');
        });

        Schema::create('lookup_users', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('email');

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

        Schema::create('lookup_contacts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('contact_key');

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

        Schema::create('lookup_invitations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('invitation_key');
            $table->string('message_id');

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

        Schema::create('lookup_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('lookup_account_id')->index();
            $table->string('token');

            $table->foreign('lookup_account_id')->references('id')->on('lookup_accounts')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('custom_contact_label1');
            $table->dropColumn('custom_contact_label2');
        });

        Schema::table('contacts', function ($table) {
            $table->dropColumn('custom_value1');
            $table->dropColumn('custom_value2');
        });

        Schema::table('expenses', function($table) {
            $table->dropColumn('payment_type_id');
            $table->dropColumn('payment_date');
            $table->dropColumn('transaction_reference');
            $table->dropColumn('invoice_documents');
        });

        Schema::dropIfExists('db_servers');
        Schema::dropIfExists('lookup_companies');
        Schema::dropIfExists('lookup_accounts');
        Schema::dropIfExists('lookup_users');
        Schema::dropIfExists('lookup_contacts');
        Schema::dropIfExists('lookup_invitations');
        Schema::dropIfExists('lookup_tokens');
    }
}
