<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExpensesTable extends Migration
{
    // Expenses model
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('expenses');
		Schema::create('expenses', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
            $table->softDeletes();

            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('user_id');
			$table->unsignedInteger('invoice_id')->nullable();
			$table->unsignedInteger('invoice_client_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->decimal('amount', 13, 2);
			$table->decimal('amount_cur', 13, 2);
			$table->decimal('exchange_rate', 13, 2);
            $table->date('expense_date')->nullable();
            $table->text('private_notes');
			$table->text('public_notes');
            $table->integer('currency_id',false, true)->nullable();
			$table->boolean('is_invoiced')->default(false);
			$table->boolean('should_be_invoiced')->default(true);

			// Relations
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

			// Indexes
            $table->unsignedInteger('public_id')->index();
            $table->unique( array('account_id','public_id') );
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
	}
}
