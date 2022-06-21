<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recurring_expenses', function ($table) {
            $table->increments('id');
            $table->timestamps(6);
            $table->softDeletes();

            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('status_id');

            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('payment_type_id')->nullable();
            $table->unsignedInteger('recurring_expense_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('uses_inclusive_taxes')->default(true);
            $table->string('tax_name1')->nullable();
            $table->string('tax_name2')->nullable();
            $table->string('tax_name3')->nullable();
            $table->date('date')->nullable();
            $table->date('payment_date')->nullable();
            $table->boolean('should_be_invoiced')->default(false);
            $table->boolean('invoice_documents')->default(false);
            $table->string('transaction_id')->nullable();
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
            $table->string('custom_value3')->nullable();
            $table->string('custom_value4')->nullable();

            $table->unsignedInteger('category_id')->nullable();
            $table->boolean('calculate_tax_by_amount')->default(false);
            $table->decimal('tax_amount1', 20, 6)->nullable();
            $table->decimal('tax_amount2', 20, 6)->nullable();
            $table->decimal('tax_amount3', 20, 6)->nullable();
            $table->decimal('tax_rate1', 20, 6)->nullable();
            $table->decimal('tax_rate2', 20, 6)->nullable();
            $table->decimal('tax_rate3', 20, 6)->nullable();
            $table->decimal('amount', 20, 6)->nullable();
            $table->decimal('foreign_amount', 20, 6)->nullable();
            $table->decimal('exchange_rate', 20, 6)->default(1);
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->string('number')->nullable();
            $table->unsignedInteger('invoice_currency_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('transaction_reference')->nullable();

            $table->unsignedInteger('frequency_id');
            $table->datetime('last_sent_date')->nullable();
            $table->datetime('next_send_date')->nullable();
            $table->integer('remaining_cycles')->nullable();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'deleted_at']);

            // Relations
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('recurring_expense_id')->nullable();
            $table->unsignedInteger('recurring_quote_id')->nullable();
        });

        Schema::table('recurring_quotes', function ($table) {
            $table->string('auto_bill')->default('off');
            $table->boolean('auto_bill_enabled')->default(0);
            $table->decimal('paid_to_date', 20, 6)->default(0);
            $table->decimal('custom_surcharge1', 20, 6)->nullable();
            $table->decimal('custom_surcharge2', 20, 6)->nullable();
            $table->decimal('custom_surcharge3', 20, 6)->nullable();
            $table->decimal('custom_surcharge4', 20, 6)->nullable();
            $table->boolean('custom_surcharge_tax1')->default(false);
            $table->boolean('custom_surcharge_tax2')->default(false);
            $table->boolean('custom_surcharge_tax3')->default(false);
            $table->boolean('custom_surcharge_tax4')->default(false);
            $table->string('due_date_days')->nullable();
            $table->decimal('exchange_rate', 13, 6)->default(1);
            $table->decimal('partial', 16, 4)->nullable();
            $table->date('partial_due_date')->nullable();
            $table->unsignedInteger('remaining_cycles')->nullable()->change();
            $table->unsignedInteger('subscription_id')->nullable();
            $table->dropColumn('start_date');
            $table->boolean('uses_inclusive_taxes')->default(true);
        });

        Schema::create('recurring_quote_invitations', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('client_contact_id');
            $t->unsignedInteger('recurring_quote_id')->index();
            $t->string('key')->index();

            $t->foreign('recurring_quote_id')->references('id')->on('recurring_invoices')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('client_contact_id')->references('id')->on('client_contacts')->onDelete('cascade')->onUpdate('cascade');
            $t->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $t->string('transaction_reference')->nullable();
            $t->string('message_id')->nullable();
            $t->mediumText('email_error')->nullable();
            $t->text('signature_base64')->nullable();
            $t->datetime('signature_date')->nullable();

            $t->datetime('sent_date')->nullable();
            $t->datetime('viewed_date')->nullable();
            $t->datetime('opened_date')->nullable();
            $t->enum('email_status', ['delivered', 'bounced', 'spam'])->nullable();

            $t->timestamps(6);
            $t->softDeletes('deleted_at', 6);

            $t->index(['deleted_at', 'recurring_quote_id', 'company_id'], 'rec_co_del_q');
            $t->unique(['client_contact_id', 'recurring_quote_id'], 'cli_rec_q');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
