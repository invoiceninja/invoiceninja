<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\ClientGatewayToken;
use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * Class ClientGatewayTokenTransformer.
 */
class ClientGatewayTokenTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @param ClientGatewayToken $cgt
     *
     * @return array
     */
    public function transform(ClientGatewayToken $cgt)
    {
        return [
            'id' => $this->encodePrimaryKey($cgt->id),
            'token' => (string) $cgt->token ?: '',
            'gateway_customer_reference' => $cgt->gateway_customer_reference ?: '',
            'gateway_type_id' => (string) $cgt->gateway_type_id ?: '',
            'company_gateway_id' => (string) $this->encodePrimaryKey($cgt->company_gateway_id) ?: '',
            'is_default' => (bool) $cgt->is_default,
            'meta' => $this->typeCastMeta($cgt->meta),
            'created_at' => (int) $cgt->created_at,
            'updated_at' => (int) $cgt->updated_at,
            'archived_at' => (int) $cgt->deleted_at,
            'is_deleted' => (bool) $cgt->is_deleted,
        ];
    }

    private function typeCastMeta($meta)
    {
        $casted = new stdClass;

        if (property_exists($meta, 'exp_month')) {
            $casted->exp_month = (string) $meta->exp_month;
        }

        if (property_exists($meta, 'exp_year')) {
            $casted->exp_year = (string) $meta->exp_year;
        }

        if (property_exists($meta, 'brand')) {
            $casted->brand = (string) $meta->brand;
        }

        if (property_exists($meta, 'last4')) {
            $casted->last4 = (string) $meta->last4;
        }

        if (property_exists($meta, 'type')) {
            $casted->type = (int) $meta->type;
        }

        return $casted;
    }
}
