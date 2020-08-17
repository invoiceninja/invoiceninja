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

namespace App\Factory;

use App\Models\CompanyGateway;

class CompanyGatewayFactory
{
    public static function create(int $company_id, int $user_id) :CompanyGateway
    {
        $company_gateway = new CompanyGateway;
        $company_gateway->company_id = $company_id;
        $company_gateway->user_id = $user_id;
        return $company_gateway;
    }
}
