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

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFast\CreditCard;
use App\PaymentDrivers\PayFast\Token;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PayFastPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $payfast; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYFAST;

    //developer resources
    //https://sandbox.payfast.co.za/

    public function gatewayTypes(): array
    {
        $types = [];

        if($this->client->currency()->code == 'ZAR')
            $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    public function endpointUrl()
    {
        if($this->company_gateway->getConfigField('testMode'))
            return 'https://sandbox.payfast.co.za/eng/process';

        return 'https://www.payfast.co.za/eng/process';
    }

    public function init()
    {

        try{

            $this->payfast = new \PayFast\PayFastPayment(
                [
                    'merchantId' => $this->company_gateway->getConfigField('merchantId'),
                    'merchantKey' => $this->company_gateway->getConfigField('merchantKey'),
                    'passPhrase' => $this->company_gateway->getConfigField('passPhrase'),
                    'testMode' => $this->company_gateway->getConfigField('testMode')
                ]
            );

        } catch(Exception $e) {

            echo '##PAYFAST## There was an exception: '.$e->getMessage();

        }

        return $this;
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
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return false;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return (new Token($this))->tokenBilling($cgt, $payment_hash);
    }

   public function generateSignature($data)
    {
        $fields = array();

        // specific order required by PayFast
        // @see https://developers.payfast.co.za/documentation/#checkout-page
        foreach (array('merchant_id', 'merchant_key', 'return_url', 'cancel_url', 'notify_url', 'name_first',
                     'name_last', 'email_address', 'cell_number',
                    /**
                     * Transaction Details
                     */
                    'm_payment_id', 'amount', 'item_name', 'item_description',
                    /**
                     * Custom return data
                     */
                    'custom_int1', 'custom_int2', 'custom_int3', 'custom_int4', 'custom_int5',
                    'custom_str1', 'custom_str2', 'custom_str3', 'custom_str4', 'custom_str5',
                    /**
                     * Email confirmation
                     */
                    'email_confirmation', 'confirmation_address',
                    /**
                     * Payment Method
                     */
                    'payment_method',
                    /**
                     * Subscriptions
                     */
                    'subscription_type', 'billing_date', 'recurring_amount', 'frequency', 'cycles',
                    /**
                     * Passphrase for md5 signature generation
                     */
                    'passphrase') as $key) {
            if (!empty($data[$key])) {
                $fields[$key] = $data[$key];
            }
        }

        return md5(http_build_query($fields));
    }


    public function processWebhookRequest(Request $request, Payment $payment = null)
    {

        $data = $request->all();
        nlog($data);

        if(array_key_exists('m_payment_id', $data))
        {

            $hash = Cache::get($data['m_payment_id']);

            switch ($hash)
            {
                case 'cc_auth':
                    return $this->setPaymentMethod(GatewayType::CREDIT_CARD)
                                ->authorizeResponse($request);
                    break;

                default:

                    $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$data['m_payment_id']])->first();

                    return $this->setPaymentMethod(GatewayType::CREDIT_CARD)
                                ->setPaymentHash($payment_hash)
                                ->processPaymentResponse($request);
                    break;
            }


        }

        return response()->json([], 200);

    }
}
