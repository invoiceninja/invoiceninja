<?php

class DatabaseSeeder extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->command->info('Running DatabaseSeeder');

		Eloquent::unguard();

		$this->call('ConstantsSeeder');
		$this->call('CountriesSeeder');
		$this->call('PaymentLibrariesSeeder');
	}

}