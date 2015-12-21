<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\TaxRate;
use League\Fractal;

/**
 * @SWG\Definition(definition="TaxRate", @SWG\Xml(name="TaxRate"))
 */

class TaxRateTransformer extends EntityTransformer
{
        /**
         * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
         * @SWG\Property(property="name", type="string", example="GST")
         * @SWG\Property(property="account_key", type="string", example="34erfdf33fdff" readOnly=true)
         * @SWG\Property(property="rate", type="float", example=17.5)
         * @SWG\Property(property="updated_at", type="date-time", example="2016-01-01 12:10:00")
         * @SWG\Property(property="archived_at", type="date-time", example="2016-01-01 12:10:00")
         */

    public function transform(TaxRate $taxRate)
    {
        return [
            'id' => (int) $taxRate->public_id,
            'name' => $taxRate->name,
            'rate' => (float) $taxRate->rate,
            'updated_at' => $taxRate->updated_at,
            'archived_at' => $taxRate->deleted_at,
            'account_key' => $this->account->account_key,
            ];
    }
}