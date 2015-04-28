<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSvLanguage extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        DB::table('languages')->insert(['name' => 'Swedish', 'locale' => 'sv']);
        DB::table('languages')->insert(['name' => 'Spanish - Spain', 'locale' => 'es_ES']);
        DB::table('languages')->insert(['name' => 'French - Canada', 'locale' => 'fr_CA']);

        DB::table('payment_terms')->insert(['num_days' => -1, 'name' => 'Net 0']);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        if ($language = \App\Models\Language::whereLocale('sv')->first()) {
            $language->delete();
        }

        if ($language = \App\Models\Language::whereLocale('es_ES')->first()) {
            $language->delete();
        }

        if ($language = \App\Models\Language::whereLocale('fr_CA')->first()) {
            $language->delete();
        }

        if ($paymentTerm = \App\Models\PaymentTerm::whereName('Net 0')->first()) {
            $paymentTerm->delete();
        }
	}

}
