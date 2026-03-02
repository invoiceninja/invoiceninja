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

namespace App\PaymentDrivers\Nmi;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\NmiPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $nmi;

    public function __construct(NmiPaymentDriver $nmi)
    {
        $this->nmi = $nmi;
    }

    public function authorizeView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.nmi.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $payment_token = $request->input('payment_token');

        if (! $payment_token) {
            return $this->nmi->processUnsuccessfulTransaction([
                'response' => [],
                'error' => 'No payment token received from Collect.js',
                'error_code' => 'NMI_NO_TOKEN',
            ]);
        }

        $data = [
            'security_key' => $this->nmi->getSecurityKey(),
            'customer_vault' => 'add_customer',
            'payment_token' => $payment_token,
            'currency' => $this->nmi->client->getCurrencyCode(),
            'first_name' => $this->nmi->client->present()->first_name(),
            'last_name' => $this->nmi->client->present()->last_name(),
            'address1' => $this->nmi->client->address1,
            'city' => $this->nmi->client->city,
            'state' => $this->nmi->client->state,
            'zip' => $this->nmi->client->postal_code,
            'country' => $this->nmi->client->country ? $this->nmi->client->country->iso_3166_2 : '',
            'phone' => $this->nmi->client->present()->phone(),
            'email' => $this->nmi->client->present()->email(),
        ];

        $response = $this->nmi->gatewayRequest($data);

        if (! isset($response['response']) || $response['response'] != '1') {
            $error = $response['responsetext'] ?? 'Failed to add payment method';
            $error_code = $response['response_code'] ?? 'NMI_ERR';

            return $this->nmi->processUnsuccessfulTransaction([
                'response' => $response,
                'error' => $error,
                'error_code' => $error_code,
            ]);
        }

        $customer_vault_id = $response['customer_vault_id'] ?? null;

        if (! $customer_vault_id) {
            return $this->nmi->processUnsuccessfulTransaction([
                'response' => $response,
                'error' => 'No customer vault ID returned',
                'error_code' => 'NMI_NO_VAULT',
            ]);
        }

        // Store the gateway token
        $cgt = [];
        $cgt['token'] = $customer_vault_id;
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = $request->input('exp_month') ?: '';
        $payment_meta->exp_year = $request->input('exp_year') ?: '';
        $payment_meta->brand = $request->input('card_brand') ?: 'CC';
        $payment_meta->last4 = $request->input('last4') ?: '';
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $this->nmi->storeGatewayToken($cgt, []);

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.nmi.pay', $data);
    }

    public function paymentResponse(Request $request)
    {
        // If paying with a saved token
        if ($request->token) {
            $token = ClientGatewayToken::find($this->decodePrimaryKey($request->token));

            return $this->processTokenPayment($token->token, $request);
        }

        // If customer wants to store the card and pay
        if ($request->has('store_card') && $request->input('store_card') === 'true') {
            $vault_response = $this->addToCustomerVault($request->input('payment_token'));

            if ($vault_response && isset($vault_response['customer_vault_id'])) {
                // Store the token
                $cgt = [];
                $cgt['token'] = $vault_response['customer_vault_id'];
                $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

                $payment_meta = new \stdClass();
                $payment_meta->exp_month = $request->input('exp_month') ?: '';
                $payment_meta->exp_year = $request->input('exp_year') ?: '';
                $payment_meta->brand = $request->input('card_brand') ?: 'CC';
                $payment_meta->last4 = $request->input('last4') ?: '';
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $cgt['payment_meta'] = $payment_meta;

                $this->nmi->storeGatewayToken($cgt, []);

                return $this->processTokenPayment($vault_response['customer_vault_id'], $request);
            }
        }

        // One-time payment with Collect.js token (no save)
        return $this->processOneTimePayment($request);
    }

    /**
     * Process a one-time payment using a Collect.js payment_token.
     */
    private function processOneTimePayment(Request $request)
    {
        $data = [
            'security_key' => $this->nmi->getSecurityKey(),
            'type' => 'sale',
            'payment_token' => $request->input('payment_token'),
            'amount' => $request->input('amount_with_fee'),
            'orderid' => $this->harvestInvoiceId(),
            'currency' => $this->nmi->client->getCurrencyCode(),
            'email' => $this->nmi->client->present()->email(),
            'first_name' => $this->nmi->client->present()->first_name(),
            'last_name' => $this->nmi->client->present()->last_name(),
            'address1' => $this->nmi->client->address1,
            'city' => $this->nmi->client->city,
            'state' => $this->nmi->client->state,
            'zip' => $this->nmi->client->postal_code,
            'country' => $this->nmi->client->country ? $this->nmi->client->country->iso_3166_2 : '',
        ];

        $response = $this->nmi->gatewayRequest($data);

        if ($response && isset($response['response']) && $response['response'] == '1') {
            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    /**
     * Process a payment using a stored customer vault ID.
     */
    public function processTokenPayment($customer_vault_id, $request)
    {
        $data = [
            'security_key' => $this->nmi->getSecurityKey(),
            'type' => 'sale',
            'customer_vault_id' => $customer_vault_id,
            'amount' => $request->input('amount_with_fee'),
            'orderid' => $this->harvestInvoiceId(),
            'currency' => $this->nmi->client->getCurrencyCode(),
            'email' => $this->nmi->client->present()->email(),
        ];

        $response = $this->nmi->gatewayRequest($data);

        if ($response && isset($response['response']) && $response['response'] == '1') {
            $this->nmi->logSuccessfulGatewayResponse(
                ['response' => $response, 'data' => $this->nmi->payment_hash->data],
                SystemLog::TYPE_NMI
            );

            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    /**
     * Add a payment token to NMI customer vault.
     */
    private function addToCustomerVault($payment_token)
    {
        $data = [
            'security_key' => $this->nmi->getSecurityKey(),
            'customer_vault' => 'add_customer',
            'payment_token' => $payment_token,
            'currency' => $this->nmi->client->getCurrencyCode(),
            'first_name' => $this->nmi->client->present()->first_name(),
            'last_name' => $this->nmi->client->present()->last_name(),
            'email' => $this->nmi->client->present()->email(),
        ];

        $response = $this->nmi->gatewayRequest($data);

        if ($response && isset($response['response']) && $response['response'] == '1') {
            return $response;
        }

        return null;
    }

    private function harvestInvoiceId()
    {
        $_invoice = collect($this->nmi->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        if ($invoice) {
            return ctrans('texts.invoice_number') . '# ' . $invoice->number;
        }

        return ctrans('texts.invoice_number') . '####';
    }

    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->nmi->payment_hash->invoices(), 'amount')) + $this->nmi->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response['transactionid'] ?? '';

        $payment = $this->nmi->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        $error = $response['responsetext'] ?? 'Payment failed';
        $error_code = $response['response_code'] ?? 'Unknown';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->nmi->processUnsuccessfulTransaction($data);
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.nmi.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->nmi;
        $data['tokenization_key'] = $this->nmi->getTokenizationKey();

        return $data;
    }
}
