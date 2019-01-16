<?php

namespace App\Utils\Traits;

trait UserSettings
{

	public function getEntity(string $entity) : \stdClass
	{
		return $this->settings()->{$entity};
	}

	public function getColumnVisibility(string $entity) : \stdClass
	{
		return $this->settings()->{class_basename($entity)}->datatable->column_visibility;
	}
}