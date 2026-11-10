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

namespace App\PaymentDrivers\GoCardless\Jobs;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Country;
use App\Models\GatewayType;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use App\Utils\Traits\GeneratesCounter;
use GoCardlessPro\Resources\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ImportGoCardlessCustomers implements ShouldQueue
{
    use Dispatchable;
    use GeneratesCounter;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    private GoCardlessPaymentDriver $gocardless;

    private bool $completed = true;

    public function __construct(public string $company_key, public int $company_gateway_id)
    {
    }

    public function handle(): void
    {
        if (! MultiDB::findAndSetDbByCompanyKey($this->company_key)) {
            return;
        }

        $company_gateway = CompanyGateway::query()
            ->where('is_deleted', 0)
            ->where('gateway_key', 'b9886f9257f0c6ee7c302f1c74475f6c')
            ->whereHas('company', fn ($query) => $query->where('company_key', $this->company_key))
            ->find($this->company_gateway_id);

        if (! $company_gateway) {
            return;
        }

        $this->gocardless = $company_gateway->driver();
        $this->gocardless->init();

        /** @var \GoCardlessPro\Resources\Customer[] $customers */
        $customers = $this->gocardless->gateway->customers()->all();

        foreach ($customers as $customer) {
            $this->addCustomer($customer);
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("import_gocardless_customers:{$this->company_key}:{$this->company_gateway_id}"))
                ->expireAfter($this->timeout + 60)
                ->dontRelease(),
        ];
    }

    public function failed($exception): void
    {
        nlog('GoCardless import customer methods exception');
        nlog($exception->getMessage());
    }

    private function addCustomer(Customer $customer): void
    {
        $existing_customer_token = $this->gocardless->company_gateway
            ->client_gateway_tokens()
            ->where('gateway_customer_reference', $customer->id)
            ->first();

        if ($existing_customer_token) {
            nlog("Skipping - Customer exists: {$customer->email} just updating payment methods");
            $this->updatePaymentMethods($customer, $existing_customer_token->client);
        } elseif ($customer->email && $this->gocardless->company_gateway->company->client_contacts()->where('email', $customer->email)->exists()) {
            nlog("Customer exists: {$customer->email} just updating payment methods");

            $this->gocardless->company_gateway->company->client_contacts()->where('email', $customer->email)->each(function ($contact) use ($customer) {
                $this->updatePaymentMethods($customer, $contact->client);
            });
        } else {
            nlog("Creating new customer: {$customer->email}");
            $client = $this->createNinjaClient($customer);
            $this->updatePaymentMethods($customer, $client);
        }
    }

    private function updatePaymentMethods(Customer $customer, Client $client): void
    {
        $this->gocardless->client = $client;

        $mandates = $this->gocardless->gateway->mandates()->all([
            'params' => [
                'customer' => $customer->id,
            ],
        ]);

        /** @var \GoCardlessPro\Resources\Mandate $mandate */
        foreach ($mandates as $mandate) {
            if (! in_array($mandate->status, ['active', 'pending_submission']) || ClientGatewayToken::where('token', $mandate->id)->where('gateway_customer_reference', $customer->id)->exists()) {
                continue;
            }

            $type = match ($mandate->scheme) {
                'bacs' => GatewayType::DIRECT_DEBIT,
                'sepa_core' => GatewayType::SEPA,
                'ach' => GatewayType::BANK_TRANSFER,
                default => null,
            };

            if (! $type) {
                continue;
            }

            try {
                $bank_account = $this->gocardless->gateway->customerBankAccounts()->get($mandate->links->customer_bank_account);
            } catch (\Throwable $e) {
                nlog('Error getting bank account: ' . $e->getMessage());
                continue;
            }

            $payment_meta = new \stdClass();
            $payment_meta->last4 = $bank_account->account_holder_name . ' - ' . $bank_account->account_number_ending;
            $payment_meta->brand = $bank_account->bank_name;
            $payment_meta->state = 'authorized';
            $payment_meta->scheme = $mandate->scheme;
            $payment_meta->type = $type;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $mandate->id,
                'payment_method_id' => $payment_meta->type,
            ];

            $this->gocardless->storeGatewayToken($data, ['gateway_customer_reference' => $mandate->links->customer]);
        }
    }

    private function createNinjaClient(Customer $customer): Client
    {
        $client = ClientFactory::create($this->gocardless->company_gateway->company_id, $this->gocardless->company_gateway->user_id);

        $client->address1 = $customer->address_line1 ?: '';
        $client->address2 = $customer->address_line2 ?: '';
        $client->city = $customer->city ?: '';
        $client->state = $customer->region ?: '';
        $client->postal_code = $customer->postal_code ?: '';
        $client->phone = $customer->phone_number ? $customer->phone_number : '';
        $client->name = $customer->company_name;

        if ($customer->country_code) {
            $country = Country::where('iso_3166_2', $customer->country_code)->first();

            if ($country) {
                $client->country_id = $country->id;
            } else {
                $client->country_id = $this->gocardless->company_gateway->company->settings->country_id;
            }
        }

        $settings = $client->settings;
        $settings->currency_id = (string) $this->gocardless->company_gateway->company->settings->currency_id;
        $client->settings = $settings;
        $client->save();

        $contact = ClientContactFactory::create($this->gocardless->company_gateway->company_id, $this->gocardless->company_gateway->user_id);
        $contact->first_name = $customer->given_name ?: '';
        $contact->last_name = $customer->family_name ?: '';
        $contact->email = $customer->email ?: '';
        $contact->client_id = $client->id;
        $contact->saveQuietly();

        if (! isset($client->number) || empty($client->number)) {
            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        } else {
            $client->saveQuietly();
        }

        return $client;
    }
}
