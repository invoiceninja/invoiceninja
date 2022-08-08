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

namespace App\Factory;

use App\Models\ClientGatewayToken;
use Illuminate\Support\Str;

class ClientGatewayTokenFactory
{
    public static function create(int $company_id) :ClientGatewayToken
    {
        $client_gateway_token = new ClientGatewayToken;
        $client_gateway_token->company_id = $company_id;
        $client_gateway_token->is_default = false;
        $client_gateway_token->meta = '';
        $client_gateway_token->is_deleted = false;
        $client_gateway_token->token = '';
        $client_gateway_token->routing_number = '';

        return $client_gateway_token;
    }
}
