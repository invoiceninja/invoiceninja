<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\PayFast;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFastPaymentDriver;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class CreditCard
{

    public $payfast;

    public function __construct(PayFastPaymentDriver $payfast)
    {
        $this->payfast = $payfast;
    }

/*
        $data = array();
        $data['merchant_id'] = $this->getMerchantId();
        $data['merchant_key'] = $this->getMerchantKey();
        $data['return_url'] = $this->getReturnUrl();
        $data['cancel_url'] = $this->getCancelUrl();
        $data['notify_url'] = $this->getNotifyUrl();

        if ($this->getCard()) {
            $data['name_first'] = $this->getCard()->getFirstName();
            $data['name_last'] = $this->getCard()->getLastName();
            $data['email_address'] = $this->getCard()->getEmail();
        }

        $data['m_payment_id'] = $this->getTransactionId();
        $data['amount'] = $this->getAmount();
        $data['item_name'] = $this->getDescription();
        $data['custom_int1'] = $this->getCustomInt1();
        $data['custom_int2'] = $this->getCustomInt2();
        $data['custom_int3'] = $this->getCustomInt3();
        $data['custom_int4'] = $this->getCustomInt4();
        $data['custom_int5'] = $this->getCustomInt5();
        $data['custom_str1'] = $this->getCustomStr1();
        $data['custom_str2'] = $this->getCustomStr2();
        $data['custom_str3'] = $this->getCustomStr3();
        $data['custom_str4'] = $this->getCustomStr4();
        $data['custom_str5'] = $this->getCustomStr5();

        if ($this->getPaymentMethod()) {
            $data['payment_method'] = $this->getPaymentMethod();
        }

        if (1 == $this->getSubscriptionType()) {
            $data['subscription_type'] = $this->getSubscriptionType();
            $data['billing_date'] = $this->getBillingDate();
            $data['recurring_amount'] = $this->getRecurringAmount();
            $data['frequency'] = $this->getFrequency();
            $data['cycles'] = $this->getCycles();
        }
        if (2 == $this->getSubscriptionType()) {
            $data['subscription_type'] = $this->getSubscriptionType();
        }

        $data['passphrase'] = $this->getParameter('passphrase'); 123456789012aV
        $data['signature'] = $this->generateSignature($data);
 */

    public function authorizeView($data)
    {
        $hash = Str::random(32);
        
        Cache::put($hash, 'cc_auth', 300);

        $data = [
            'merchant_id' => $this->payfast->company_gateway->getConfigField('merchantId'),
            'merchant_key' => $this->payfast->company_gateway->getConfigField('merchantKey'),
            'return_url' => route('client.payment_methods.index'),
            'cancel_url' => route('client.payment_methods.index'),
            'notify_url' => $this->payfast->genericWebhookUrl(),
            'm_payment_id' => $hash,
            'amount' => 5,
            'item_name' => 'pre-auth',
            'item_description' => 'Credit Card Pre Authorization',
            'subscription_type' => 2,
            'passphrase' => $this->payfast->company_gateway->getConfigField('passphrase'),
        ];        

        $data['signature'] = $this->payfast->generateSignature($data);
        $data['gateway'] = $this->payfast;
        $data['payment_endpoint_url'] = $this->payfast->endpointUrl();

        return render('gateways.payfast.authorize', $data);
    }

    /*
      'm_payment_id' => NULL,
      'pf_payment_id' => '1409993',
      'payment_status' => 'COMPLETE', 
      'item_name' => 'pre-auth',
      'item_description' => NULL,
      'amount_gross' => '5.00',
      'amount_fee' => '-2.53',
      'amount_net' => '2.47',
      'custom_str1' => NULL,
      'custom_str2' => NULL,
      'custom_str3' => NULL,
      'custom_str4' => NULL,
      'custom_str5' => NULL,
      'custom_int1' => NULL,
      'custom_int2' => NULL,
      'custom_int3' => NULL,
      'custom_int4' => NULL,
      'custom_int5' => NULL,
      'name_first' => NULL,
      'name_last' => NULL,
      'email_address' => NULL,
      'merchant_id' => '10023100',
      'token' => '34b66bc2-3c54-9590-03ea-42ee8b89922a',
      'billing_date' => '2021-07-05',
      'signature' => 'ebdb4ca937d0e3f43462841c0afc6ad9',
      'q' => '/payment_notification_webhook/EhbnVYyzJZyccY85hcHIkIzNPI2rtHzznAyyyG73oSxZidAdN9gf8BvAKDomqeHp/4openRe7Az/WPe99p3eLy',
     */
 	public function authorizeResponse($request)
 	{
        $data = $request->all();

        $cgt = [];
        $cgt['token'] = $data['token'];
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = 'xx';
        $payment_meta->exp_year = 'xx';
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = 'xxxx';
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->payfast->storeGatewayToken($cgt, []);

        return response()->json([], 200);

 	}  



    public function paymentView($data)
    {

        $payfast_data = [
            'merchant_id' => $this->payfast->company_gateway->getConfigField('merchantId'),
            'merchant_key' => $this->payfast->company_gateway->getConfigField('merchantKey'),
            'return_url' => route('client.payment_methods.index'),
            'cancel_url' => route('client.payment_methods.index'),
            'notify_url' => $this->payfast->genericWebhookUrl(),
            'm_payment_id' => $data['payment_hash'],
            'amount' => $data['amount_with_fee'],
            'item_name' => 'purchase',
            'item_description' => ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number'),
            'passphrase' => $this->payfast->company_gateway->getConfigField('passphrase'),
        ];  

        $payfast_data['signature'] = $this->payfast->generateSignature($payfast_data);
        $payfast_data['gateway'] = $this->payfast;
        $payfast_data['payment_endpoint_url'] = $this->payfast->endpointUrl();

        return render('gateways.payfast.pay', array_merge($data, $payfast_data));

    }

    /*
    [2021-07-05 11:21:24] local.INFO: array (
      'm_payment_id' => 'B7G9Q2vPhqkLEoMwwY1paXvPGuFxpbDe',
      'pf_payment_id' => '1410364',
      'payment_status' => 'COMPLETE',
      'item_name' => 'purchase',
      'item_description' => 'Invoices: ["0001"]',
      'amount_gross' => '100.00',
      'amount_fee' => '-2.30',
      'amount_net' => '97.70',
      'custom_str1' => NULL,
      'custom_str2' => NULL,
      'custom_str3' => NULL,
      'custom_str4' => NULL,
      'custom_str5' => NULL,
      'custom_int1' => NULL,
      'custom_int2' => NULL,
      'custom_int3' => NULL,
      'custom_int4' => NULL,
      'custom_int5' => NULL,
      'name_first' => NULL,
      'name_last' => NULL,
      'email_address' => NULL,
      'merchant_id' => '10023100',
      'signature' => '3ed27638479fd65cdffb0f4910679d10',
      'q' => '/payment_notification_webhook/EhbnVYyzJZyccY85hcHIkIzNPI2rtHzznAyyyG73oSxZidAdN9gf8BvAKDomqeHp/4openRe7Az/WPe99p3eLy',
    )

     */
    public function paymentResponse(Request $request)
    {
        $response_array = $request->all();

        $state = [
            'server_response' => $request->all(),
            'payment_hash' => $request->input('m_payment_id'),
        ];

        $this->payfast->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->payfast->payment_hash->save();

        if($response_array['payment_status'] == 'COMPLETE') {

            $this->payfast->logSuccessfulGatewayResponse(['response' => json_decode($response_array), 'data' => $this->payfast->payment_hash], SystemLog::TYPE_PAYFAST);

            return $this->processSuccessfulPayment();
        }
        else {
            $this->processUnsuccessfulPayment($response_array);
        }
    }
}



