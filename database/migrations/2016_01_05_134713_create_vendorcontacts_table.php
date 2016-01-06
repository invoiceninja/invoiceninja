<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorcontactsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::dropIfExists('vendor_contacts');
		Schema::create('vendor_contacts', function(Blueprint $table)
		{
			$table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('vendor_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->boolean('is_primary')->default(0);
            //$table->boolean('send_invoice')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('last_login')->nullable();            

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade'); 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;

            $table->unsignedInteger('public_id')->nullable();
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
		Schema::drop('vendor_contacts');
	}

}
