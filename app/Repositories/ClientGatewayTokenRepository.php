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

namespace App\Repositories;

use App\Models\ClientGatewayToken;

/**
 * Class for ClientGatewayTokenRepository .
 */
class ClientGatewayTokenRepository extends BaseRepository
{
    public function save(array $data, ClientGatewayToken $client_gateway_token) :ClientGatewayToken
    {
        $client_gateway_token->fill($data);
        $client_gateway_token->save();

        return $client_gateway_token->fresh();
    }
}
