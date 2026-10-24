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

namespace App\PaymentDrivers\Stripe;

use App\Factory\ClientGatewayTokenFactory;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
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

        $card_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'card',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($card_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::CREDIT_CARD);
        }

        $link_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'link',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($link_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::CREDIT_CARD);
        }

        $alipay_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'alipay',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($alipay_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::ALIPAY);
        }

        $sofort_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'sofort',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($sofort_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::SOFORT);
        }

        $sepa_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'sepa_debit',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($sepa_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::SEPA);
        }

        $this->importBankAccounts($customer, $client);

        $this->importPMBankAccounts($customer, $client);
    }

    public function importPMBankAccounts($customer, $client)
    {
        if ((string) $client->company_id !== (string) $this->stripe->company_gateway->company_id) {
            throw new \InvalidArgumentException('The bank import client must belong to the gateway company.');
        }

        $eligibility = new BankAccountEligibility($customer, $this->stripe->stripe_connect_auth);
        $methods = PaymentMethod::all(
            ['customer' => $customer->id, 'type' => 'us_bank_account', 'limit' => 100],
            $this->stripe->stripe_connect_auth,
        );

        foreach ($methods->autoPagingIterator() as $method) {
            // Verification and webhook flows own existing token states.
            if ($this->bankTokenQuery($method, $customer, $client)->exists()) {
                continue;
            }

            if (!$eligibility->resolve($method)) {
                nlog([
                    'message' => 'Skipping Stripe bank import: eligibility not established.',
                    'company_gateway_id' => $this->stripe->company_gateway->id,
                    'customer_id' => $customer->id,
                    'payment_method_id' => $method->id,
                ]);
                continue;
            }

            $this->storeEligibleBankMethod($method, $customer, $client);
        }
    }

    private function storeEligibleBankMethod(PaymentMethod $method, Customer $customer, Client $client): void
    {
        // Recheck after Stripe lookups in case another flow created the token.
        /** @var ?ClientGatewayToken $token */
        $token = $this->bankTokenQuery($method, $customer, $client)->first();

        if ($token) {
            return;
        }

        $token = ClientGatewayTokenFactory::create($client->company_id);
        $token->client_id = $client->id;
        $token->company_gateway_id = $this->stripe->company_gateway->id;
        $token->gateway_customer_reference = $customer->id;
        $token->token = $method->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->is_default = ClientGatewayToken::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('is_default', true)
            ->doesntExist();

        $meta = is_object($token->meta) ? (array) $token->meta : [];
        unset($meta['next_action']);
        $token->meta = (object) array_merge($meta, [
            'brand' => sprintf('%s (%s)', $method->us_bank_account->bank_name, ctrans('texts.ach')),
            'last4' => (string) $method->us_bank_account->last4,
            'type' => GatewayType::BANK_TRANSFER,
            'state' => 'authorized',
        ]);
        $token->save();
    }

    private function bankTokenQuery(PaymentMethod $method, Customer $customer, Client $client): Builder
    {

        return ClientGatewayToken::query()->where([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'gateway_customer_reference' => $customer->id,
            'token' => $method->id,
        ]);

    }

    public function importBankAccounts($customer, $client)
    {
        if (!$customer) {
            return;
        }

        $sources = Customer::allSources(
            $customer->id,
            ['object' => 'bank_account', 'limit' => 100],
            $this->stripe->stripe_connect_auth,
        );

        // Nested list operations in stripe-php do not retain the initial filters.
        $sources->setFilters(['object' => 'bank_account', 'limit' => 100]);

        foreach ($sources->autoPagingIterator() as $source) {
            // A legacy verification status does not prove a reusable debit mandate.
            nlog([
                'message' => 'Skipping legacy Stripe bank source.',
                'company_gateway_id' => $this->stripe->company_gateway->id,
                'customer_id' => $customer->id,
                'source_id' => $source->id,
                'reason' => $source->status === 'verified'
                    ? 'reusable_authorization_not_established'
                    : 'bank_account_not_verified',
            ]);
        }
    }

    private function addOrUpdateCard(PaymentMethod $method, $customer_reference, Client $client, $type_id)
    {
        $token_exists = ClientGatewayToken::query()->where([
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
        if ($method->card && $method->card->exp_year <= date('Y') && $method->card->exp_month < date('m')) {
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

                if ($method->type == 'link') {
                    return new \stdClass();
                }

                /**
                 * @class \Stripe\PaymentMethod $method
                 * @property \Stripe\StripeObject $card
                 * @class \Stripe\StripeObject $card
                 * @property string|null $exp_year
                 * @property string|null $exp_month
                 * @property string|null $brand
                 * @property string|null $last4
                */

                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) ($method->card->exp_month ?? '00');
                $payment_meta->exp_year = (string) ($method->card->exp_year ?? '0000');
                $payment_meta->brand = (string) ($method->card->brand ?? 'visa');
                $payment_meta->last4 = (string) ($method->card->last4 ?? 'link');
                $payment_meta->type = GatewayType::CREDIT_CARD;

                return $payment_meta;
            case GatewayType::ALIPAY:
            case GatewayType::SOFORT:

                return new \stdClass();

            case GatewayType::SEPA:

                $payment_meta = new \stdClass();
                $payment_meta->brand = (string) \sprintf('%s (%s)', $method->sepa_debit->bank_code, ctrans('texts.sepa'));
                $payment_meta->last4 = (string) $method->sepa_debit->last4;
                $payment_meta->state = 'authorized';
                $payment_meta->type = GatewayType::SEPA;

                return $payment_meta;
            default:

                break;
        }
    }
}
