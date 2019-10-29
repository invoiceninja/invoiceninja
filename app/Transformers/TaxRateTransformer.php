<?php

namespace App\Transformers;

use App\Models\TaxRate;
use App\Utils\Traits\MakesHash;

/**
 * @SWG\Definition(definition="TaxRate", @SWG\Xml(name="TaxRate"))
 */
class TaxRateTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(TaxRate $tax_rate)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($tax_rate->id),
            'name' => (string) $tax_rate->name,
            'rate' => (float) $tax_rate->rate,
            'updated_at' => $tax_rate->updated_at,
            'deleted_at' => $tax_rate->deleted_at,
        ];
    }
}
