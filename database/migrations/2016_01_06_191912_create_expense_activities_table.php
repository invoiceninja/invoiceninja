<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExpenseActivitiesTable extends Migration {

    // Expenses model
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('expense_activities');
		Schema::create('expense_activities', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('vendor_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('contact_id')->nullable();
			$table->unsignedInteger('expense_id');
            $table->unsignedInteger('invitation_id')->nullable();
            $table->text('message')->nullable();
            $table->text('json_backup')->nullable();
            $table->integer('activity_type_id');            
            $table->decimal('adjustment', 13, 2)->nullable();
            $table->decimal('balance', 13, 2)->nullable();
            $table->unsignedInteger('token_id')->nullable();
			$table->string('ip')->nullable();
			$table->boolean('is_system')->default(0);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
			$table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('expense_activities');
	}

}
