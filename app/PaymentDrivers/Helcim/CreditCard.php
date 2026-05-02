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
use App\PaymentDrivers\Common\LivewireMethodInterface;
use Illuminate\Http\Request;

class CreditCard implements MethodInterface, LivewireMethodInterface
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
        
        // Initialize HelcimPay.js session for card verification (PCI compliant)
        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'verify',
                'currency' => $this->helcim_driver->client->currency()->code,
                'amount' => 0, // Verify doesn't charge
            ]);
            
            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );
            
            throw new PaymentFailed('Failed to initialize payment form: ' . $e->getMessage(), 400);
        }
        
        return render('gateways.helcim.credit_card.authorize', $data);
    }

    /**
     * Handle authorization response (saving a payment method)
     * 
     * PCI COMPLIANCE: This now processes tokenized data from HelcimPay.js
     * instead of raw card data. Card information never touches our servers.
     */
    public function authorizeResponse(Request $request)
    {
        $transactionData = $request->input('transaction_data');
        $transactionHash = $request->input('transaction_hash');
        $secretToken = $request->input('secret_token');
        $isDefault = $request->input('is_default', false);

        // Validate required fields
        if (empty($transactionData) || empty($transactionHash) || empty($secretToken)) {
            throw new PaymentFailed('Invalid payment response', 400);
        }

        try {
            // Decode transaction data
            $data = json_decode($transactionData, true);
            
            if (!$data) {
                throw new PaymentFailed('Invalid transaction data format', 400);
            }

            // Validate the response hash (PCI compliance check)
            if (!$this->helcim_driver->validateHelcimPayResponse($transactionData, $transactionHash, $secretToken)) {
                throw new PaymentFailed('Transaction validation failed - data may have been tampered with', 400);
            }

            // Check transaction status
            if (!isset($data['status']) || $data['status'] !== 'APPROVED') {
                throw new PaymentFailed('Card verification failed: ' . ($data['warning'] ?? 'Unknown error'), 400);
            }

            // Extract card token from response
            if (!isset($data['cardToken'])) {
                throw new PaymentFailed('No card token received', 400);
            }

            // Parse expiry date from response
            $expiryMonth = null;
            $expiryYear = null;
            
            // Store the payment method
            $payment_meta = new \stdClass();
            $payment_meta->exp_month = $expiryMonth;
            $payment_meta->exp_year = $expiryYear;
            $payment_meta->brand = $data['cardType'] ?? 'Unknown';
            $payment_meta->last4 = $data['cardNumber'] ?? ''; // Last 4 digits only
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $tokenData = [
                'payment_meta' => $payment_meta,
                'token' => $data['cardToken'],
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ];

            $this->helcim_driver->storeGatewayToken($tokenData, ['gateway_customer_reference' => $data['cardToken']]);

            SystemLogger::dispatch(
                ['response' => $data, 'data' => $tokenData],
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

        // Initialize HelcimPay.js session for new card payments (PCI compliant)
        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'purchase',
                'amount' => $data['amount'],
                'currency' => $data['currency'],
            ]);
            
            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );
            
            throw new PaymentFailed('Failed to initialize payment form: ' . $e->getMessage(), 400);
        }

        return render('gateways.helcim.credit_card.pay', $data);
    }

    /**
     * Process payment response
     * 
     * PCI COMPLIANCE: This now processes tokenized data from HelcimPay.js
     * instead of raw card data.
     */
    public function paymentResponse(Request $request)
    {
        $paymentHash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        $this->helcim_driver->payment_hash = $paymentHash;
        $this->helcim_driver->init();

        $useToken = $request->input('use_token', false);
        $tokenId = $request->input('token');

        try {
            if ($useToken && $tokenId) {
                // Payment with saved card token
                $token = $this->helcim_driver->client->gateway_tokens()
                    ->where('id', $this->helcim_driver->decodePrimaryKey($tokenId))
                    ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
                    ->firstOrFail();

                return $this->processTokenPayment($token, $paymentHash);
            } else {
                // Payment with HelcimPay.js (new card)
                return $this->processHelcimPayPayment($request, $paymentHash);
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
            'cardData' => ['cardToken' => $token->token],
            'amount' => $amount,
            'currency' => $this->helcim_driver->client->currency()->code,
            'ipAddress' => request()->ip(),
            'ecommerce' => true,
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
     * Process payment with HelcimPay.js tokenized data
     * 
     * PCI COMPLIANCE: Processes tokenized payment from HelcimPay.js
     */
    private function processHelcimPayPayment(Request $request, PaymentHash $paymentHash)
    {
        $transactionData = $request->input('transaction_data');
        $transactionHash = $request->input('transaction_hash');
        $secretToken = $request->input('secret_token');
        $storeCard = $request->input('store_card', false);

        // Validate required fields
        if (empty($transactionData) || empty($transactionHash) || empty($secretToken)) {
            throw new PaymentFailed('Invalid payment response', 400);
        }

        // Decode transaction data
        $data = json_decode($transactionData, true);
        
        if (!$data) {
            throw new PaymentFailed('Invalid transaction data format', 400);
        }

        // Validate the response hash (PCI compliance check)
        if (!$this->helcim_driver->validateHelcimPayResponse($transactionData, $transactionHash, $secretToken)) {
            throw new PaymentFailed('Transaction validation failed - data may have been tampered with', 400);
        }

        // Check transaction status
        if (!isset($data['status']) || $data['status'] !== 'APPROVED') {
            throw new PaymentFailed('Payment failed: ' . ($data['warning'] ?? 'Unknown error'), 400);
        }

        $amount = $paymentHash->data->amount_with_fee;

        // Create payment record
        $paymentData = [
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'amount' => $amount,
            'transaction_reference' => $data['transactionId'] ?? '',
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->helcim_driver->createPayment($paymentData, Payment::STATUS_COMPLETED);

        // Store card if requested and token is available
        if ($storeCard && isset($data['cardToken'])) {
            $payment_meta = new \stdClass();
            $payment_meta->exp_month = null;
            $payment_meta->exp_year = null;
            $payment_meta->brand = $data['cardType'] ?? 'Unknown';
            $payment_meta->last4 = $data['cardNumber'] ?? ''; // Last 4 digits only
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $tokenData = [
                'payment_meta' => $payment_meta,
                'token' => $data['cardToken'],
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ];

            $this->helcim_driver->storeGatewayToken($tokenData, ['gateway_customer_reference' => $data['cardToken']]);
        }

        SystemLogger::dispatch(
            ['response' => $data, 'data' => $paymentData],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
    }

    /**
     * Return the Livewire-compatible blade view path.
     * Called by BaseDriver::livewirePaymentView().
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.helcim.credit_card.pay_livewire';
    }

    /**
     * Prepare payment data for the Livewire/view payment flow.
     * Called by BaseDriver::processPaymentViewData().
     * Must include ALL variables the pay_livewire.blade.php view needs.
     */
    public function paymentData(array $data): array
    {
        $this->helcim_driver->payment_hash->data = array_merge((array) $this->helcim_driver->payment_hash->data, $data);
        $this->helcim_driver->payment_hash->save();

        $data['gateway'] = $this->helcim_driver;
        $data['payment_hash'] = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;
        $data['amount'] = $this->helcim_driver->payment_hash->data->amount_with_fee;
        $data['currency'] = $this->helcim_driver->client->currency()->code;
        $data['tokens'] = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::CREDIT_CARD)
            ->get();

        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'purchase',
                'amount' => $data['amount'],
                'currency' => $data['currency'],
            ]);
            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
        } catch (\Exception $e) {
            $data['checkout_token'] = '';
            $data['secret_token'] = '';
        }

        return $data;
    }

    /**
     * Process token billing (recurring payments)
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = $payment_hash->data->amount_with_fee;

        $response = $this->helcim_driver->gatewayRequest('/payment/purchase', [
            'cardData' => ['cardToken' => $cgt->token],
            'amount' => $amount,
            'currency' => $this->helcim_driver->client->currency()->code,
            'ipAddress' => request()->ip(),
            'ecommerce' => true,
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