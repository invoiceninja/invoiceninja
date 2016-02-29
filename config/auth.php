<?php

return [
	'driver' => 'eloquent',
	'model' => 'App\Models\User',
	'table' => 'users',
	'password' => [
		'email' => 'emails.password',
		'table' => 'password_resets',
		'expire' => 60,
	]	
];
