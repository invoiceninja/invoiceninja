<?php

namespace App\Utils;

class Default
{

	const PER_PAGE = 20;

	public static function userSettings()
	{
		return (object)[
	        'client' => self::clientSettings,
	    ];
	}

	private static function clientSettings()
	{
		
		return (object)[
			'datatable' (object) [
				'per_page' => PER_PAGE,
				'column_visibility' => (object)[
					'__checkbox' => true,
	    			'name' => true,
	    			'contact' => true,
	    			'email' => true,
	    			'client_created_at' => true,
	    			'last_login' => true,
	    			'balance' => true,
	    			'__component:client-actions' => true,
				]
			]
		];

	}
}