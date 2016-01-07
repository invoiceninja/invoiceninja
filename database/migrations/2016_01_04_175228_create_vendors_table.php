<?php
// vendor
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::dropIfExists('vendors');
		Schema::create('vendors', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->integer('user_id', false, true);
			$table->integer('account_id', false, true);
			$table->integer('currency_id',false, true)->nullable();
			$table->string('name')->nullable();
			$table->string('address1');
			$table->string('address2');
			$table->string('city');
			$table->string('state');
			$table->string('postal_code');
			$table->integer('country_id')->default(0);
			$table->string('work_phone');
			$table->text('private_notes');
			$table->decimal('balance',13,2);
			$table->decimal('paid_to_date',13,2);

			//$table->dateTime('last_login');
			
			$table->string('website');
			$table->integer('industry_id')->nullable();
			$table->integer('size_id')->nullable();
			$table->tinyInteger('is_deleted')->default(0);
			$table->integer('payment_terms')->nullable();
			$table->integer('public_id')->default(0);
			$table->string('custom_value1')->nullable();
			$table->string('custom_value2')->nullable();
			$table->string('vat_number')->nullable();
			$table->string('id_number')->nullable();
			$table->integer('language_id', false, true)->nullable();	
		});

		// add relations
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('vendors');
	}

}
