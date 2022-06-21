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

namespace App\PaymentDrivers\Stripe;

use App\Factory\ClientGatewayTokenFactory;
use App\Models\Client;
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

    public function updateMethods(Customer $customer, Client $client)
    {
        $this->stripe->client = $client;

        $card_methods = PaymentMethod::all([
            'customer' => $customer->id,
            'type' => 'card',
        ],
                     $this->stripe->stripe_connect_auth);

        foreach ($card_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::CREDIT_CARD);
        }

        $alipay_methods = PaymentMethod::all([
            'customer' => $customer->id,
            'type' => 'alipay',
        ],
                     $this->stripe->stripe_connect_auth);

        foreach ($alipay_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::ALIPAY);
        }

        $sofort_methods = PaymentMethod::all([
            'customer' => $customer->id,
            'type' => 'sofort',
        ],
                     $this->stripe->stripe_connect_auth);

        foreach ($sofort_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::SOFORT);
        }

        $this->importBankAccounts($customer, $client);
    }

    private function importBankAccounts($customer, $client)
    {
        $sources = $customer->sources;

        foreach ($sources->data as $method) {
            $token_exists = ClientGatewayToken::where([
                'gateway_customer_reference' => $customer->id,
                'token' => $method->id,
                'client_id' => $client->id,
                'company_id' => $client->company_id,
            ])->exists();

            /* Already exists return */
            if ($token_exists) {
                continue;
            }

            $payment_meta = new \stdClass;
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->bank_name, ctrans('texts.ach'));
            $payment_meta->last4 = (string) $method->last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->state = $method->status;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $additional_data = ['gateway_customer_reference' => $customer->id];

            if ($customer->default_source === $method->id) {
                $additional_data = ['gateway_customer_reference' => $customer->id, 'is_default' => 1];
            }

            $this->stripe->storeGatewayToken($data, $additional_data);
        }
    }

    private function addOrUpdateCard(PaymentMethod $method, $customer_reference, Client $client, $type_id)
    {
        $token_exists = ClientGatewayToken::where([
            'gateway_customer_reference' => $customer_reference,
            'token' => $method->id,
            'client_id' => $client->id,
            'company_id' => $client->company_id,
        ])->exists();

        /* Already exists return */
        if ($token_exists) {
            return;
        }

        /* Ignore Expired cards */
        if ($method->card->exp_year <= date('Y') && $method->card->exp_month < date('m')) {
            return;
        }

        $cgt = ClientGatewayTokenFactory::create($client->company_id);
        $cgt->client_id = $client->id;
        $cgt->token = $method->id;
        $cgt->gateway_customer_reference = $customer_reference;
        $cgt->company_gateway_id = $this->stripe->company_gateway->id;
        $cgt->gateway_type_id = $type_id;
        $cgt->meta = $this->buildPaymentMethodMeta($method, $type_id);
        $cgt->save();
    }

    private function buildPaymentMethodMeta(PaymentMethod $method, $type_id)
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
