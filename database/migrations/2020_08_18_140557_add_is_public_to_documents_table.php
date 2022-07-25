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
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->decimal('amount', 16, 4);
        });

        Schema::table('company_gateways', function (Blueprint $table) {
            $table->enum('token_billing', ['off', 'always', 'optin', 'optout'])->default('off');
            $table->string('label', 255)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->text('meta')->nullable();
        });

        Schema::table('system_logs', function (Blueprint $table) {
            $table->softDeletes('deleted_at', 6);
        });

        Schema::create('payment_hashes', function ($table) {
            $table->increments('id');
            $table->string('hash', 255);
            $table->decimal('fee_total', 16, 4);
            $table->unsignedInteger('fee_invoice_id')->nullable();
            $table->mediumText('data');
            $table->unsignedInteger('payment_id')->nullable();
            $table->timestamps(6);

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('recurring_invoices', function ($table) {
            $table->string('auto_bill')->default('off');
            $table->boolean('auto_bill_enabled')->default(0);
            $table->unsignedInteger('design_id')->nullable();
            $table->boolean('uses_inclusive_taxes')->default(0);
            $table->string('custom_surcharge1')->nullable();
            $table->string('custom_surcharge2')->nullable();
            $table->string('custom_surcharge3')->nullable();
            $table->string('custom_surcharge4')->nullable();
            $table->boolean('custom_surcharge_tax1')->default(false);
            $table->boolean('custom_surcharge_tax2')->default(false);
            $table->boolean('custom_surcharge_tax3')->default(false);
            $table->boolean('custom_surcharge_tax4')->default(false);
            $table->integer('remaining_cycles')->nullable()->change();
            $table->dropColumn('start_date');
            $table->string('due_date_days')->nullable();
            $table->date('partial_due_date')->nullable();

            $table->decimal('exchange_rate', 13, 6)->default(1);
        });

        Schema::table('invoices', function ($table) {
            $table->boolean('auto_bill_enabled')->default(0);
        });

        Schema::table('companies', function ($table) {
            $table->enum('default_auto_bill', ['off', 'always', 'optin', 'optout'])->default('off');
        });

        Schema::create('recurring_invoice_invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('recurring_invoice_id')->index();
            $t->string('key')->index();

            $t->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->index(['deleted_at', 'recurring_invoice_id', 'company_id'], 'rec_co_del');
            $t->unique(['client_contact_id', 'recurring_invoice_id'], 'cli_rec');
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
