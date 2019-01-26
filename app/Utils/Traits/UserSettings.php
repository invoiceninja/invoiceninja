<?php

namespace App\Utils\Traits;

/**
 * Class UserSettings
 * @package App\Utils\Traits
 */
trait UserSettings
{

	/**
	 * @param string $entity
	 * @return \stdClass
     */
	public function getEntity(string $entity) : \stdClass
	{
		return $this->settings()->{$entity};
	}

	/**
	 * @param string $entity
	 * @return \stdClass
     */
	public function getColumnVisibility(string $entity) : \stdClass
	{
		return $this->settings()->{class_basename($entity)}->datatable->column_visibility;
	}
}