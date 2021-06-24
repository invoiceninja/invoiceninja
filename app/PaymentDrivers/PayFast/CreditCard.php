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
        $data = [
            'merchant_id' => $this->payfast->company_gateway->getConfigField('merchantId'),
            'merchant_key' => $this->payfast->company_gateway->getConfigField('merchantKey'),
            'return_url' => route('client.payment_methods.index'),
            'cancel_url' => route('client.payment_methods.index'),
            'notify_url' => $this->company_gateway->webhookUrl(),
            'amount' => 0,
            'item_name' => 'pre-auth',
            'subscription_type' => 2,
            'passphrase' => $this->payfast->company_gateway->getConfigField('passphrase'),
        ];        

        $data['signature'] = $this->payfast->generateSignature($data);
        $data['gateway'] = $this->payfast;
        $data['payment_endpoint_url'] = $this->payfast->endpointUrl();

        return render('gateways.payfast.authorize', $data);
    }

 	public function authorizeResponse($request)
 	{
   
    
 	}  




}

