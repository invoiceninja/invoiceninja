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

namespace App\PaymentDrivers;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Forte\ACH;
use Illuminate\Support\Facades\Http;
use App\Repositories\ClientRepository;
use App\PaymentDrivers\Forte\CreditCard;
use App\Repositories\ClientContactRepository;
use App\PaymentDrivers\Factory\ForteCustomerFactory;

class FortePaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_FORTE; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

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

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }
        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');
        $forte_organization_id = $this->company_gateway->getConfigField('organizationId');
        $forte_location_id = $this->company_gateway->getConfigField('locationId');

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $forte_base_uri.'organizations/'.$forte_organization_id.'/locations/'.$forte_location_id.'/transactions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                     "action":"reverse", 
                     "authorization_amount":'.$amount.',
                     "original_transaction_id":"'.$payment->transaction_reference.'",
                     "authorization_code": "9ZQ754"
              }',
                CURLOPT_HTTPHEADER => [
                  'Content-Type: application/json',
                  'X-Forte-Auth-Organization-Id: '.$forte_organization_id,
                  'Authorization: Basic '.base64_encode($forte_api_access_id.':'.$forte_secure_key)
                ],
              ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response = json_decode($response);
        } catch (\Throwable $th) {
            $message = [
                'action' => 'error',
                'data' => $th,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FORTE,
                $this->client,
                $this->client->company,
            );
        }

        $message = [
            'action' => 'refund',
            'server_message' => $response->response->response_desc,
            'server_response' => $response,
            'data' => $payment->paymentables,
        ];

        if ($httpcode > 299) {
            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FORTE,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => $response,
                'success' => false,
                'description' => $payment->paymentables,
                'code' => 422,
            ];
        }

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_FORTE,
            $this->client,
            $this->client->company,
        );

        return [
            'transaction_reference' => $payment->transaction_reference,
            'transaction_response' => $response,
            'success' => $response->response->response_code == 'A01' ? true : false,
            'description' => $payment->paymentables,
            'code' => $httpcode,
        ];
    }

    ////////////////////////////////////////////
    // DB
    ///////////////////////////////////////////
    public function auth(): bool
    {

        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }
        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');
        $forte_organization_id = $this->company_gateway->getConfigField('organizationId');
        $forte_location_id = $this->company_gateway->getConfigField('locationId');

        $response = Http::withBasicAuth($forte_api_access_id, $forte_secure_key)
                    ->withHeaders(['X-Forte-Auth-Organization-Id' => $forte_organization_id])
                    ->get("{$forte_base_uri}/organizations/{$forte_organization_id}/locations/{$forte_location_id}/customers/");

        return $response->successful();

    }

    public function baseUri(): string
    {

        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }

        return $forte_base_uri;
    }

    private function getOrganisationId(): string
    {
        return $this->company_gateway->getConfigField('organizationId');
    }

    public function getLocationId(): string
    {
        return $this->company_gateway->getConfigField('locationId');
    }

    public function stubRequest()
    {

        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');

        return Http::withBasicAuth($forte_api_access_id, $forte_secure_key)
                    ->withHeaders(['X-Forte-Auth-Organization-Id' => $this->getOrganisationId()]);
    }

    private function getClient(?string $email)
    {
        return ClientContact::query()
                     ->where('company_id', $this->company_gateway->company_id)
                     ->where('email', $email)
                     ->first();
    }

    public function getLocation()
    {

        $response = $this->stubRequest()
                    ->withQueryParameters(['page_size' => 10000])
                    ->get("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}");

        if($response->successful()) {
            return $response->json();
        }

        return false;
    }

    public function updateFees()
    {
        $response = $this->getLocation();

        if($response) {
            $body = $response['services'];

            $fees_and_limits = $this->company_gateway->fees_and_limits;

            if($body['card']['service_fee_percentage'] > 0 || $body['card']['service_fee_additional_amount'] > 0) {

                $fees_and_limits->{1}->fee_amount = $body['card']['service_fee_additional_amount'];
                $fees_and_limits->{1}->fee_percent = $body['card']['service_fee_percentage'];
            }

            if($body['debit']['service_fee_percentage'] > 0 || $body['debit']['service_fee_additional_amount'] > 0) {

                $fees_and_limits->{2}->fee_amount = $body['debit']['service_fee_additional_amount'];
                $fees_and_limits->{2}->fee_percent = $body['debit']['service_fee_percentage'];
            }

            $this->company_gateway->fees_and_limits = $fees_and_limits;
            $this->company_gateway->save();

        }

        return false;

    }

    public function importCustomers()
    {

        $response = $this->stubRequest()
                    ->withQueryParameters(['page_size' => 10000])
                    ->get("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}/customers");

        if($response->successful()) {

            foreach($response->json()['results'] as $customer) {

                $client_repo = new ClientRepository(new ClientContactRepository());
                $factory = new ForteCustomerFactory();

                $data = $factory->convertToNinja($customer, $this->company_gateway->company);

                if(strlen($data['email']) == 0 || $this->getClient($data['email'])) {
                    continue;
                }

                $client_repo->save($data, ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id));

                //persist any payment methods here!
            }
        }

    }

}
