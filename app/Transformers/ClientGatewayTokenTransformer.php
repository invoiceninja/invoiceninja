<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\ClientGatewayToken;
use App\Utils\Traits\MakesHash;

/**
 * Class ClientGatewayTokenTransformer.
 *
 */
class ClientGatewayTokenTransformer extends EntityTransformer
{
    use MakesHash;
    /**
     * @param ClientGatewayToken $cgt
     *
     * @return array
     *
     */
    public function transform(ClientGatewayToken $cgt)
    {
        return [
            'id' => $this->encodePrimaryKey($cgt->id),
            'token' => (string)$cgt->token ?: '',
            'gateway_customer_reference' => $cgt->gateway_customer_reference ?: '',
            'gateway_type_id' => (string)$cgt->gateway_type_id ?: '',
            'company_gateway_id' => (string)$this->encodePrimaryKey($cgt->company_gateway_id) ?: '',
            'is_default' => (bool) $cgt->is_default,
            'updated_at' => $cgt->updated_at,
            'archived_at' => $cgt->deleted_at,
        ];
    }
}
