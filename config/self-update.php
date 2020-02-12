<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Default source repository type
	|--------------------------------------------------------------------------
	|
	| The default source repository type you want to pull your updates from.
	|
	 */

	'default' => env('SELF_UPDATER_SOURCE', 'github'),

	/*
	|--------------------------------------------------------------------------
	| Version installed
	|--------------------------------------------------------------------------
	|
	| Set this to the version of your software installed on your system.
	|
	 */

	'version_installed' => env('SELF_UPDATER_VERSION_INSTALLED', ''),

	/*
	|--------------------------------------------------------------------------
	| Repository types
	|--------------------------------------------------------------------------
	|
	| A repository can be of different types, which can be specified here.
	| Current options:
	| - github
	| - http
	|
	 */

	'repository_types'       => [
		'github'                => [
			'type'                 => 'github',
			'repository_vendor'    => env('SELF_UPDATER_REPO_VENDOR', ''),
			'repository_name'      => env('SELF_UPDATER_REPO_NAME', ''),
			'repository_url'       => '',
			'download_path'        => env('SELF_UPDATER_DOWNLOAD_PATH', '/tmp'),
			'private_access_token' => env('SELF_UPDATER_GITHUB_PRIVATE_ACCESS_TOKEN', ''),
			'use_branch'           => env('SELF_UPDATER_BRANCH_NAME', 'v2'),

		],
		'http'                  => [
			'type'                 => 'http',
			'repository_url'       => env('SELF_UPDATER_REPO_URL', ''),
			'pkg_filename_format'  => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
			'download_path'        => env('SELF_UPDATER_DOWNLOAD_PATH', '/tmp'),
			'private_access_token' => env('SELF_UPDATER_HTTP_PRIVATE_ACCESS_TOKEN', ''),
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Exclude folders from update
	|--------------------------------------------------------------------------
	|
	| Specifiy folders which should not be updated and will be skipped during the
	| update process.
	|
	| Here's already a list of good examples to skip. You may want to keep those.
	|
	 */

	'exclude_folders' => [
		'node_modules',
		'bootstrap/cache',
		'bower',
		'storage/app',
		'storage/framework',
		'storage/logs',
		'storage/self-update',
		'vendor',
	],

	/*
	|--------------------------------------------------------------------------
	| Event Logging
	|--------------------------------------------------------------------------
	|
	| Configure if fired events should be logged
	|
	 */

	'log_events' => env('SELF_UPDATER_LOG_EVENTS', false),

	/*
	|--------------------------------------------------------------------------
	| Mail To Settings
	|--------------------------------------------------------------------------
	|
	| Configure if fired events should be logged
	|
	 */

	'mail_to'                   => [
		'address'                  => env('SELF_UPDATER_MAILTO_ADDRESS', ''),
		'name'                     => env('SELF_UPDATER_MAILTO_NAME', ''),
		'subject_update_available' => env('SELF_UPDATER_MAILTO_UPDATE_AVAILABLE_SUBJECT', 'Update available'),
		'subject_update_succeeded' => env('SELF_UPDATER_MAILTO_UPDATE_SUCCEEDED_SUBJECT', 'Update succeeded'),
	],

	/*
	|---------------------------------------------------------------------------
	| Register custom artisan commands
	|---------------------------------------------------------------------------
	 */

	'artisan_commands' => [
		'pre_update'      => [
			//'command:signature' => [
			//    'class' => Command class
			//    'params' => []
			//]
		],
		'post_update' => [
	        'postupdate:cleanup' => [
            	'class' => \App\Console\Commands\ArtisanUpgrade::class,
			],
		],
	],

];
