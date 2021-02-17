<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Gmail Configuration
	|--------------------------------------------------------------------------
	|
	|
	|
	|  Scopes Available:
	|
	|   * all - Read, send, delete, and manage your email
	|   * compose - Manage drafts and send emails
	|   * insert - Insert mail into your mailbox
	|   * labels - Manage mailbox labels
	|   * metadata - View your email message metadata such as labels and headers, but not the email body
	|   * modify - View and modify but not delete your email
	|   * readonly - View your email messages and settings
	|   * send - Send email on your behalf
	|   * settings_basic - Manage your basic mail settings
	|   * settings_sharing - Manage your sensitive mail settings, including who can manage your mail
	|
	|   Leaving the scopes empty fill use readonly
	|
	|  Credentials File Name
	|
	*/

	'project_id' => env('GOOGLE_PROJECT_ID'),
	'client_id' => env('GOOGLE_CLIENT_ID'),
	'client_secret' => env('GOOGLE_CLIENT_SECRET'),
	'redirect_url' => env('GOOGLE_REDIRECT_URI', '/'),

	'state' => null,

	'scopes' => [
		'readonly',
		'modify',
	],

	/*
	|--------------------------------------------------------------------------
	| Additional Scopes [URL Style]
	|--------------------------------------------------------------------------
	|
	|   'additional_scopes' => [
	|        'https://www.googleapis.com/auth/drive',
	|        'https://www.googleapis.com/auth/documents'
	|   ],
	|
	|
	*/

	'additional_scopes' => [

	],

	'access_type' => 'offline',

	'approval_prompt' => 'force',

	/*
	|--------------------------------------------------------------------------
	| Credentials File Name
	|--------------------------------------------------------------------------
	|
	|   :email to use, clients email on the file
	|
	|
	*/

	'credentials_file_name' => env('GOOGLE_CREDENTIALS_NAME', 'gmail-json'),

	/*
	|--------------------------------------------------------------------------
	| Allow Multiple Credentials
	|--------------------------------------------------------------------------
	|
	|   Allow the application to store multiple credential json files.
	|
	|
	*/

	'allow_multiple_credentials' => env('GOOGLE_ALLOW_MULTIPLE_CREDENTIALS', false),

	/*
	|--------------------------------------------------------------------------
	| Allow Encryption for json Files
	|--------------------------------------------------------------------------
	|
	|   Use Laravel Encrypt in json Files
	|
	|
	*/

	'allow_json_encrypt' => env('GOOGLE_ALLOW_JSON_ENCRYPT', false),
];
