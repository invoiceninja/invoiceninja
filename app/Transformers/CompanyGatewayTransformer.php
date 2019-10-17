<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\CompanyGateway;
use App\Transformers\GatewayTransformer;
use App\Utils\Traits\MakesHash;

/**
 * Class CompanyGatewayTransformer.
 */
class CompanyGatewayTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'gateway'
    ];


    /**
     * @param CompanyGateway $company_gateway
     *
     * @return array
     */
    public function transform(CompanyGateway $company_gateway)
    {
        return [
            'id' => (string)$this->encodePrimaryKey($company_gateway->id),
            'gateway_key' => (string)$company_gateway->gateway_key ?: '',
            'accepted_credit_cards' => (int)$company_gateway->accepted_credit_cards,
            'require_cvv' => (bool)$company_gateway->require_cvv,
            'show_billing_address' => (bool)$company_gateway->show_billing_address,
            'show_shipping_address' => (bool)$company_gateway->show_shipping_address,
            'update_details' => (bool)$company_gateway->update_details,
            'config' => (string) $company_gateway->getConfigTransformed(),
            'priority_id' => (int)$company_gateway->priority_id,
            'min_limit' => (float)$company_gateway->min_limit,
            'max_limit' => (float)$company_gateway->max_limit,
            'fee_amount' => (float) $company_gateway->fee_amount,
            'fee_percent' => (float)$company_gateway->fee_percent ?: '',
            'fee_tax_name1' => (string)$company_gateway->fee_tax_name1 ?: '',
            'fee_tax_name2' => (string) $company_gateway->fee_tax_name2 ?: '',
            'fee_tax_name3' => (string) $company_gateway->fee_tax_name3 ?: '',
            'fee_tax_rate1' => (float) $company_gateway->fee_tax_rate1,
            'fee_tax_rate2' => (float)$company_gateway->fee_tax_rate2,
            'fee_tax_rate3' => (float)$company_gateway->fee_tax_rate3,
            'fee_cap' => (float)$company_gateway->fee_cap,
            'adjust_fee_percent' => (bool)$company_gateway->adjust_fee_percent,
            'updated_at' => $company_gateway->updated_at,
            'deleted_at' => $company_gateway->deleted_at,
        ];
    }

    public function includeGateway(CompanyGateway $company_gateway)
    {
        $transformer = new GatewayTransformer($this->serializer);

        return $this->includeItem($company_gateway->gateway, $transformer, Gateway::class);
    }

}
