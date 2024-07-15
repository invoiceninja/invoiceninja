<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use Omnipay\Omnipay;
use App\Models\Client;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\PaymentHash;
use Illuminate\Support\Arr;
use App\Models\GatewayType;
use Omnipay\Rotessa\Gateway;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\BaseDriver;
use App\Models\ClientGatewayToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use App\PaymentDrivers\Rotessa\Resources\Customer;
use App\PaymentDrivers\Rotessa\PaymentMethod as Acss;
use App\PaymentDrivers\Rotessa\PaymentMethod as BankTransfer;

class RotessaPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public Gateway $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::BANK_TRANSFER => BankTransfer::class,
        //GatewayType::BACS => Bacs::class,
        GatewayType::ACSS => Acss::class,
        // GatewayType::DIRECT_DEBIT => DirectDebit::class
    ];

    public function init(): self
    {
       
        $this->gateway = Omnipay::create(
            $this->company_gateway->gateway->provider
        );
        $this->gateway->initialize((array) $this->company_gateway->getConfig());
        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        if ($this->client
        && $this->client->currency()
        && in_array($this->client->currency()->code, ['USD'])
        && isset($this->client->country)
        && in_array($this->client->country->iso_3166_2, ['US'])) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['CAD'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_2, ['CA'])) {
            $types[] = GatewayType::ACSS;
        }
        
        return $types;
    }

    
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }
    
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function importCustomers() {
        $this->init();
        try {
            if(!$result = Cache::has('rotessa-import_customers')) {
                $result = $this->gateway->getCustomers()->send();
                if(!$result->isSuccessful()) throw new \Exception($result->getMessage(), (int) $result->getCode());
                // cache results
                Cache::put('rotessa-import_customers', $result->getData(), 60 * 60 * 24);
            }
           
            $result = Cache::get('rotessa-import_customers');
            $customers = collect($result)->unique('email');
            $client_emails = $customers->pluck('email')->all();
            $company_id = $this->company_gateway->company->id;
            // get existing customers
            $client_contacts = ClientContact::where('company_id', $company_id)->whereIn('email', $client_emails )->whereNull('deleted_at')->get();
            $client_contacts = $client_contacts->map(function($item, $key) use ($customers) {
                return  array_merge([], (array) $customers->firstWhere("email", $item->email) , ['custom_identifier' => $item->client->number, 'identifier' => $item->client->number, 'client_id' => $item->client->id ]);
            }  );
            // create payment methods
            $client_contacts->each(
                function($contact) use ($customers) {
                    sleep(10);
                    $result = $this->gateway->getCustomersId(['id' => ($contact = (object) $contact)->id])->send();
                    $this->client = Client::find($contact->client_id);
                    $customer = (new Customer($result->getData()))->additional(['id' => $contact->id, 'custom_identifier' => $contact->custom_identifier ] );
                    $this->findOrCreateCustomer($customer->additional + $customer->jsonSerialize());
                }
            );
            
            // create new clients from rotessa customers
            $client_emails = $client_contacts->pluck('email')->all();
            $client_contacts = $customers->filter(function ($value, $key) use ($client_emails) {
                return !in_array(((object) $value)->email, $client_emails);
            })->each( function($customer) use ($company_id) {
                sleep(10);
                // create new client contact from rotess customer
                $customer = (object) $this->gateway->getCustomersId(['id' => ($customer = (object) $customer)->id])->send()->getData();
                /**
                    {
                        "account_number": "11111111"
                        "active": true,
                        "address": {
                            "address_1": "123 Main Street",
                            "address_2": "Unit 4",
                            "city": "Birmingham",
                            "id": 114397,
                            "postal_code": "36016",
                            "province_code": "AL"
                        },
                        "authorization_type": "Online",
                        "bank_account_type": "Checking",
                        "bank_name": "Scotiabank",
                        "created_at": "2015-02-10T23:50:45.000-06:00",
                        "custom_identifier": "Mikey",
                        "customer_type": "Personal",
                        "email": "mikesmith@test.com",
                        "financial_transactions": [],
                        "home_phone": "(204) 555 5555",
                        "id": 1,
                        "identifier": "Mikey",
                        "institution_number": "",
                        "name": "Mike Smith",
                        "phone": "(204) 555 4444",
                        "routing_number": "111111111",
                        "transaction_schedules": [],
                        "transit_number": "",
                        "updated_at": "2015-02-10T23:50:45.000-06:00"
                    } 
                    */
                $client = (\App\Factory\ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id))->fill(
                    [
                        'address1' => $customer->address['address_1'] ?? '',
                        'address2' =>$customer->address['address_2'] ?? '',
                        'city' => $customer->address['city'] ?? '',
                        'postal_code' => $customer->address['postal_code'] ?? '',
                        'state' => $customer->address['province_code'] ?? '',
                        'country_id' => empty($customer->transit_number) ? 840 : 124,
                        'routing_id' => empty(($r = $customer->routing_number))? null : $r,
                        "number" => str_pad($customer->account_number,3,'0',STR_PAD_LEFT)
                    ]
                );
                $client->saveQuietly();
                $contact = (\App\Factory\ClientContactFactory::create($company_id, $this->company_gateway->user_id))->fill([
                    "first_name" => substr($customer->name, 0, stripos($customer->name, " ")),
                    "last_name" => substr($customer->name, stripos($customer->name, " ")),
                    "email" => $customer->email,
                    "phone" => $customer->phone
                ]);
                $client->contacts()->saveMany([$contact]);
                $contact = $client->contacts()->first();
                $this->client = $client;
                $customer = (new Customer((array) $customer))->additional(['id' => $customer->id, 'custom_identifier' => $customer->custom_identifier ?? $contact->id ] );
                $this->findOrCreateCustomer($customer->additional + $customer->jsonSerialize());
            });
        } catch (\Throwable $th) {
           $data = [
                'transaction_reference' => null,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $th->getMessage(),
                'code' =>(int) $th->getCode()
            ];
            SystemLogger::dispatch(['server_response' => $th->getMessage(), 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE,  880 , $this->company_gateway->client , $this->company_gateway->company);
            
            throw $th;
        }

        return true;
    }
    
    public function findOrCreateCustomer(array $data)
    {
        $result = null; 
        try {
           
            $existing = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->company_gateway->id)
                ->where('client_id', $this->client->id)
                ->orWhere(function (Builder $query) use ($data) {
                    $query->where('token', encrypt(join(".", Arr::only($data, 'id','custom_identifier')))  )
                    ->where('gateway_customer_reference', Arr::only($data,'id'));
                })
                ->exists();
            if ($existing) return true;
            else if(!Arr::has($data,'id')) {
                $result = $this->gateway->authorize($data)->send();
                if (!$result->isSuccessful()) throw new \Exception($result->getMessage(), (int) $result->getCode());

                $customer = new Customer($result->getData());
                $data = array_filter($customer->resolve());
            }
            
            $payment_method_id = Arr::has($data,'address.postal_code') && ((int) $data['address']['postal_code'])? GatewayType::BANK_TRANSFER: GatewayType::ACSS; 
            $gateway_token = $this->storeGatewayToken( [
                'payment_meta' => $data + ['brand' => 'Rotessa'],
                'token' => encrypt(join(".", Arr::only($data, 'id','custom_identifier'))),
                'payment_method_id' => $payment_method_id ,
            ], ['gateway_customer_reference' => 
                    $data['id'] 
                , 'routing_number' => Arr::has($data,'routing_number') ? $data['routing_number'] : $data['transit_number'] ]);
            
            return $data['id'];
            
            throw new \Exception($result->getMessage(), (int) $result->getCode());

        } catch (\Throwable $th) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $th->getMessage(),
                'code' =>(int) $th->getCode()
            ];

            SystemLogger::dispatch(['server_response' => is_null($result) ? '' : (array) $result, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE,  880 , $this->client, $this->company_gateway->company);
            
            throw $th;
        }
    }
}
