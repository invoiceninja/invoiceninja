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
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique('projects_company_id_name_unique');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('invoice_currency_id')->nullable()->change();
            $table->unsignedInteger('expense_currency_id')->nullable()->change();
            $table->text('private_notes')->nullable()->change();
            $table->text('public_notes')->nullable()->change();
            $table->text('transaction_reference')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('invoice_expense_documents')->default(false);
            $table->boolean('auto_start_tasks')->default(false);
        });

        Schema::create('task_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->index(['company_id', 'deleted_at']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('rate', 16, 4)->default(0);
            $table->renameColumn('task_status_id', 'status_id');
            $table->renameColumn('task_status_sort_order', 'sort_order');
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
