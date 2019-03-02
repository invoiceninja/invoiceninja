<?php

namespace App\DataMapper;

/**
 * ClientSettings
 */
class BaseSettings
{

	public function __construct($obj)
	{
		foreach($obj as $key => $value)
			$this->{$key} = $value;
	}
	

}