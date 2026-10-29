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

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Razorpay\Hosted;
use App\Utils\Traits\MakesHash;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public \Razorpay\Api\Api $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::HOSTED_PAGE => Hosted::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_RAZORPAY;

    public function init(): self
    {
        $this->gateway = new \Razorpay\Api\Api(
            $this->company_gateway->getConfigField('apiKey'),
            $this->company_gateway->getConfigField('apiSecret'),
        );

        return $this;
    }

    public function auth(): string
    {
        $apiKey = trim((string) $this->company_gateway->getConfigField('apiKey'));
        $apiSecret = trim((string) $this->company_gateway->getConfigField('apiSecret'));

        if ($apiKey === '' || $apiSecret === '') {
            return 'error';
        }

        try {
            $this->init()->gateway->payment->all(['count' => 1]); // @phpstan-ignore-line

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::HOSTED_PAGE,
        ];
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

    public function refund(Payment $payment, $amount, $return_client_response = false) {}

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash) {}

    /**
     * Convert the amount to the format that Razorpay supports.
     *
     * @param mixed|float $amount
     * @return int
     */
    public function convertToRazorpayAmount($amount): int
    {
        return \number_format((float) $amount * 100, 0, '.', '');
    }

    /**
     * Records a captured payment when the customer never returned from Checkout.
     *
     * @see https://razorpay.com/docs/webhooks/validate-test/
     */
    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        $webhook_secret = (string) $this->company_gateway->getConfigField('webhookSecret');

        if ($webhook_secret === '') {
            nlog('Razorpay webhook ignored: no webhook secret configured');

            return response()->json([], 200);
        }

        try {
            $this->init()->gateway->utility->verifyWebhookSignature( // @phpstan-ignore-line
                $request->getContent(),
                (string) $request->header('X-Razorpay-Signature'),
                $webhook_secret,
            );
        } catch (SignatureVerificationError) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($request->getContent(), true);
        $entity = $event['payload']['payment']['entity'] ?? null;

        if (! in_array($event['event'] ?? null, ['payment.captured', 'order.paid'], true)
            || ! is_array($entity)
            || ($entity['status'] ?? null) !== 'captured'
            || empty($entity['id'])
            || empty($entity['order_id'])) {
            return response()->json([], 200);
        }

        $payment_hash = PaymentHash::query()
            ->where('data->order_id', $entity['order_id'])
            ->whereHas('fee_invoice', fn ($query) => $query->where('company_id', $this->company_gateway->company_id))
            ->first();

        if (! $payment_hash) {
            return response()->json([], 200);
        }

        $this->setPaymentHash($payment_hash);
        $this->client = $payment_hash->fee_invoice->client;

        $this->setPaymentMethod(GatewayType::HOSTED_PAGE)
            ->payment_method
            ->recordPayment($entity['id']);

        return response()->json([], 200);
    }
}
