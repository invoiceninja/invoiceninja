<?php namespace App\Ninja\Transformers;

use App\Models\TaxRate;

/**
 * @SWG\Definition(definition="TaxRate", @SWG\Xml(name="TaxRate"))
 */

class TaxRateTransformer extends EntityTransformer
{
        /**
         * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
         * @SWG\Property(property="name", type="string", example="GST")
         * @SWG\Property(property="account_key", type="string", example="asimplestring", readOnly=true)
         * @SWG\Property(property="rate", type="float", example=17.5)
         * @SWG\Property(property="updated_at", type="date-time", example="2016-01-01 12:10:00")
         * @SWG\Property(property="archived_at", type="date-time", example="2016-01-01 12:10:00")
         */

    public function transform(TaxRate $taxRate)
    {
        return array_merge($this->getDefaults($taxRate), [
            'id' => (int) $taxRate->public_id,
            'name' => $taxRate->name,
            'rate' => (float) $taxRate->rate,
            'updated_at' => $this->getTimestamp($taxRate->updated_at),
            'archived_at' => $this->getTimestamp($taxRate->deleted_at),
        ]);
    }
}