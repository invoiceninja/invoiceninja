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
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\Models\Payment;
use App\Models\Gateway;
use App\Models\ClientGatewayToken;
use App\Models\PaymentHash;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\Easymerchant\CreditCard;
use App\PaymentDrivers\Easymerchant\ACH;
use Illuminate\Support\Facades\Http;
use App\Exceptions\PaymentFailed;

class EasymerchantPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    //maps GatewayType => Implementation class
    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_EASYMERCHANT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    // public function init(): self
    // {
    //     $this->gateway = new Gateway([
    //         'environment' => $this->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production',
    //         'X-Api-Key' => $this->company_gateway->getConfigField('X-Api-Key'),
    //         'X-Api-Secret' => $this->company_gateway->getConfigField('X-Api-Secret'),
    //     ]);

    //     return $this;
    // }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        if ($this->client && $this->client->currency() && $this->client->currency()->code != 'USD') {
            return $types;    
        }

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
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
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund($payment, $amount, $return_client_response = false)
    { 
        $testMode = $this->company_gateway->getConfigField('testMode');
        if($testMode){
            $api_url = $this->company_gateway->getConfigField('test_url');
            $api_key = $this->company_gateway->getConfigField('test_api_key');
            $api_secret = $this->company_gateway->getConfigField('test_api_secret');
        }else{
            $api_url = $this->company_gateway->getConfigField('production_url');
            $api_key = $this->company_gateway->getConfigField('api_key');
            $api_secret = $this->company_gateway->getConfigField('api_secret');
        }
        
        $headers = [ 
            'Content-Type' => 'application/json',
            'X-Api-Key' => $api_key,
            'X-Api-Secret' => $api_secret 
        ];

        $data = [
            'charge_id' => $payment->transaction_reference, 
            'amount' => number_format((float)$amount , 2, '.', ''),
            'mode' => 1
        ];

        $refund_url = $api_url.'/refunds';
        if($payment->gateway_type_id == 2){
            $payment_details = PaymentHash::where('payment_id', $payment->id)->first();
            $refund_url = $api_url.'/ach/refund';
            if($payment_details){
                if($payment_details->data->status == 'paid_unsettled'){
                    $refund_url = $api_url.'/ach/cancel';
                    $data['cancel_reason'] = 'Invoice ninja cancel';
                }
            }
        }

        try {

            $response = Http::withHeaders($headers)->post($refund_url, $data);

            $result = $response->json();

            return [
                'transaction_reference' => ($result['status']) ? $result['refund_id'] : null,
                'transaction_response' => json_encode($result),
                'success' => $result['status'],
                'description' => $result['message'],
                'code' => $response?->status(),
            ];

        } catch (\Exception $e) {
            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation();
    }
}