private function processSuccessfulPayment()
    {
        $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

        $data = [
            'payment_method' => $this->stripe->payment_hash->data->server_response->payment_method,
            'payment_type' => PaymentType::parseCardType(strtolower($stripe_method->card->brand)),
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->server_response->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => optional($this->stripe->payment_hash->data->payment_intent->charges->data[0])->id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        $this->stripe->payment_hash->save();

        if ($this->stripe->payment_hash->data->store_card) {
            $customer = new \stdClass;
            $customer->id = $this->stripe->payment_hash->data->customer;

            $this->stripe->attach($this->stripe->payment_hash->data->server_response->payment_method, $customer);

            $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

            $this->storePaymentMethod($stripe_method, $this->stripe->payment_hash->data->payment_method_id, $customer);
        }

        $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data->server_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($server_response)
    {
        PaymentFailureMailer::dispatch($this->stripe->client, $server_response->cancellation_reason, $this->stripe->client->company, $server_response->amount);

        PaymentFailureMailer::dispatch(
            $this->stripe->client,
            $server_response,
            $this->stripe->client->company,
            $server_response->amount
        );

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function storePaymentMethod(PaymentMethod $method, $payment_method_id, $customer)
    {
        try {
            $payment_meta = new \stdClass;
            $payment_meta->exp_month = (string) $method->card->exp_month;
            $payment_meta->exp_year = (string) $method->card->exp_year;
            $payment_meta->brand = (string) $method->card->brand;
            $payment_meta->last4 = (string) $method->card->last4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => $payment_method_id,
            ];

            $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }
