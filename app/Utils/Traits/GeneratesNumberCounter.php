<?php

namespace App\Utils\Traits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;

/**
 * Class GeneratesNumberCounter
 * @package App\Utils\Traits
 */
trait GeneratesNumberCounter
{

	public function getNextNumber($entity)
	{

		$counter = $this->getCounter($entity);

	}

	public function hasSharedCounter() : bool
	{

		return $this->getSettingsByKey($shared_invoice_quote_counter)->shared_invoice_quote_counter;

	}

	private function incrementCounter($entity)
	{

	}

	private function entity_name($entity)
	{

		return strtolower(class_basename($entity));
	
	}

	public function getCounter($entity) : int
	{
		$counter = $this->entity_name($entity) . '_number_counter';

		return $this->getSettingsByKey( $counter )->{$counter};

	}

}