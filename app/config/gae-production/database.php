<?php

return array(

	'connections' => array(

		'mysql' => array(
	        'driver'    => 'mysql',
	        'unix_socket' => getenv('PRODUCTION_CLOUD_SQL_INSTANCE'),
	        'host'      => '',
	        'database'  => getenv('PRODUCTION_DB_NAME'),
	        'username'  => getenv('PRODUCTION_DB_USERNAME'),
	        'password'  => getenv('PRODUCTION_DB_PASSWORD'),
	        'charset'   => 'utf8',
	        'collation' => 'utf8_unicode_ci',
	        'prefix'    => '',
		),
	),
);
