<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToPaymentterms extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('payment_terms', function(Blueprint $table)
		{
			$table->timestamps();
			$table->softDeletes();
			$table->integer('user_id', false, true);
			$table->integer('account_id', false, true);
			$table->integer('public_id')->default(0);
		});
		
		// Update public id
        $paymentTerms = DB::table('payment_terms')
                    ->where('public_id', '=',0)
                    ->select('id', 'public_id')
                    ->get();
		$i = 1;
        foreach ($paymentTerms as $pTerm) {
            $data = ['public_id' => $i];

            DB::table('paymet_terms')->where('id', $pTerm->id)->update($data);
        }
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('payment_terms', function(Blueprint $table)
		{
			//
		});
	}

}
