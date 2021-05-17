<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Stripe;

use App\Factory\ClientGatewayTokenFactory;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Stripe\Customer;
use Stripe\PaymentMethod;

class UpdatePaymentMethods
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function run()
    {
        $this->stripe->init();

        $this->stripe
             ->company_gateway
             ->client_gateway_tokens
             ->each(function ($token){

                $card_methods = PaymentMethod::all([
                    'customer' => $token->gateway_customer_reference,
                    'type' => 'card',
                    ],
                     $this->stripe->stripe_connect_auth);

                foreach($card_methods as $method) 
                {
                    $this->addOrUpdateCard($method, $token, GatewayType::CREDIT_CARD);
                }

                $alipay_methods = PaymentMethod::all([
                    'customer' => $token->gateway_customer_reference,
                    'type' => 'alipay',
                    ],
                     $this->stripe->stripe_connect_auth);

                foreach($alipay_methods as $method) 
                {
                    $this->addOrUpdateCard($method, $token, GatewayType::ALIPAY);
                }

                $sofort_methods = PaymentMethod::all([
                    'customer' => $token->gateway_customer_reference,
                    'type' => 'sofort',
                    ],
                     $this->stripe->stripe_connect_auth);

                foreach($alipay_methods as $method) 
                {
                    $this->addOrUpdateCard($method, $token, GatewayType::SOFORT);
                }

                $bank_accounts = Customer::allSources(
                    $token->gateway_customer_reference,
                    ['object' => 'bank_account', 'limit' => 300]
                );

                foreach($bank_accounts as $bank_account)
                {
                    $this->addOrUpdateBankAccount($bank_account, $token);
                }

        });

    }

    private function addOrUpdateBankAccount($bank_account, ClientGatewayToken $token)
    {
        $token_exists = ClientGatewayToken::where([
            'gateway_customer_reference' => $token->gateway_customer_reference,
            'token' => $bank_account->id,
        ])->exists();

        /* Already exists return */
        if($token_exists)
            return;

        $cgt = ClientGatewayTokenFactory::create($token->company_id);
        $cgt->client_id = $token->client_id;
        $cgt->token = $bank_account->id;
        $cgt->gateway_customer_reference = $token->gateway_customer_reference;
        $cgt->company_gateway_id = $token->company_gateway_id;
        $cgt->gateway_type_id = GatewayType::BANK_TRANSFER;
        $cgt->meta = new \stdClass;
        $cgt->routing_number = $bank_account->routing_number;
        $cgt->save();

    }

    private function addOrUpdateCard(PaymentMethod $method, ClientGatewayToken $token, GatewayType $type_id)
    {
        
        $token_exists = ClientGatewayToken::where([
            'gateway_customer_reference' => $token->gateway_customer_reference,
            'token' => $method->id,
        ])->exists();

        /* Already exists return */
        if($token_exists)
            return;

        /* Ignore Expired cards */
        if($method->card->exp_year <= date('Y') && $method->card->exp_month < date('m'))
            return;

        $cgt = ClientGatewayTokenFactory::create($token->company_id);
        $cgt->client_id = $token->client_id;
        $cgt->token = $method->id;
        $cgt->gateway_customer_reference = $token->gateway_customer_reference;
        $cgt->company_gateway_id = $token->company_gateway_id;
        $cgt->gateway_type_id = $type_id;
        $cgt->meta = $this->buildPaymentMethodMeta($method, $type_id);
        $cgt->save();

    }

    private function buildPaymentMethodMeta(PaymentMethod $method, GatewayType $type_id)
    {

        switch ($type_id) {
            case GatewayType::CREDIT_CARD:

                $payment_meta = new \stdClass;
                $payment_meta->exp_month = (string) $method->card->exp_month;
                $payment_meta->exp_year = (string) $method->card->exp_year;
                $payment_meta->brand = (string) $method->card->brand;
                $payment_meta->last4 = (string) $method->card->last4;
                $payment_meta->type = GatewayType::CREDIT_CARD;
                return $payment_meta;

                break;
            
            case GatewayType::ALIPAY:
            case GatewayType::SOFORT:

                return new \stdClass;

            default:
                

                break;
        }
    }
}
