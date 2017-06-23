<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDarkMode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->boolean('dark_mode')->default(true)->change();
        });

        DB::statement('update users set dark_mode = 1;');

        // update invoice_item_type_id for task invoice items
        DB::statement('update invoice_items
            left join invoices on invoices.id = invoice_items.invoice_id
            set invoice_item_type_id = 2
            where invoices.has_tasks = 1
            and invoice_item_type_id = 1');

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->decimal('amount', 13, 2);
            $table->text('private_notes');
            $table->text('public_notes');
            $table->unsignedInteger('invoice_currency_id')->nullable(false);
            $table->unsignedInteger('expense_currency_id')->nullable()->index();
            $table->boolean('should_be_invoiced')->default(true);
            $table->unsignedInteger('expense_category_id')->nullable()->index();
            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 13, 3);
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);

            // Relations
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invoice_currency_id')->references('id')->on('currencies');
            $table->foreign('expense_currency_id')->references('id')->on('currencies');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->onDelete('cascade');

            // Indexes
            $table->unsignedInteger('public_id')->index();
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
        Schema::drop('recurring_expenses');
    }
}
