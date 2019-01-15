<?php

namespace App\DataMapper;

class UserSettings
{

	private $settings;

	public function __construct($settings)
	{		
		$this->settings = json_decode($settings);
	}

	public function getClient()
	{
		return $this->settings->client;
	}

	public function getColumnVisibility(string $entity) : array
	{
		return $this->settings->{$entity}->datatable->column_visibility[];
	}
}