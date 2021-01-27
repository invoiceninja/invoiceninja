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

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\CheckoutCom\CreditCard;
use App\PaymentDrivers\CheckoutCom\Utilities;
use App\Utils\Traits\SystemLogTrait;
use Checkout\CheckoutApi;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\Refund;
use Exception;

class CheckoutComPaymentDriver extends BaseDriver
{
    use SystemLogTrait, Utilities;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = true;

    /* Token billing */
    public $token_billing = true;

    /* Authorise payment methods */
    public $can_authorise_credit_card = true;

    /**
     * @var CheckoutApi;
     */
    public $gateway;

    /**
     * @var
     */
    public $payment_method; //the gateway type id

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT;

    /**
     * Returns the default gateway type.
     */
    public function gatewayTypes()
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

    /**
     * Since with Checkout.com we handle only credit cards, this method should be empty.
     * @param int|null $payment_method
     * @return CheckoutComPaymentDriver
     */
    public function setPaymentMethod($payment_method = null): CheckoutComPaymentDriver
    {
        // At the moment Checkout.com payment
        // driver only supports payments using credit card.

        $class = self::$methods[GatewayType::CREDIT_CARD];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Initialize the checkout payment driver
     * @return $this
     */
    public function init()
    {
        $config = [
            'secret' => $this->company_gateway->getConfigField('secretApiKey'),
            'public' => $this->company_gateway->getConfigField('publicApiKey'),
            'sandbox' => $this->company_gateway->getConfigField('testMode'),
        ];

        $this->gateway = new CheckoutApi($config['secret'], $config['sandbox'], $config['public']);

        return $this;
    }

    /**
     * Process different view depending on payment type
     * @param int $gateway_type_id The gateway type
     * @return string                       The view string
     */
    public function viewForType($gateway_type_id)
    {
        // At the moment Checkout.com payment
        // driver only supports payments using credit card.

        return 'gateways.checkout.credit_card.pay';
    }

    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    /**
     * Payment View
     *
     * @param array $data Payment data array
     * @return view         The payment view
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Process the payment response
     *
     * @param Request $request The payment request
     * @return view             The payment response view
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function storePaymentMethod(array $data)
    {
        return $this->storeGatewayToken($data);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $checkout_payment = new Refund($payment->transaction_reference);

        try {
            $refund = $this->gateway->payments()->refund($checkout_payment);
            $checkout_payment = $this->gateway->payments()->details($refund->id);

            $response = ['refund_response' => $refund, 'checkout_payment_fetch' => $checkout_payment];

            return [
                'transaction_reference' => $refund->action_id,
                'transaction_response' => json_encode($response),
                'success' => $checkout_payment->status == 'Refunded',
                'description' => $checkout_payment->status,
                'code' => $checkout_payment->http_code,
            ];
        } catch (CheckoutHttpException $e) {
            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $this->init();

        $method = new IdSource($cgt->token);

        $payment = new \Checkout\Models\Payments\Payment($method, $this->client->getCurrencyCode());
        $payment->amount = $this->convertToCheckoutAmount($amount, $this->client->getCurrencyCode());
        $payment->reference = $cgt->meta->last4 . '-' . now();

        $request = new PaymentResponseRequest();
        $request->setMethod('POST');
        $request->request->add(['payment_hash' => $payment_hash]);

        try {
            $response = $this->gateway->payments()->request($payment);

            if ($response->status == 'Authorized') {
                $this->confirmGatewayFee($request);

                return $this->processSuccessfulPayment($response, true);
            }

            if ($response->status == 'Declined') {
                $this->unWindGatewayFees($payment_hash);

                PaymentFailureMailer::dispatch(
                    $this->client, $response->response_summary,
                    $this->client->company,
                    $this->payment_hash->data->value
                );

                $this->processUnsuccessfulPayment($response, false);

                return false;
            }
        } catch (\Exception | CheckoutHttpException $e) {
            $this->unWindGatewayFees($payment_hash);

            // ..
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
        $this->init();
        $this->setPaymentHash($request->getPaymentHash());

        try {
            $payment = $this->gateway->payments()->details($request->query('cko-session-id'));

            if ($payment->approved) {
                return $this->processSuccessfulPayment($payment);
            } else {
                return $this->processUnsuccessfulPayment($payment);
            }
        } catch (CheckoutHttpException | Exception $e) {
            return $this->processInternallyFailedPayment($this, $e);
        }
    }

    public function detach(ClientGatewayToken $clientGatewayToken)
    {
        // Gateway doesn't support this feature.
    }
}
