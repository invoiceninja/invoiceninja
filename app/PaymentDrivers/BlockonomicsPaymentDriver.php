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

    public function init()
    {
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->callback_url = $this->company_gateway->getConfigField('callbackUrl');
        // $this->setCallbackUrl();
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    // public function doCurlCall($url, $post_content = '')
    // {
    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, $url);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //     if ($post_content) {
    //         curl_setopt($ch, CURLOPT_POST, 1);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
    //     }
    //     curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //         'Authorization: Bearer ' . $this->api_key,
    //         'Content-type: application/x-www-form-urlencoded',
    //     ]);
    //     $contents = curl_exec($ch);
    //     if (curl_errno($ch)) {
    //         echo "Error:" . curl_error($ch);
    //     }
    //     $responseObj = json_decode($contents);
    //     $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //     curl_close ($ch);
    //     if ($status != 200) {
    //         echo "ERROR: " . $status . ' ' . $responseObj->message;
    //     }
    //     return $responseObj;
    // }

    // public function setCallbackUrl()
    // {
    //     $GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    //     $SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    //     $get_callback_response = $this->doCurlCall($GET_CALLBACKS_URL);

    //     $callback_url = $this->callback_url;
    //     $xpub = $get_callback_response[0]->address;
    //     $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $xpub . '"}';

    //     $responseObj = $this->doCurlCall($SET_CALLBACK_URL, $post_content);
    //     return $responseObj;
    // }

    // public function findPaymentHashInTransactionReference($transaction_reference)
    // {
    //     $pattern = '/payment hash:\s*([a-zA-Z0-9]+)/';
    //     // Perform the regex match
    //     if (preg_match($pattern,  $transaction_reference, $matches)) {
    //         // Return the matched payment hash
    //         return $matches[1];
    //     } else {
    //         // Return null if no match is found
    //         return null;
    //     }
    // }

    public function findPaymentByTxid($txid)
    {
        return Payment::whereRaw('BINARY `transaction_reference` LIKE ?', ["%txid: " . $txid])->firstOrFail();
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
        // TODO: Figure out why init does not work
        // $this->init();
        // $secret = $this->company_gateway->getConfigField('callbackSecret');
        // //Match secret for security
        // if ($_GET['secret'] != $secret) {
        //     echo "Invalid Secret";
        //     return;
        // }

        $txid = $_GET['txid'];
        $value = $_GET['value'];
        $status = $_GET['status'];
        $addr = $_GET['addr'];

        // Only accept confirmed transactions
        if ($status != 2) {
            throw new PaymentFailed('Transaction not confirmed');
        }
        
        $payment = $this->findPaymentByTxid($txid);
        // $payment_hash = $this->findPaymentHashInTransactionReference($payment->transaction_reference);

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

        // Save the updated payment status
        if ($payment->status_id != $statusId) {
            $payment->status_id = $statusId;
            $payment->save();
        }

        header('HTTP/1.1 200 OK');
        echo 'SUCCESS';
        return;
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }
}
