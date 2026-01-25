<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
        $casted = new stdClass();

        if ($exp_month = data_get($meta, 'exp_month')) {
            $casted->exp_month = (string) $exp_month;
        }

        if ($exp_year = data_get($meta, 'exp_year')) {
            $casted->exp_year = (string) $exp_year;
        }

        if ($brand = data_get($meta, 'brand')) {
            $casted->brand = (string) $brand;
        }

        if ($last4 = data_get($meta, 'last4')) {
            $casted->last4 = (string) $last4;
        }

        if ($type = data_get($meta, 'type')) {
            $casted->type = (int) $type;
        }

        return $casted;
    }
}
