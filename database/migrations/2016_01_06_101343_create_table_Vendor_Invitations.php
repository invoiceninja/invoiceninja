<?php
// vendor
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableVendorInvitations extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('vendor_invitations');
		Schema::create('vendor_invitations', function(Blueprint $table)
		{
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('contact_id');
            //$table->unsignedInteger('invoice_id')->index();
            $table->string('invitation_key')->index()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->string('transaction_reference')->nullable();
            $table->timestamp('sent_date')->nullable();
            $table->timestamp('viewed_date')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');;
            $table->foreign('contact_id')->references('id')->on('vendor_contacts')->onDelete('cascade');
            //$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

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
		Schema::table('vendor_invitations', function(Blueprint $table)
		{
			//
		});
		
		Schema::dropIfExists('vendor_invitations');
	}

}
