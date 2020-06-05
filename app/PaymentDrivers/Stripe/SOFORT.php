<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Stripe;

use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;

class SOFORT
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl($data);

        return render('gateways.stripe.sofort.pay', $data);
    }

    private function buildReturnUrl($data): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'gateway_type_id' => GatewayType::SOFORT,
            'hashed_ids' => implode(",", $data['hashed_ids']),
            'amount' => $data['amount'],
            'fee' => $data['fee'],
        ]);
    }

    public function paymentResponse($request)
    {
        $state = array_merge($request->all(), []);

        if ($request->redirect_status == 'succeeded') {
            return $this->processSuccessfulPayment($state);
        }

        return $this->processUnsuccessfulPayment($state);
    }

    public function processSuccessfulPayment($state)
    {
        // ..
    }

    public function processUnsuccessfulPayment($state)
    {
        return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_voided'));
    }
}
