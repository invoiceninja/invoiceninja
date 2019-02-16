<?php

namespace App\DataMapper;

/**
 * ClientSettings
 */
class BaseSettings
{
	/**
	 * Migrates properties of the datamapper classes when new properties are added
	 * 
	 * @param  \stdClass $object  Datamapper settings object
	 * @return \stdClass $object  Datamapper settings object updated
	 */
	public function migrate(\stdClass $object) : \stdClass
	{
		$properties = self::default();

		foreach($properties as $property)
		{
			if(!property_exists($object, $property))
				$object->{$property} = NULL;
		}

		return $object;
	}
}