<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address1');
            $table->string('address2');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->unsignedInteger('country_id')->nullable();
            $table->string('work_phone');
            $table->text('private_notes');
            $table->string('website');
            $table->tinyInteger('is_deleted')->default(0);
            $table->integer('public_id')->default(0);
            $table->string('vat_number')->nullable();
            $table->string('id_number')->nullable();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('vendor_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->boolean('is_primary')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $table->unsignedInteger('public_id')->nullable();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->decimal('amount', 13, 2);
            $table->decimal('foreign_amount', 13, 2);
            $table->decimal('exchange_rate', 13, 4);
            $table->date('expense_date')->nullable();
            $table->text('private_notes');
            $table->text('public_notes');
            $table->unsignedInteger('invoice_currency_id')->nullable(false);
            $table->boolean('should_be_invoiced')->default(true);

            // Relations
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('public_id')->index();

            //$table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            //$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            //$table->unique(array('account_id', 'public_id'));
        });

        // Update public id
        $paymentTerms = DB::table('payment_terms')
                    ->where('public_id', '=', 0)
                    ->select('id', 'public_id')
                    ->get();
        $i = 1;
        foreach ($paymentTerms as $pTerm) {
            $data = ['public_id' => $i++];
            DB::table('payment_terms')->where('id', $pTerm->id)->update($data);
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('has_expenses')->default(false);
        });

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unique(['account_id', 'public_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('expenses');
        Schema::drop('vendor_contacts');
        Schema::drop('vendors');
    }
}
