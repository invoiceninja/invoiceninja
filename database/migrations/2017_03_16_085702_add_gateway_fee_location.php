<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGatewayFeeLocation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function ($table) {
            $table->integer('invoice_number_counter')->default(1)->nullable();
            $table->integer('quote_number_counter')->default(1)->nullable();
        });

        Schema::table('credits', function ($table) {
            $table->text('public_notes')->nullable();
        });

        // update invoice_item_type_id for task invoice items
        DB::statement('update invoice_items
            left join invoices on invoices.id = invoice_items.invoice_id
            set invoice_item_type_id = 2
            where invoices.has_tasks = 1');

        Schema::create('account_email_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();

            $table->string('reply_to_email')->nullable();
            $table->string('bcc_email')->nullable();

            $table->string('email_subject_invoice');
            $table->string('email_subject_quote');
            $table->string('email_subject_payment');
            $table->text('email_template_invoice');
            $table->text('email_template_quote');
            $table->text('email_template_payment');

            $table->string('email_subject_reminder1');
            $table->string('email_subject_reminder2');
            $table->string('email_subject_reminder3');
            $table->text('email_template_reminder1');
            $table->text('email_template_reminder2');
            $table->text('email_template_reminder3');

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        DB::statement('insert into account_email_settings (account_id,
                bcc_email,
                email_subject_invoice,
                email_subject_quote,
                email_subject_payment,
                email_template_invoice,
                email_template_quote,
                email_template_payment,
                email_subject_reminder1,
                email_subject_reminder2,
                email_subject_reminder3,
                email_template_reminder1,
                email_template_reminder2,
                email_template_reminder3
            )
            select id,
                bcc_email,
                email_subject_invoice,
                email_subject_quote,
                email_subject_payment,
                email_template_invoice,
                email_template_quote,
                email_template_payment,
                email_subject_reminder1,
                email_subject_reminder2,
                email_subject_reminder3,
                email_template_reminder1,
                email_template_reminder2,
                email_template_reminder3
            from accounts;');

        Schema::table('accounts', function ($table) {
            $table->dropColumn('email_subject_invoice');
            $table->dropColumn('email_subject_quote');
            $table->dropColumn('email_subject_payment');
            $table->dropColumn('email_template_invoice');
            $table->dropColumn('email_template_quote');
            $table->dropColumn('email_template_payment');
            $table->dropColumn('email_subject_reminder1');
            $table->dropColumn('email_subject_reminder2');
            $table->dropColumn('email_subject_reminder3');
            $table->dropColumn('email_template_reminder1');
            $table->dropColumn('email_template_reminder2');
            $table->dropColumn('email_template_reminder3');

            if (Schema::hasColumn('accounts', 'bcc_email')) {
                $table->dropColumn('bcc_email');
            }
            if (Schema::hasColumn('accounts', 'auto_wrap')) {
                $table->dropColumn('auto_wrap');
            }
            if (Schema::hasColumn('accounts', 'utf8_invoices')) {
                $table->dropColumn('utf8_invoices');
            }
            if (Schema::hasColumn('accounts', 'dark_mode')) {
                $table->dropColumn('dark_mode');
            }
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('gateway_fee_enabled')->default(0);
            $table->date('reset_counter_date')->nullable();
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
            $table->dropColumn('gateway_fee_enabled');
            $table->dropColumn('reset_counter_date');
        });

        Schema::table('clients', function ($table) {
            $table->dropColumn('invoice_number_counter');
            $table->dropColumn('quote_number_counter');
        });

        Schema::table('credits', function ($table) {
            $table->dropColumn('public_notes');
        });

        Schema::drop('account_email_settings');
    }
}
