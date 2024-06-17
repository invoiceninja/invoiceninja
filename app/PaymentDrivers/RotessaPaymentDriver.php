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
use App\PaymentDrivers\Rotessa\Acss;
use App\PaymentDrivers\Rotessa\BankTransfer;
use App\PaymentDrivers\Rotessa\Resources\Customer;

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
        try {
            $customers = collect($this->gateway->getCustomers()->getData())->pluck('email','id');
            $client_emails = $customers->pluck('email')->all();
            $company_id = $this->company_gateway->company->id;
            $client_contacts = ClientContact::select('email','id','client_id',)->where('company_id', $companY_id)->whereIn('email', $client_emails )->whereNull('deleted_at')->where('is_deleted', false)->get();
            $client_contacts->each(
                function($contact) use ($customers) {
                    $result = $this->gateway->postCustomersShowWithCustomIdentifier(['custom_identifier' => $contact->id ]);
                    $customer = new Customer($result->getData());
                    $this->findOrCreateCustomer(array_filter($customer->toArray()));
                }
            );
        } catch (\Throwable $th) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $th->getMessage(),
                'code' =>(int) $th->getCode()
            ];

            SystemLogger::dispatch(['server_response' => $th->getMessage(), 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE,  880 , $this->client, $this->client->company);
            

            throw $th;
        }

        return true;
    }
    
    protected function findOrCreateCustomer(array $customer)
    {
        $result = null;  $data = []; $id = null; 
       
        try {
           
            $id = $data['id'] ?? null;
            $existing = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->company_gateway->id)
                ->where('client_id', $this->client->id)
                ->orWhere(function (Builder $query) use ($data, $id) {
                    $uqery->where('token', encrypt($data))
                    ->where('gateway_customer_reference', $id);
                })
                ->exists();
            if ($existing) return $existing->gateway_customer_reference;
            else if(is_null($id)) {
                $result = $this->gateway->authorize($data)->send();
                if ($result->isSuccessful()) {
                    $customer = new Customer($result->getData());
                    $data = array_filter($customer->toArray());
                }
            }

            $this->storeGatewayToken( [
                'payment_meta' => $data + ['brand' => 'Rotessa'],
                'token' => encrypt($data),
                'payment_method_id' => (int) $request->input("gateway_type_id"),
            ], ['gateway_customer_reference' => 
                    $result->getParameter('id') 
                , 'routing_number' =>  $result->getParameter('routing_number') ?? $result->getParameter('transit_number')]);
            
            return $result->getParameter('id');
            
            throw new \Exception($result->getMessage(), (int) $result->getCode());

        } catch (\Throwable $th) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $th->getMessage(),
                'code' =>(int) $th->getCode()
            ];

            SystemLogger::dispatch(['server_response' => is_null($result) ? '' : $result->getData(), 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE,  880 , $this->client, $this->client->company);
            
            throw $th;
        }
    }
}
