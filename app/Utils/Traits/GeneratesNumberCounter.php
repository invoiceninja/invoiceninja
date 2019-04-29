<?php

namespace App\Utils\Traits;

/**
 * Class GeneratesNumberCounter
 * @package App\Utils\Traits
 */
trait GeneratesNumberCounter
{

	public function getNextNumber($entity)
	{

	}

	public function hasSharedCounter() : bool
	{

		return $this->getSettingsByKey($shared_invoice_quote_counter)->shared_invoice_quote_counter;

	}

	public function incrementCounter($entity)
	{

	}

	public function entity_name($entity)
	{

		return strtolower(class_basename($entity));
	
	}

}