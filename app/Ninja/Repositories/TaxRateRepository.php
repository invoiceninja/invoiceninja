<?php namespace App\Ninja\Repositories;

use App\Models\TaxRate;
use Utils;

class TaxRateRepository
{
    public function save($taxRates)
    {
        $taxRateIds = [];

        foreach ($taxRates as $record) {
            if (!isset($record->rate) || (isset($record->is_deleted) && $record->is_deleted)) {
                continue;
            }

            if (!isset($record->name) || !trim($record->name)) {
                continue;
            }

            if ($record->public_id) {
                $taxRate = TaxRate::scope($record->public_id)->firstOrFail();
            } else {
                $taxRate = TaxRate::createNew();
            }

            $taxRate->rate = Utils::parseFloat($record->rate);
            $taxRate->name = trim($record->name);
            $taxRate->save();

            $taxRateIds[] = $taxRate->public_id;
        }

        $taxRates = TaxRate::scope()->get();

        foreach ($taxRates as $taxRate) {
            if (!in_array($taxRate->public_id, $taxRateIds)) {
                $taxRate->delete();
            }
        }
    }
}
