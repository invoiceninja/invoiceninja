<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class FixConstraints extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('account_email_settings', function ($table) {
            $table->string('email_subject_invoice')->nullable()->change();
            $table->string('email_subject_quote')->nullable()->change();
            $table->string('email_subject_payment')->nullable()->change();
            $table->text('email_template_invoice')->nullable()->change();
            $table->text('email_template_quote')->nullable()->change();
            $table->text('email_template_payment')->nullable()->change();
            $table->string('email_subject_reminder1')->nullable()->change();
            $table->string('email_subject_reminder2')->nullable()->change();
            $table->string('email_subject_reminder3')->nullable()->change();
            $table->text('email_template_reminder1')->nullable()->change();
            $table->text('email_template_reminder2')->nullable()->change();
            $table->text('email_template_reminder3')->nullable()->change();
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('all_pages_footer')->default(false)->change();
            $table->boolean('all_pages_header')->default(false)->change();
            $table->boolean('show_currency_code')->default(false)->change();

            $table->unsignedInteger('logo_width')->nullable()->change();
            $table->unsignedInteger('logo_height')->nullable()->change();
            $table->unsignedInteger('logo_size')->nullable()->change();

            $table->integer('start_of_week')->nullable()->change();

            $table->decimal('tax_rate1', 13, 3)->nullable()->change();
            $table->decimal('tax_rate2', 13, 3)->nullable()->change();
        });

        Schema::table('companies', function ($table) {
            $table->decimal('discount', 8, 2)->default(0)->change();
        });

        Schema::table('expenses', function ($table) {
            $table->decimal('tax_rate1', 13, 3)->nullable()->change();
            $table->decimal('tax_rate2', 13, 3)->nullable()->change();
        });

        Schema::table('invoices', function ($table) {
            $table->text('tax_name1')->nullable()->change();
            $table->text('tax_name2')->nullable()->change();
            $table->decimal('tax_rate1', 13, 3)->nullable()->change();
            $table->decimal('tax_rate2', 13, 3)->nullable()->change();
        });

        Schema::table('payments', function($table) {
            $table->decimal('refunded', 13, 2)->default(0)->change();
        });

        Schema::table('products', function ($table) {
            $table->decimal('tax_rate1', 13, 3)->nullable()->change();
            $table->decimal('tax_rate2', 13, 3)->nullable()->change();
        });

        Schema::table('recurring_expenses', function ($table) {
            $table->decimal('tax_rate1', 13, 3)->nullable()->change();
            $table->decimal('tax_rate2', 13, 3)->nullable()->change();
        });

        Schema::table('users', function ($table) {
            $table->longtext('permissions')->nullable()->change();

            $table->string('avatar', 255)->nullable()->change();
            $table->unsignedInteger('avatar_width')->nullable()->change();
            $table->unsignedInteger('avatar_height')->nullable()->change();
            $table->unsignedInteger('avatar_size')->nullable()->change();
            $table->text('signature')->nullable()->change();
        });

        Schema::table('tickets', function ($table) {
            //$table->text('subject')->nullable()->change();
            //$table->text('description')->nullable()->change();
            $table->longtext('tags')->nullable()->change();
            $table->longtext('private_notes')->nullable()->change();
            $table->longtext('ccs')->nullable()->change();
            $table->string('ip_address', 255)->nullable()->change();
            $table->string('contact_key', 255)->nullable()->change();
            $table->dateTime('due_date')->nullable()->change();
            $table->dateTime('closed')->nullable()->change();
            $table->dateTime('reopened')->nullable()->change();
        });

        Schema::table('ticket_templates', function ($table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('ticket_comments', function ($table) {
            $table->text('description')->nullable()->change();
            $table->string('contact_key', 255)->nullable()->change();
        });

        Schema::table('account_ticket_settings', function ($table) {
            $table->string('from_name', 255)->nullable()->change();
            $table->string('mime_types')->nullable()->change();
            $table->unsignedInteger('new_ticket_template_id')->nullable()->change();
            $table->unsignedInteger('close_ticket_template_id')->nullable()->change();
            $table->unsignedInteger('update_ticket_template_id')->nullable()->change();
            $table->unsignedInteger('default_priority')->nullable()->change();
            $table->string('ticket_number_prefix')->nullable()->change();
            $table->longtext('alert_new_comment_id_email')->nullable()->change();
            $table->longtext('alert_ticket_assign_email')->nullable()->change();
            $table->longtext('alert_ticket_overdue_email')->nullable()->change();
            $table->string('postmark_api_token')->nullable()->change();
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
}
