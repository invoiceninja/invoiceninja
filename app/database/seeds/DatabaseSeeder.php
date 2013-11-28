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

		$this->call('UserTableSeeder');
		$this->call('ConstantsSeeder');
	}

}