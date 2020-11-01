<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Http\Requests\Request;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\Stripe\Alipay;
use App\PaymentDrivers\Stripe\Charge;
use App\PaymentDrivers\Stripe\CreditCard;
use App\PaymentDrivers\Stripe\SOFORT;
use App\PaymentDrivers\Stripe\Utilities;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripePaymentDriver extends BaseDriver
{
    use MakesHash, Utilities;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    /** @var \Stripe\StripeClient */
    public $stripe;

    protected $customer_reference = 'customerReferenceParam';

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
        GatewayType::ALIPAY => Alipay::class,
        GatewayType::SOFORT => SOFORT::class,
        GatewayType::APPLE_PAY => 1, // TODO
        GatewayType::SEPA => 1, // TODO
    ];

    /**
     * Initializes the Stripe API.
     * @return void
     */
    public function init(): void
    {
        $this->stripe = new \Stripe\StripeClient(
            $this->company_gateway->getConfigField('apiKey')
        );

        Stripe::setApiKey($this->company_gateway->getConfigField('apiKey'));
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CREDIT_CARD,
        ];

        if ($this->company_gateway->getSofortEnabled() && $this->invitation && $this->client() && isset($this->client()->country) && in_array($this->client()->country, ['AUT', 'BEL', 'DEU', 'ITA', 'NLD', 'ESP'])) {
            $types[] = GatewayType::SOFORT;
        }

        if ($this->company_gateway->getAchEnabled()) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if ($this->company_gateway->getSepaEnabled()) {
            $types[] = GatewayType::SEPA;
        }

        if ($this->company_gateway->getBitcoinEnabled()) {
            $types[] = GatewayType::CRYPTO;
        }

        if ($this->company_gateway->getAlipayEnabled()) {
            $types[] = GatewayType::ALIPAY;
        }

        if ($this->company_gateway->getApplePayEnabled()) {
            $types[] = GatewayType::APPLE_PAY;
        }

        return $types;
    }

    public function viewForType($gateway_type_id)
    {
        switch ($gateway_type_id) {
            case GatewayType::CREDIT_CARD:
                return 'gateways.stripe.credit_card';
                break;
            case GatewayType::SOFORT:
                return 'gateways.stripe.sofort';
                break;
            case GatewayType::BANK_TRANSFER:
                return 'gateways.stripe.ach';
                break;
            case GatewayType::SEPA:
                return 'gateways.stripe.sepa';
                break;
            case GatewayType::CRYPTO:
            case GatewayType::ALIPAY:
            case GatewayType::APPLE_PAY:
                return 'gateways.stripe.other';
                break;

            default:
                break;
        }
    }

    /**
     * Proxy method to pass the data into payment method authorizeView().
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Processes the gateway response for credit card authorization.
     *
     * @param \Illuminate\Http\Request $request The returning request object

     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    /**
     * Process the payment with gateway.
     *
     * @param array $data

     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Payment Intent Reponse looks like this.
      +"id": "pi_1FMR7JKmol8YQE9DuC4zMeN3"
      +"object": "payment_intent"
      +"allowed_source_types": array:1 [▼
        0 => "card"
      ]
      +"amount": 2372484
      +"canceled_at": null
      +"cancellation_reason": null
      +"capture_method": "automatic"
      +"client_secret": "pi_1FMR7JKmol8YQE9DuC4zMeN3_secret_J3yseWJG6uV0MmsrAT1FlUklV"
      +"confirmation_method": "automatic"
      +"created": 1569381877
      +"->currency()": "usd"
      +"description": "[3]"
      +"last_payment_error": null
      +"livemode": false
      +"next_action": null
      +"next_source_action": null
      +"payment_method": "pm_1FMR7ZKmol8YQE9DQWqPuyke"
      +"payment_method_types": array:1 [▶]
      +"receipt_email": null
      +"setup_future_usage": "off_session"
      +"shipping": null
      +"source": null
      +"status": "succeeded"
     */
    public function processPaymentResponse($request) //We never have to worry about unsuccessful payments as failures are handled at the front end for this driver.
    {
        return $this->payment_method->paymentResponse($request);
    }

    /**
     * Creates a new String Payment Intent.
     *
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createPaymentIntent($data): ?PaymentIntent
    {
        $this->init();

        return PaymentIntent::create($data);
    }

    /**
     * Returns a setup intent that allows the user
     * to enter card details without initiating a transaction.
     *
     * @return \Stripe\SetupIntent
     */
    public function getSetupIntent(): SetupIntent
    {
        $this->init();

        return SetupIntent::create();
    }

    /**
     * Returns the Stripe publishable key.
     * @return null|string The stripe publishable key
     */
    public function getPublishableKey(): ?string
    {
        return $this->company_gateway->getPublishableKey();
    }

    /**
     * Finds or creates a Stripe Customer object.
     *
     * @return null|\Stripe\Customer A Stripe customer object
     */
    public function findOrCreateCustomer(): ?\Stripe\Customer
    {
        $customer = null;

        $this->init();

        $client_gateway_token = ClientGatewayToken::whereClientId($this->client->id)->whereCompanyGatewayId($this->company_gateway->id)->first();

        if ($client_gateway_token && $client_gateway_token->gateway_customer_reference) {
            $customer = \Stripe\Customer::retrieve($client_gateway_token->gateway_customer_reference);
        } else {
            $data['name'] = $this->client->present()->name();
            $data['phone'] = $this->client->present()->phone();

            if (filter_var($this->client->present()->email(), FILTER_VALIDATE_EMAIL)) {
                $data['email'] = $this->client->present()->email();
            }

            $customer = \Stripe\Customer::create($data);
        }

        if (!$customer) {
            throw new \Exception('Unable to create gateway customer');
        }

        return $customer;
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $response = $this->stripe
            ->refunds
            ->create(['charge' => $payment->transaction_reference, 'amount' => $amount]);

        // $response = $this->gateway
        //     ->refund(['transactionReference' => $payment->transaction_reference, 'amount' => $amount, 'currency' => $payment->client->getCurrencyCode()])
        //     ->send();

        if ($response->status == $response::STATUS_SUCCEEDED) {
            SystemLogger::dispatch(['server_response' => $response, 'data' => request()->all(),
            ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->client);

            return [
                'transaction_reference' => $response->charge,
                'transaction_response' => json_encode($response),
                'success' => $response->status == $response::STATUS_SUCCEEDED ? true : false,
                'description' => $response->metadata,
                'code' => $response,
            ];
        }

        SystemLogger::dispatch(['server_response' => $response, 'data' => request()->all(),
        ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->client);

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => $response->failure_reason,
            'code' => 422,
        ];
    }

    public function verificationView(ClientGatewayToken $payment_method)
    {
        return $this->payment_method->verificationView($payment_method);
    }

    public function processVerification(Request $request, ClientGatewayToken $payment_method)
    {
        return $this->payment_method->processVerification($request, $payment_method);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Company $company, CompanyGateway $company_gateway, Payment $payment)
    {
        if ($request->type == 'source.chargable') {
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();
        }

        return response([], 200);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return (new Charge($this))->tokenBilling($cgt, $payment_hash);
    }

    /**
     * Creates a payment record for the given
     * data array.
     *
     * @param  array $data   An array of payment attributes
     * @param  float $amount The amount of the payment
     * @return Payment       The payment object
     */
    public function createPaymentRecord($data, $amount): ?Payment
    {
        $payment = PaymentFactory::create($this->client->company_id, $this->client->user_id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->company_gateway->id;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->gateway_type_id = $data['gateway_type_id'];
        $payment->type_id = $data['type_id'];
        $payment->currency_id = $this->client->getSetting('currency_id');
        $payment->date = Carbon::now();
        $payment->transaction_reference = $data['transaction_reference'];
        $payment->amount = $amount;
        $payment->save();

        return $payment->service()->applyNumber()->save();
    }

    /**
     * Attach Stripe payment method to Stripe client. 
     * 
     * @param string $payment_method 
     * @param mixed $customer 
     * 
     * @return void 
     */
    public function attach(string $payment_method, $customer): void
    {
        try {
            $stripe_payment_method = $this->getStripePaymentMethod($payment_method);
            $stripe_payment_method->attach(['customer' => $customer->id]);
        }
        catch(\Stripe\Exception\ApiErrorException | \Exception $e) {
            $this->processInternallyFailedPayment($this, $e);
        }
    }

    /**
     * Detach payment method from the Stripe.
     * https://stripe.com/docs/api/payment_methods/detach
     * 
     * @param \App\Models\ClientGatewayToken $token 
     * @return bool 
     */
    public function detach(ClientGatewayToken $token)
    {
        $stripe = new \Stripe\StripeClient(
            $this->company_gateway->getConfigField('apiKey')
        );

        try {
            $response = $stripe->paymentMethods->detach($token->token);
        } catch (\Exception $e) {
            SystemLogger::dispatch([
                'server_response' => $e->getMessage(), 'data' => request()->all(),
            ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->client);
        }
    }
    
    public function getCompanyGatewayId(): int
    {
        return $this->company_gateway->id;
    }

    /**
     * Retrieve payment method from Stripe.
     * 
     * @param string $source 
     *
     * @return \Stripe\PaymentMethod|void 
     */
    public function getStripePaymentMethod(string $source)
    {
        try {
            return \Stripe\PaymentMethod::retrieve($source);
        } catch (\Stripe\Exception\ApiErrorException | \Exception $e) {
            return $this->processInternallyFailedPayment($this, $e);
        }
    }
}
