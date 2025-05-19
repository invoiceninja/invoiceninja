<?php

namespace App\PaymentDrivers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
 
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\GiftUp\GiftUp;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\PaymentDrivers\Forte\CreditCard;
use Illuminate\Http\Request;

class GiftUpPaymentDriver extends BaseDriver
{ 
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::GIFTUP => GiftUp::class, //maps GatewayType => Implementation class
         
    ];

 
    public $api_key  = "";
    public $test_mode = ""; 
    public $webhook_url = "";
  


    public function init()
    { 
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->test_mode = $this->company_gateway->getConfigField('testMode'); 
        $this->webhook_url = $this->company_gateway->webhookUrl();
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];
        $types[] = GatewayType::GIFTUP;

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

   

    public function processPaymentResponse($request)
    {
        
        return $this->payment_method->paymentResponse($request);
    }

    public function processWebhookRequest(Request $request)
    { 
       
        if ($request->input('test') === true) {
            return response()->json(['message' => 'Test webhook received.'], 200);
        }
       

    }

    private function failedPaymentNotification(Payment $payment): void
    {

        $error = ctrans('texts.client_payment_failure_body', [
            'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
            'amount' => array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total, ]);

        PaymentFailedMailer::dispatch(
            $this->payment_hash,
            $payment->client->company,
            $payment->client,
            $error
        );

    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    return false;
    }
}
