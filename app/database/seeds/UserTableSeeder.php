<?php

class UserTableSeeder extends Seeder
{

	public function run()
	{
		DB::table('users')->delete();
		/*
		User::create(array(
			'first_name'     => 'Hillel',
			'last_name' => 'Coren',
			'email'    => 'hillelcoren@gmail.com',
			'password' => Hash::make('1234'),
		));
		*/
	}

}