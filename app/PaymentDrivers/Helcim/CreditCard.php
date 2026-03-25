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

namespace App\PaymentDrivers\Helcim;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\HelcimPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreditCard implements MethodInterface
{
    protected HelcimPaymentDriver $helcim_driver;

    public function __construct(HelcimPaymentDriver $helcim_driver)
    {
        $this->helcim_driver = $helcim_driver;
    }

    /**
     * Authorization view for adding a payment method
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->helcim_driver;
        
        return render('gateways.helcim.credit_card.authorize', $data);
    }

    /**
     * Handle authorization response (saving a payment method)
     * 
     * SECURITY: Card data is processed server-side only
     */
    public function authorizeResponse(Request $request)
    {
        $cardNumber = $request->input('card_number');
        $cardExpiry = $request->input('card_expiry');
        $cardCvv = $request->input('card_cvv');
        $cardholderName = $request->input('cardholder_name');
        $isDefault = $request->input('is_default', false);

        // Validate card inputs
        if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
            throw new PaymentFailed('Please provide complete card information', 400);
        }

        // Parse expiry (MM/YY format)
        $expiryParts = explode('/', $cardExpiry);
        if (count($expiryParts) !== 2) {
            throw new PaymentFailed('Invalid expiry date format', 400);
        }

        $expiryMonth = str_pad($expiryParts[0], 2, '0', STR_PAD_LEFT);
        $expiryYear = '20' . $expiryParts[1]; // Assuming 20XX

        try {
            // Create card token via Helcim API (server-side only)
            $response = $this->helcim_driver->gatewayRequest('/payment/card-verify', [
                'cardNumber' => $cardNumber,
                'cardExpiry' => $expiryMonth . $expiryYear,
                'cardCVV' => $cardCvv,
                'cardHolderName' => $cardholderName,
            ]);

            if (!isset($response['cardToken'])) {
                throw new PaymentFailed($response['message'] ?? 'Failed to verify card', 400);
            }

            // Store the payment method
            $payment_meta = new \stdClass();
            $payment_meta->exp_month = $expiryMonth;
            $payment_meta->exp_year = $expiryYear;
            $payment_meta->brand = $response['cardType'] ?? 'Unknown';
            $payment_meta->last4 = substr($cardNumber, -4);
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $response['cardToken'],
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ];

            $this->helcim_driver->storeGatewayToken($data, ['gateway_customer_reference' => $response['cardToken']]);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payment_methods.index');
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            throw new PaymentFailed($e->getMessage(), 400);
        }
    }

    /**
     * Payment view for processing a payment
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->helcim_driver;
        $data['amount'] = $this->helcim_driver->payment_hash->data->amount_with_fee;
        $data['currency'] = $this->helcim_driver->client->currency()->code;
        $data['payment_hash'] = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;
        $data['tokens'] = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::CREDIT_CARD)
            ->get();

        return render('gateways.helcim.credit_card.pay', $data);
    }

    /**
     * Process payment response
     * 
     * SECURITY: All payment processing happens server-side
     */
    public function paymentResponse(Request $request)
    {
        $paymentHash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        $this->helcim_driver->payment_hash = $paymentHash;
        $this->helcim_driver->init();

        $useToken = $request->input('use_token', false);
        $tokenId = $request->input('token');
        $storeCard = $request->input('store_card', false);

        try {
            if ($useToken && $tokenId) {
                // Payment with saved card
                $token = $this->helcim_driver->client->gateway_tokens()
                    ->where('hashed_id', $tokenId)
                    ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
                    ->firstOrFail();

                return $this->processTokenPayment($token, $paymentHash);
            } else {
                // Payment with new card
                return $this->processNewCardPayment($request, $paymentHash, $storeCard);
            }
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payment_methods.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Process payment with a saved token
     */
    private function processTokenPayment(ClientGatewayToken $token, PaymentHash $paymentHash)
    {
        $amount = $paymentHash->data->amount_with_fee;

        $response = $this->helcim_driver->gatewayRequest('/payment/purchase', [
            'cardToken' => $token->token,
            'amount' => $amount,
            'currency' => $this->helcim_driver->client->currency()->code,
        ]);

        if (isset($response['status']) && $response['status'] === 'APPROVED') {
            $data = [
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'amount' => $amount,
                'transaction_reference' => $response['transactionId'] ?? '',
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $this->helcim_driver->createPayment($data, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
        }

        throw new PaymentFailed($response['message'] ?? 'Payment failed', 400);
    }

    /**
     * Process payment with a new card
     */
    private function processNewCardPayment(Request $request, PaymentHash $paymentHash, bool $storeCard)
    {
        $cardNumber = $request->input('card_number');
        $cardExpiry = $request->input('card_expiry');
        $cardCvv = $request->input('card_cvv');
        $cardholderName = $request->input('cardholder_name');

        if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
            throw new PaymentFailed('Please provide complete card information', 400);
        }

        // Parse expiry
        $expiryParts = explode('/', $cardExpiry);
        if (count($expiryParts) !== 2) {
            throw new PaymentFailed('Invalid expiry date format', 400);
        }

        $expiryMonth = str_pad($expiryParts[0], 2, '0', STR_PAD_LEFT);
        $expiryYear = '20' . $expiryParts[1];

        $amount = $paymentHash->data->amount_with_fee;

        // Process payment via Helcim API
        $response = $this->helcim_driver->gatewayRequest('/payment/purchase', [
            'cardNumber' => $cardNumber,
            'cardExpiry' => $expiryMonth . $expiryYear,
            'cardCVV' => $cardCvv,
            'cardHolderName' => $cardholderName,
            'amount' => $amount,
            'currency' => $this->helcim_driver->client->currency()->code,
        ]);

        if (isset($response['status']) && $response['status'] === 'APPROVED') {
            $data = [
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'amount' => $amount,
                'transaction_reference' => $response['transactionId'] ?? '',
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $this->helcim_driver->createPayment($data, Payment::STATUS_COMPLETED);

            // Store card if requested
            if ($storeCard && isset($response['cardToken'])) {
                $payment_meta = new \stdClass();
                $payment_meta->exp_month = $expiryMonth;
                $payment_meta->exp_year = $expiryYear;
                $payment_meta->brand = $response['cardType'] ?? 'Unknown';
                $payment_meta->last4 = substr($cardNumber, -4);
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $tokenData = [
                    'payment_meta' => $payment_meta,
                    'token' => $response['cardToken'],
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ];

                $this->helcim_driver->storeGatewayToken($tokenData, ['gateway_customer_reference' => $response['cardToken']]);
            }

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
        }

        throw new PaymentFailed($response['message'] ?? 'Payment failed', 400);
    }

    /**
     * Process token billing (recurring payments)
     */
    public function tokenBilling(ClientGatewayToken $cgt, float $amount)
    {
        $response = $this->helcim_driver->gatewayRequest('/payment/purchase', [
            'cardToken' => $cgt->token,
            'amount' => $amount,
            'currency' => $this->helcim_driver->client->currency()->code,
        ]);

        if (isset($response['status']) && $response['status'] === 'APPROVED') {
            $data = [
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'amount' => $amount,
                'transaction_reference' => $response['transactionId'] ?? '',
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $this->helcim_driver->createPayment($data, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return $payment;
        }

        SystemLogger::dispatch(
            ['error' => $response['message'] ?? 'Token billing failed', 'response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        throw new PaymentFailed($response['message'] ?? 'Payment failed', 400);
    }
}