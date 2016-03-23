<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDocuments extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('accounts', function($table) {
			$table->string('logo')->nullable()->default(null);
			$table->unsignedInteger('logo_width');
			$table->unsignedInteger('logo_height');
			$table->unsignedInteger('logo_size');
			$table->boolean('invoice_embed_documents')->default(1);
			$table->boolean('document_email_attachment')->default(1);
		});
		
		DB::table('accounts')->update(array('logo' => ''));
		Schema::dropIfExists('documents');
		Schema::create('documents', function($t)
        {
            $t->increments('id');
			$t->unsignedInteger('public_id')->nullable();
			$t->unsignedInteger('account_id');
			$t->unsignedInteger('user_id');
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('expense_id')->nullable();
            $t->string('path');
			$t->string('preview');
			$t->string('name');
			$t->string('type');
			$t->string('disk');
            $t->unsignedInteger('size');
			$t->unsignedInteger('width')->nullable();
			$t->unsignedInteger('height')->nullable();

            $t->timestamps();

            $t->foreign('account_id')->references('id')->on('accounts');
			$t->foreign('user_id')->references('id')->on('users');
			$t->foreign('invoice_id')->references('id')->on('invoices');
			$t->foreign('expense_id')->references('id')->on('expenses');
			
			
            $t->unique( array('account_id','public_id') );
        });     
	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('accounts', function($table) {
			$table->dropColumn('logo');
			$table->dropColumn('logo_width');
			$table->dropColumn('logo_height');
			$table->dropColumn('logo_size');
			$table->dropColumn('invoice_embed_documents');
		});
		
		Schema::dropIfExists('documents');
	}
}
