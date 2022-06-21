<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\WePay\ACH;
use App\PaymentDrivers\WePay\CreditCard;
use App\PaymentDrivers\WePay\Setup;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use WePay;

class WePayPaymentDriver extends BaseDriver
{
    use MakesHash;

    /* Does this gateway support refunds? */
    public $refundable = true;

    /* Does this gateway support token billing? */
    public $token_billing = true;

    /* Does this gateway support authorizations? */
    public $can_authorise_credit_card = true;

    /* Initialized gateway */
    public $wepay;

    /* Initialized payment method */
    public $payment_method;

    /* Maps the Payment Gateway Type - to its implementation */
    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_WEPAY;

    public function init()
    {
        if (WePay::getEnvironment() == 'none') {
            if (config('ninja.wepay.environment') == 'staging') {
                WePay::useStaging(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));
            } else {
                WePay::useProduction(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));
            }
        }

        if ($this->company_gateway) {
            $this->wepay = new WePay($this->company_gateway->getConfigField('accessToken'));
        } else {
            $this->wepay = new WePay(null);
        }

        return $this;
    }

    /**
     * Return the gateway types that have been enabled
     *
     * @return array
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    /**
     * Setup the gateway
     *
     * @param  array $data user_id + company
     * @return view
     */
    public function setup(array $data)
    {
        return (new Setup($this))->boot($data);
    }

    /**
     * Set the payment method
     *
     * @param int $payment_method_id Alias of GatewayType
     */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        $this->init();

        $data['gateway'] = $this->wepay;
        $client = $data['client'];
        $contact = $client->primary_contact()->first() ? $client->primary_contact()->first() : $client->contacts->first();
        $data['contact'] = $contact;

        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        $this->init();

        return $this->payment_method->authorizeResponse($request);
    }

    public function verificationView(ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->verificationView($cgt);
    }

    public function processVerification(Request $request, ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->processVerification($request, $cgt);
    }

    public function processPaymentView(array $data)
    {
        $this->init();

        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        $this->init();

        return $this->payment_method->paymentResponse($request);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();
        $this->setPaymentMethod($cgt->gateway_type_id);
        $this->setPaymentHash($payment_hash);

        return $this->payment_method->tokenBilling($cgt, $payment_hash);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
        $this->init();

        $input = $request->all();

        $config = $this->company_gateway->getConfig();

        $accountId = $this->company_gateway->getConfigField('accountId');

        foreach (array_keys($input) as $key) {
            if ('_id' == substr($key, -3)) {
                $objectType = substr($key, 0, -3);
                $objectId = $input[$key];
                break;
            }
        }

        if (! isset($objectType)) {
            throw new \Exception('Could not find object id parameter');
        }

        if ($objectType == 'credit_card') {
            $payment_method = ClientGatewayToken::where('token', $objectId)->first();

            if (! $payment_method) {
                throw new \Exception('Unknown payment method');
            }

            $source = $this->wepay->request('credit_card', [
                'client_id'          => config('ninja.wepay.client_id'),
                'client_secret'      => config('ninja.wepay.client_secret'),
                'credit_card_id'     => (int) $objectId,
            ]);

            if ($source->state == 'deleted') {
                $payment_method->delete();
            } else {
                //$this->paymentService->convertPaymentMethodFromWePay($source, null, $paymentMethod)->save();
            }

            return 'Processed successfully';
        } elseif ($objectType == 'account') {
            if ($accountId != $objectId) {
                throw new \Exception('Unknown account '.$accountId.' does not equal '.$objectId);
            }

            $wepayAccount = $this->wepay->request('account', [
                'account_id'     => (int) $objectId,
            ]);

            if ($wepayAccount->state == 'deleted') {
                $this->company_gateway->delete();
            } else {
                $config->state = $wepayAccount->state;
                $this->company_gateway->setConfig($config);
                $this->company_gateway->save();
            }

            return ['message' => 'Processed successfully'];
        } elseif ($objectType == 'checkout') {
            $payment = Payment::where('company_id', $this->company_gateway->company_id)
                              ->where('transaction_reference', '=', $objectId)
                              ->first();

            if (! $payment) {
                throw new Exception('Unknown payment');
            }

            if ($payment->is_deleted) {
                throw new \Exception('Payment is deleted');
            }

            $checkout = $this->wepay->request('checkout', [
                'checkout_id' => intval($objectId),
            ]);

            if ($checkout->state == 'captured') {
                $payment->status_id = Payment::STATUS_COMPLETED;
                $payment->save();
            } elseif ($checkout->state == 'cancelled') {
                $payment->service()->deletePayment()->save();
            } elseif ($checkout->state == 'failed') {
                $payment->status_id = Payment::STATUS_FAILED;
                $payment->save();
            }

            return 'Processed successfully';
        } else {
            return 'Ignoring event';
        }

        return true;
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $response = $this->wepay->request('checkout/refund', [
            'checkout_id'   => $payment->transaction_reference,
            'refund_reason' => 'Refund by merchant',
            'amount'        => $amount,
        ]);

        return [
            'transaction_reference' => $response->checkout_id,
            'transaction_response' => json_encode($response),
            'success' => $response->state == 'refunded' ? true : false,
            'description' => 'refund',
            'code' => 0,
        ];
    }

    public function detach(ClientGatewayToken $token)
    {
        /*Bank accounts cannot be deleted - only CC*/
        if ($token->gateway_type_id == 2) {
            return true;
        }

        $this->init();

        $response = $this->wepay->request('/credit_card/delete', [
            'client_id'          => config('ninja.wepay.client_id'),
            'client_secret'      => config('ninja.wepay.client_secret'),
            'credit_card_id'     => intval($token->token),
        ]);

        if ($response->state == 'deleted') {
            return true;
        } else {
            throw new \Exception(trans('texts.failed_remove_payment_method'));
        }
    }

    public function getClientRequiredFields(): array
    {
        $fields = [
            ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required'],
        ];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_name) {
            $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_email) {
            $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_address_line_2', 'label' => ctrans('texts.address2'), 'type' => 'text', 'validation' => 'nullable'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_shipping_address_line_2', 'label' => ctrans('texts.shipping_address2'), 'type' => 'text', 'validation' => 'sometimes'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }
}
