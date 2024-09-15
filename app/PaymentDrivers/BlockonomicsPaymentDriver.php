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

use App\Utils\Traits\MakesHash;
use App\Models\PaymentHash;
use App\Models\GatewayType;
use App\PaymentDrivers\Blockonomics\Blockonomics;
use App\Models\SystemLog;
use App\Models\Payment;
use App\Models\Gateway;
use App\Models\Client;
use App\Exceptions\PaymentFailed;
use App\Models\PaymentType;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\Invoice;

class BlockonomicsPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CRYPTO => Blockonomics::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public $blockonomics;
    public $BASE_URL = 'https://www.blockonomics.co';
    public $NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    public $PRICE_URL = 'https://www.blockonomics.co/api/price';

    public $api_key; 
    public $callback_url; 
    public $callback_secret; 

    public function init()
    {
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->callback_url = $this->company_gateway->getConfigField('callbackUrl');
        $this->callback_secret = $this->company_gateway->getConfigField('callbackSecret');
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }


    public function getPaymentByTxid($txid)
    {
        return Payment::whereRaw('BINARY `transaction_reference` LIKE ?', ["%txid: " . $txid . "%"])->firstOrFail();
    }

    public function getCallbackSecret()
    {
        $blockonomicsGatewayData = Gateway::find(64);
        $intialData = json_decode($blockonomicsGatewayData, true);
        $jsonString = $intialData['fields'];
        $blockonomicsFields = json_decode($jsonString, true);

        // Access the value of callbackSecret
        $callbackSecret = $blockonomicsFields['callbackSecret'];
        return $callbackSecret;
    }


    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CRYPTO;

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function processWebhookRequest()
    {

        $url_callback_secret = $_GET['secret'];
        $db_callback_secret = $this->getCallbackSecret();

        if ($url_callback_secret != $db_callback_secret) {
            throw new PaymentFailed('Secret does not match');
            return;
        }

        $txid = $_GET['txid'];
        $value = $_GET['value'];
        $status = $_GET['status'];
        $addr = $_GET['addr'];
                
        $payment = $this->getPaymentByTxid($txid);
        
        if (!$payment) {
            // TODO: Implement logic to create new payment in case user sends payment to the address after closing the payment page
        }

        switch ($status) {
            case 0:
                $statusId = Payment::STATUS_PENDING;
                break;
            case 1:
                $statusId = Payment::STATUS_PENDING;
                break;
            case 2:
                $statusId = Payment::STATUS_COMPLETED;
                break;
        }

        if($payment->status_id == $statusId) {
            header('HTTP/1.1 200 OK');
            echo "No change in payment status";
        } else {
            $payment->status_id = $statusId;
            $payment->save();
            header('HTTP/1.1 200 OK');
            echo "Payment status updated successfully";
        }
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }
}
