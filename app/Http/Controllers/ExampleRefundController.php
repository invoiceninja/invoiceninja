<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Omnipay\Omnipay;

class ExampleRefundController extends Controller
{
    public function __invoke()
    {
        $gateway = Omnipay::create('PayPal_Express');

        $config = json_decode(config('ninja.testvars.paypal'));

        $gateway->setUsername($config->username);
        $gateway->setPassword($config->password);
        $gateway->setSignature($config->signature);
        $gateway->setTestMode(true);

        $response = $gateway
            ->refund(['transactionReference' => '123'])
            ->send();

        // 1) "Transaction refused because of an invalid transaction id value".
        // 2) "You do not have permission to refund this transaction".
        
        return $response->getMessage();
    }
}
