<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Forte;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Validator;
use App\PaymentDrivers\FortePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;

class ACH implements LivewireMethodInterface
{
    use MakesHash;

    public $forte;

    private $forte_base_uri = "";
    private $forte_api_access_id = "";
    private $forte_secure_key = "";
    private $forte_auth_organization_id = "";
    private $forte_organization_id = "";
    private $forte_location_id = "";

    public function __construct(FortePaymentDriver $forte)
    {
        $this->forte = $forte;

        $this->forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->forte->company_gateway->getConfigField('testMode') == false) {
            $this->forte_base_uri = "https://api.forte.net/v3/";
        }
        $this->forte_api_access_id = $this->forte->company_gateway->getConfigField('apiAccessId');
        $this->forte_secure_key = $this->forte->company_gateway->getConfigField('secureKey');
        $this->forte_auth_organization_id = $this->forte->company_gateway->getConfigField('authOrganizationId');
        $this->forte_organization_id = $this->forte->company_gateway->getConfigField('organizationId');
        $this->forte_location_id = $this->forte->company_gateway->getConfigField('locationId');
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->forte;

        return render('gateways.forte.ach.authorize', $data);
    }

    private function storePaymentMethod(array $payload)
    {

        $cst = $this->forte->findOrCreateCustomer();

        $data = [
            "notes" => $payload['account_holder_name'],
            "echeck" => [
                "one_time_token" => $payload['one_time_token'],
                "account_holder" => $payload['account_holder_name'],
                "account_type" => "checking"
                ],
        ];

        $response = $this->forte
                        ->stubRequest()
                        ->post("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/customers/{$cst}/paymethods", $data);

        if ($response->successful()) {

            $token = $response->object();

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) '';
            $payment_meta->exp_year = (string) '';
            $payment_meta->brand = (string) 'ACH';
            $payment_meta->last4 = (string) $payload['last_4'];
            $payment_meta->type = GatewayType::BANK_TRANSFER;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $token->paymethod_token,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $cgt = $this->forte->storeGatewayToken($data, ['gateway_customer_reference' => $cst]);

            return $cgt;

        }

        $error = $response->object();

        $message = [
            'server_message' => $error->response->response_desc,
            'server_response' => $response->json(),
            'data' => $data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_FORTE,
            $this->forte->client,
            $this->forte->client->company,
        );

        throw new \App\Exceptions\PaymentFailed("Unable to store payment method: {$error->response->response_desc}", 400);

    }


    public function authorizeResponse(Request $request)
    {
        $data = [
            'account_holder_name' => $request->account_holder_name,
            'one_time_token' => $request->one_time_token,
            'last_4' => $request->last_4,
        ];

        $cgt = $this->storePaymentMethod($data);

        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');

    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.forte.ach.pay', $data);
    }

    public function paymentResponse($request)
    {
        nlog($request->all());

        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();

        //Handle Token Billing
        if ($request->token && strlen($request->token) > 4) {

            $cgt = \App\Models\ClientGatewayToken::where('token', $request->token)->firstOrFail();
            $payment = $this->tokenBilling($cgt, $payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
        }

        //Handle Storing Payment Method + Token Billing
        if (isset($this->forte->company_gateway->token_billing) && $this->forte->company_gateway->token_billing != 'off') {

            $data = [
                'account_holder_name' => $request->account_holder_name,
                'one_time_token' => $request->payment_token,
                'last_4' => $request->last_4,
            ];

            $cgt = $this->storePaymentMethod($data);

            $payment = $this->tokenBilling($cgt, $payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

        }

        $data = [
            'action' => 'sale',
            'authorization_amount' => $payment_hash->data->total->amount_with_fee,
            'echeck' => [
                'sec_code' => 'PPD',
                'one_time_token' => $request->payment_token
            ],
            'billing_address' => [
                'first_name' => $this->forte->client->name,
                'last_name' => $this->forte->client->name
            ]
        ];

        $response = $this->forte
                        ->stubRequest()
                        ->post("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/transactions", $data);

        if ($response->successful()) {

            $forte_response = $response->object();

            $message = [
                'server_message' => $forte_response->response->response_desc,
                'server_response' => $forte_response,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_FORTE,
                $this->forte->client,
                $this->forte->client->company,
            );

            $data = [
                'payment_method' => $request->payment_method_id,
                'payment_type' => PaymentType::ACH,
                'amount' => $payment_hash->data->amount_with_fee,
                'transaction_reference' => $forte_response->transaction_id,
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
            ];

            $payment = $this->forte->createPayment($data, Payment::STATUS_COMPLETED);

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

        }
        //Handle Failures.

        $forte_response = $response->object();

        $message = [
            'server_message' => $forte_response->response->response_desc,
            'server_response' => $forte_response,
            'data' => $payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_FORTE,
            $this->forte->client,
            $this->forte->client->company,
        );

        $error = Validator::make([], []);

        $error->getMessageBag()->add('gateway_error', $forte_response->response->response_desc);

        return redirect()->route('client.invoice.show', ['invoice' => $payment_hash->fee_invoice->hashed_id])->withErrors($error);

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount_with_fee = $payment_hash->data->amount_with_fee;
        $fee_total = $payment_hash->fee_total;

        $data = [
            "action" => "sale",
            "authorization_amount" => $amount_with_fee,
            "paymethod_token" => $cgt->token,
            "billing_address" => [
                "first_name" => $this->forte->client->present()->first_name(),
                "last_name" => $this->forte->client->present()->last_name()
            ],
            "echeck" => [
                "sec_code" => "WEB",
            ]

        ];

        if ($fee_total > 0) {
            $data["service_fee_amount"] = $fee_total;
        }

        $response = $this->forte
                        ->stubRequest()
                        ->post("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/transactions", $data);

        $forte_response = $response->object();

        if ($response->successful()) {

            $data = [
                'payment_method' => $cgt->gateway_type_id,
                'payment_type' => \App\Models\PaymentType::ACH,
                'amount' => $payment_hash->data->amount_with_fee,
                'transaction_reference' => $forte_response->transaction_id,
                'gateway_type_id' => $cgt->gateway_type_id,
            ];

            $payment = $this->forte->createPayment($data, Payment::STATUS_COMPLETED);

            $message = [
                'server_message' => $forte_response->response->response_desc,
                'server_response' => $response->json(),
                'data' => $data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_FORTE,
                $this->forte->client,
                $this->forte->client->company,
            );

            return $payment;
        }

        $forte_response = $response->object();

        $message = [
            'server_message' => $forte_response->response->response_desc,
            'server_response' => $forte_response,
            'data' => $payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_FORTE,
            $this->forte->client,
            $this->forte->client->company,
        );

        throw new PaymentFailed($forte_response->response->response_desc ?? 'Unable to process payment', 500);

    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.forte.ach.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $this->forte->payment_hash->data = array_merge((array) $this->forte->payment_hash->data, $data);
        $this->forte->payment_hash->save();

        $data['gateway'] = $this->forte;

        return $data;
    }
}
