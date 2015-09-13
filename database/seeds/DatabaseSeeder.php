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
        $this->command->info('Seeded the constants'); 

		$this->call('CountriesSeeder');
		$this->command->info('Seeded the countries'); 

		$this->call('PaymentLibrariesSeeder');
		$this->command->info('Seeded the Payment Libraries'); 
	}

}