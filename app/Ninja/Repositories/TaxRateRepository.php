<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\TaxRate;

class TaxRateRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\TaxRate';
    }

    public function find($accountId)
    {
        return DB::table('tax_rates')
                ->where('tax_rates.account_id', '=', $accountId)
                ->where('tax_rates.deleted_at', '=', null)
                ->select('tax_rates.public_id', 'tax_rates.name', 'tax_rates.rate', 'tax_rates.deleted_at');
    }

    public function save($data, $taxRate = null)
    {
        if ($taxRate) {
            // do nothing
        } elseif (isset($data['public_id'])) {
            $taxRate = TaxRate::scope($data['public_id'])->firstOrFail();
            \Log::warning('Entity not set in tax rate repo save');
        } else {
            $taxRate = TaxRate::createNew();
        }
        
        $taxRate->fill($data);
        $taxRate->save();

        return $taxRate;
    }
}
