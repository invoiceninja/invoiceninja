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

namespace App\PaymentDrivers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\CurlUtils;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Nmi\CreditCard;
use App\Http\Requests\Payments\PaymentWebhookRequest;

class NmiPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_NMI;

    public const NMI_API_ENDPOINT = 'https://secure.nmi.com/api/transact.php';

    public function init()
    {
        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

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

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $data = [
            'security_key' => $this->getSecurityKey(),
            'type' => 'refund',
            'transactionid' => $payment->transaction_reference,
            'amount' => $amount,
        ];

        $response = $this->gatewayRequest($data);

        if ($response && $response['response'] == '1') {
            SystemLogger::dispatch(
                ['server_response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_NMI,
                $this->client,
                $this->client->company
            );

            return [
                'transaction_reference' => $response['transactionid'] ?? '',
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => $response['responsetext'] ?? 'Refund successful',
                'code' => $response['response_code'] ?? '100',
            ];
        }

        SystemLogger::dispatch(
            ['server_response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_NMI,
            $this->client,
            $this->client->company
        );

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => $response['responsetext'] ?? 'Refund failed',
            'code' => 422,
        ];
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $_invoice = collect($payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $invoice_id = $invoice
            ? ctrans('texts.invoice_number') . '# ' . $invoice->number
            : ctrans('texts.invoice_number') . '# ' . substr($payment_hash->hash, 0, 6);

        $data = [
            'security_key' => $this->getSecurityKey(),
            'type' => 'sale',
            'customer_vault_id' => $cgt->token,
            'amount' => $amount,
            'orderid' => $invoice_id,
            'currency' => $this->client->getCurrencyCode(),
            'email' => $this->client->present()->email(),
        ];

        $response = $this->gatewayRequest($data);

        if ($response && $response['response'] == '1') {
            $data = [
                'gateway_type_id' => $cgt->gateway_type_id,
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'transaction_reference' => $response['transactionid'],
                'amount' => $amount,
            ];

            $payment = $this->createPayment($data);
            $payment->meta = $cgt->meta;
            $payment->save();

            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();

            return $payment;
        }

        $error = $response['responsetext'] ?? 'Payment failed';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => 500,
        ];

        $this->processUnsuccessfulTransaction($data, false);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
    }

    /**
     * Make a request to the NMI Direct Post API.
     *
     * @param array $data Form-encoded parameters
     * @return array Parsed response as associative array
     */
    public function gatewayRequest(array $data): array
    {
        $url = self::NMI_API_ENDPOINT;

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $response = CurlUtils::post($url, http_build_query($data), $headers);

        $parsed = [];
        parse_str($response, $parsed);

        return $parsed;
    }

    /**
     * Get the NMI security key from gateway config.
     */
    public function getSecurityKey(): string
    {
        return $this->company_gateway->getConfigField('securityKey') ?: '';
    }

    /**
     * Get the NMI tokenization key for Collect.js from gateway config.
     */
    public function getTokenizationKey(): string
    {
        return $this->company_gateway->getConfigField('tokenizationKey') ?: '';
    }

    public function getClientRequiredFields(): array
    {
        $fields = [];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value1) {
            $fields[] = ['name' => 'client_custom_value1', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client1'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value2) {
            $fields[] = ['name' => 'client_custom_value2', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client2'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value3) {
            $fields[] = ['name' => 'client_custom_value3', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client3'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value4) {
            $fields[] = ['name' => 'client_custom_value4', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client4'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }

    public function auth(): string
    {
        try {
            // Test credentials by making a minimal API call
            $data = [
                'security_key' => $this->getSecurityKey(),
                'type' => 'validate',
                'amount' => '0.00',
            ];

            $response = $this->gatewayRequest($data);

            if ($response && isset($response['response'])) {
                return 'ok';
            }
        } catch (\Exception $e) {
            nlog('NMI auth error: ' . $e->getMessage());
        }

        return 'error';
    }
}
