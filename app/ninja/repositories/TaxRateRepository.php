<?php namespace ninja\repositories;

use TaxRate;

class TaxRateRepository
{
	public function save($taxRates)
	{
		$taxRateIds = [];		
		
		foreach ($taxRates as $record)
		{	
			if (!isset($record->rate) || $record->is_deleted)
			{
				continue;
			}

			if (!floatval($record->rate) || !trim($record->name))
			{
				continue;
			}

			if ($record->public_id)
			{
				$taxRate = TaxRate::scope($record->public_id)->firstOrFail();
			}
			else
			{
				$taxRate = TaxRate::createNew();
			}

			$taxRate->rate = floatval($record->rate);
			$taxRate->name = trim($record->name);
			$taxRate->save();				

			$taxRateIds[] = $taxRate->public_id;
		}		
		
		$taxRates = TaxRate::scope()->get();

		foreach($taxRates as $taxRate)
		{
			if (!in_array($taxRate->public_id, $taxRateIds))
			{
				$taxRate->delete();
			}
		}
	}
}