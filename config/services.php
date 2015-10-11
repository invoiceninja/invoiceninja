<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Third Party Services
	|--------------------------------------------------------------------------
	|
	| This file is for storing the credentials for third party services such
	| as Stripe, Mailgun, Mandrill, and others. This file provides a sane
	| default location for this type of information, allowing packages
	| to have a conventional place to find your various credentials.
	|
	*/

    'postmark' => env('POSTMARK_API_TOKEN', ''),

	'mailgun' => [
		'domain' => '',
		'secret' => '',
	],

	'mandrill' => [
		'secret' => '',
	],

	'ses' => [
		'key' => '',
		'secret' => '',
		'region' => 'us-east-1',
	],

	'stripe' => [
		'model'  => 'User',
		'secret' => '',
	],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => 'http://ninja.dev/auth/github'
    ],

    'google' => [
        'client_id' => '640903115046-dd09j2q24lcc3ilrrv5f2ft2i3n0sreg.apps.googleusercontent.com',
        'client_secret' => 'Vsfhldq7mRxsCXQTQI8U_4Ua',
        'redirect' => 'http://ninja.dev/auth/google',
    ],

    'facebook' => [
        'client_id' => '635126583203143',
        'client_secret' => '7aa7c391019f2ece3c6aa90f4c9b1485',
        'redirect' => 'http://ninja.dev/auth/facebook',
    ],

    'linkedin' => [
        'client_id' => '778is2j21w25xj',
        'client_secret' => 'DvDExxfBLXUtxc81',
        'redirect' => 'http://ninja.dev/auth/linkedin',
    ],

];
