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

namespace App\PaymentDrivers\Braintree;

use App\Http\Requests\Request;
use App\Models\GatewayType;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;

class ACH implements MethodInterface
{
    protected BraintreePaymentDriver $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.ach.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        $request->validate([
            'nonce' => ['required'],
            'gateway_type_id' => ['required'],
        ]);

        $customer = $this->braintree->findOrCreateCustomer();

        $result = $this->braintree->gateway->paymentMethod()->create([
            'customerId' => $customer->id,
            'paymentMethodNonce' => $request->nonce,
            'options' => [
                'usBankAccountVerificationMethod' => \Braintree\Result\UsBankAccountVerification::NETWORK_CHECK,
            ],
        ]);

        if ($result->success) {
            $account = $result->paymentMethod;

            try {
                $payment_meta = new \stdClass;
                $payment_meta->brand = (string)$account->bankName;
                $payment_meta->last4 = (string)$account->last4;
                $payment_meta->type = GatewayType::BANK_TRANSFER;
                $payment_meta->state = $account->verified ? 'authorized' : 'unauthorized';

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $account->token,
                    'payment_method_id' => $request->gateway_type_id,
                ];

                $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);

                return redirect()->route('client.payment_methods.index');
            } catch (\Exception $e) {
                // ..
            }
        }
    }
}
