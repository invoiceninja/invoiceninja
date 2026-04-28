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

namespace App\PaymentDrivers\Forte;

use App\Factory\ClientFactory;
use App\Factory\ClientGatewayTokenFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\Factory\ForteCustomerFactory;
use App\PaymentDrivers\FortePaymentDriver;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;

class ImportCustomers
{
    public function __construct(public FortePaymentDriver $forte)
    {
    }

    /**
     * Pulls every customer (with inlined paymethods) from the configured Forte
     * organization/location, finds or creates the corresponding Ninja client,
     * then persists each Forte paymethod as a ClientGatewayToken.
     */
    public function run(): void
    {
        $response = $this->forte
            ->stubRequest()
            ->withQueryParameters(['page_size' => 10000, 'include' => 'paymethods,addresses'])
            ->get("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/customers");

        if (! $response->successful()) {
            return;
        }

        foreach ($response->json('results') ?? [] as $customer) {
            $this->addCustomer($customer);
        }
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    public function addCustomer(array $customer): void
    {
        $factory = new ForteCustomerFactory();
        $data = $factory->convertToNinja($customer, $this->forte->company_gateway->company);

        $email = $data['email'] ?? '';

        if (strlen($email) === 0) {
            return;
        }

        $client = $this->resolveClient($email, $data);

        if (! $client) {
            return;
        }

        $this->importPaymethods($customer, $client);
    }

    /**
     * Returns an existing Ninja client matching the given contact email, or
     * creates a new one using the converted Forte payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(string $email, array $data): ?Client
    {
        $existing_contact = ClientContact::query()
            ->where('company_id', $this->forte->company_gateway->company_id)
            ->where('email', $email)
            ->first();

        if ($existing_contact) {
            return $existing_contact->client;
        }

        $client_repo = new ClientRepository(new ClientContactRepository());

        return $client_repo->save(
            $data,
            ClientFactory::create(
                $this->forte->company_gateway->company_id,
                $this->forte->company_gateway->user_id,
            ),
        );
    }

    /**
     * Persists each Forte paymethod attached to $customer as a ClientGatewayToken
     * on the supplied Ninja client. Existing tokens are left untouched (idempotent).
     *
     * @param  array<string, mixed>  $customer
     */
    private function importPaymethods(array $customer, Client $client): void
    {
        $customer_token = $customer['customer_token'] ?? null;
        $paymethods = $customer['paymethods'] ?? [];

        if (! $customer_token || ! is_array($paymethods)) {
            return;
        }

        foreach ($paymethods as $paymethod) {
            if (! is_array($paymethod) || empty($paymethod['paymethod_token'])) {
                continue;
            }

            $type_id = $this->resolveGatewayType($paymethod);

            if ($type_id === null) {
                continue;
            }

            $token_exists = ClientGatewayToken::query()
                ->where('company_id', $client->company_id)
                ->where('client_id', $client->id)
                ->where('gateway_customer_reference', $customer_token)
                ->where('token', $paymethod['paymethod_token'])
                ->exists();

            if ($token_exists) {
                continue;
            }

            $cgt = ClientGatewayTokenFactory::create($client->company_id);
            $cgt->client_id = $client->id;
            $cgt->company_gateway_id = $this->forte->company_gateway->id;
            $cgt->gateway_customer_reference = $customer_token;
            $cgt->token = $paymethod['paymethod_token'];
            $cgt->gateway_type_id = $type_id;
            $cgt->meta = $this->buildPaymentMeta($paymethod, $type_id);
            $cgt->save();
        }
    }

    /**
     * @param  array<string, mixed>  $paymethod
     */
    private function resolveGatewayType(array $paymethod): ?int
    {
        if (! empty($paymethod['card']) && is_array($paymethod['card'])) {
            return GatewayType::CREDIT_CARD;
        }

        if (! empty($paymethod['echeck']) && is_array($paymethod['echeck'])) {
            return GatewayType::BANK_TRANSFER;
        }

        return null;
    }

    /**
     * Mirrors the meta shape produced by the manual paymethod-creation paths in
     * Forte/CreditCard.php and Forte/ACH.php so token billing can read identical
     * fields regardless of how the token was acquired.
     *
     * @param  array<string, mixed>  $paymethod
     */
    private function buildPaymentMeta(array $paymethod, int $type_id): \stdClass
    {
        $meta = new \stdClass();
        $meta->exp_month = '';
        $meta->exp_year = '';
        $meta->brand = '';
        $meta->last4 = '';
        $meta->type = $type_id;

        if ($type_id === GatewayType::CREDIT_CARD) {
            $card = $paymethod['card'] ?? [];

            $meta->exp_month = (string) ($card['expire_month'] ?? '');
            $meta->exp_year = (string) ($card['expire_year'] ?? '');
            $meta->brand = (string) ($card['card_type'] ?? '');
            $meta->last4 = (string) ($card['last_4_account_number'] ?? '');
        }

        if ($type_id === GatewayType::BANK_TRANSFER) {
            $echeck = $paymethod['echeck'] ?? [];

            $meta->brand = 'ACH';
            $meta->last4 = (string) ($echeck['last_4_account_number'] ?? '');
        }

        return $meta;
    }
}
