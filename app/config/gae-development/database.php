<?php

return array(

	'connections' => array(

		'mysql' => array(
	        'driver'    => 'mysql',
	        'host'      => getenv('DEVELOPMENT_DB_HOST'),
	        'database'  => getenv('DEVELOPMENT_DB_NAME'),
	        'username'  => getenv('DEVELOPMENT_DB_USERNAME'),
	        'password'  => getenv('DEVELOPMENT_DB_PASSWORD'),
	        'charset'   => 'utf8',
	        'collation' => 'utf8_unicode_ci',
	        'prefix'    => '',
		),
	),
);
