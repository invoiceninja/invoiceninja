<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripePaymentDriver extends BasePaymentDriver
{
    use MakesHash, Utilities;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    protected $customer_reference = 'customerReferenceParam';

    protected $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
        GatewayType::ALIPAY => Alipay::class,
        GatewayType::SOFORT => SOFORT::class,
        GatewayType::APPLE_PAY => 1,
        GatewayType::SEPA => 1,
    ];

    /**
     * Methods in this class are divided into
     * two separate streams
     *
     * 1. Omnipay Specific
     * 2. Stripe Specific
     *
     * Our Stripe integration is deeper than
     * other gateways and therefore
     * relies on direct calls to the API
     */
    /************************************** Stripe API methods **********************************************************/

    /**
     * Initializes the Stripe API
     * @return void
     */
    public function init(): void
    {
        Stripe::setApiKey($this->company_gateway->getConfigField('apiKey'));
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Returns the gateway types
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CREDIT_CARD,
            //GatewayType::TOKEN,
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
            case GatewayType::TOKEN:
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
     * Payment Intent Reponse looks like this
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

    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        $payment = parent::createPayment($data, $status);

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $this->convertFromStripeAmount($data['amount'], $this->client->currency()->precision);
        $payment->type_id = $data['payment_type'];
        $payment->transaction_reference = $data['payment_method'];
        $payment->client_contact_id = $client_contact_id;
        $payment->gateway_type_id = GatewayType::ALIPAY;
        $payment->save();

        return $payment;
    }

    /**
     * Creates a new String Payment Intent
     *
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createPaymentIntent($data): ?\Stripe\PaymentIntent
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
    public function getSetupIntent(): \Stripe\SetupIntent
    {
        $this->init();

        return SetupIntent::create();
    }


    /**
     * Returns the Stripe publishable key
     * @return NULL|string The stripe publishable key
     */
    public function getPublishableKey(): ?string
    {
        return $this->company_gateway->getPublishableKey();
    }

    /**
     * Finds or creates a Stripe Customer object
     *
     * @return NULL|\Stripe\Customer A Stripe customer object
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

    public function refund(Payment $payment, $amount)
    {
        $this->gateway();

        $response = $this->gateway
            ->refund(['transactionReference' => $payment->transaction_reference, 'amount' => $amount, 'currency' => $payment->client->getCurrencyCode()])
            ->send();

        if ($response->isSuccessful()) {
            SystemLogger::dispatch([
                'server_response' => $response->getMessage(), 'data' => request()->all(),
            ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->client);

            return [
                'transaction_reference' => $response->getData()['id'],
                'transaction_response' => json_encode($response->getData()),
                'success' => $response->getData()['refunded'],
                'description' => $response->getData()['description'],
                'code' => $response->getCode(),
            ];
        }

        SystemLogger::dispatch([
            'server_response' => $response->getMessage(), 'data' => request()->all(),
        ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->client);

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response->getData()),
            'success' => false,
            'description' => $response->getData()['error']['message'],
            'code' => $response->getData()['error']['code'],
        ];
    }

    public function verificationView(ClientGatewayToken $payment_method)
    {
        return $this->payment_method->verificationView($payment_method);
    }

    public function processVerification(ClientGatewayToken $payment_method)
    {
        return $this->payment_method->processVerification($payment_method);
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
    public function createPaymentRecord($data, $amount) :?Payment
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
}
