<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRemember2faToken extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('remember_2fa_token', 100)->nullable();
        });

        Schema::dropIfExists('task_statuses');
        Schema::create('task_statuses', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name')->nullable();
            $table->smallInteger('sort_order')->default(0);

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('tasks', function ($table) {
            $table->unsignedInteger('task_status_id')->index()->nullable();
            $table->smallInteger('task_status_sort_order')->default(0);
        });

        Schema::table('tasks', function ($table) {
            $table->foreign('task_status_id')->references('id')->on('task_statuses')->onDelete('cascade');
        });

        Schema::table('currencies', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('convert_products')->default(false);
            $table->boolean('enable_reminder4')->default(false);
            $table->boolean('signature_on_pdf')->default(false);
        });

        Schema::table('invoice_items', function ($table) {
            $table->float('discount');
        });

        Schema::table('projects', function ($table) {
            $table->date('due_date')->nullable();
            $table->text('private_notes')->nullable();
            $table->float('budgeted_hours');
        });

        Schema::table('account_email_settings', function ($table) {
            $table->string('email_subject_reminder4')->nullable();
            $table->text('email_template_reminder4')->nullable();
            $table->unsignedInteger('frequency_id_reminder4')->nullable();
        });

        Schema::table('frequencies', function ($table) {
            $table->string('date_interval')->nullable();
        });

        DB::statement("update invoices, (
            	select max(created_at) created_at, invoice_id
            	from activities
            	where activity_type_id = 6
            	group by invoice_id
            ) as activities
            set invoices.last_sent_date = activities.created_at
            where invoices.id = activities.invoice_id
            and invoices.is_recurring = 0
            and invoices.invoice_type_id = 1");

        DB::statement("update invoices, (
            	select max(created_at) created_at, invoice_id
            	from activities
            	where activity_type_id = 20
            	group by invoice_id
            ) as activities
            set invoices.last_sent_date = activities.created_at
            where invoices.id = activities.invoice_id
            and invoices.is_recurring = 0
            and invoices.invoice_type_id = 2");

        if (! Utils::isNinja()) {
            Schema::table('activities', function ($table) {
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('remember_2fa_token');
        });

        Schema::table('tasks', function ($table) {
            $table->dropForeign('tasks_task_status_id_foreign');
        });

        Schema::table('tasks', function ($table) {
            $table->dropColumn('task_status_id');
            $table->dropColumn('task_status_sort_order');
        });

        Schema::dropIfExists('task_statuses');

        Schema::table('currencies', function ($table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('convert_products');
            $table->dropColumn('enable_reminder4');
            $table->dropColumn('signature_on_pdf');
        });

        Schema::table('invoice_items', function ($table) {
            $table->dropColumn('discount');
        });

        Schema::table('projects', function ($table) {
            $table->dropColumn('due_date');
            $table->dropColumn('private_notes');
            $table->dropColumn('budgeted_hours');
        });

        Schema::table('account_email_settings', function ($table) {
            $table->dropColumn('email_subject_reminder4');
            $table->dropColumn('email_template_reminder4');
            $table->dropColumn('frequency_id_reminder4');
        });

        Schema::table('frequencies', function ($table) {
            $table->dropColumn('date_interval');
        });

    }
}
