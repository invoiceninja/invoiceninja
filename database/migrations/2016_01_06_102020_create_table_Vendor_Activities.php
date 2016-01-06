<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableVendorActivities extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('vendor_activities');
		Schema::create('vendor_activities', function(Blueprint $table)
		{
            $table->increments('id');
            $table->timestamps();

            $table->unsignedInteger('account_id');
            $table->unsignedInteger('vendor_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            //$table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('credit_id')->nullable();
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
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('vendor_activities', function(Blueprint $table)
		{
			//
		});
		
		Schema::dropIfExists('vendor_activities');
	}

}
