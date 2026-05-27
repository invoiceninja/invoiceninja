<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
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
            'is_deleted' => (bool) $tax_rate->is_deleted,
            'updated_at' => (int) $tax_rate->updated_at,
            'archived_at' => (int) $tax_rate->deleted_at,
            'created_at' => (int) $tax_rate->created_at,
        ];
    }
}
