<?php

class ConstantsSeeder extends Seeder
{

	public function run()
	{
		DB::table('gateways')->delete();
		
		Gateway::create(array(
			'name' => 'PayPal Express',
			'provider' => 'PayPal_Express'
		));

		/*
		ActivityType::create(array(
			'name' => 'Created invoice'
		));
		*/
	}
}