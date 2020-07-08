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

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Util\SystemLogger;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;

class SOFORT
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl($data);
        $data['stripe_amount'] = $this->stripe->convertToStripeAmount($data['amount_with_fee'], $this->stripe->client->currency()->precision);
        $data['client'] = $this->stripe->client;
        $data['country'] = $this->stripe->client->country->iso_3166_2;

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
        $amount = $state['amount'] + $state['fee'];
        $state['amount'] = $this->stripe->convertToStripeAmount($amount, $this->stripe->client->currency()->precision);

        if ($request->redirect_status == 'succeeded') {
            return $this->processSuccessfulPayment($state);
        }

        return $this->processUnsuccessfulPayment($state);
    }

    public function processSuccessfulPayment($state)
    {
        $state['charge_id'] = $state['source'];

        $this->stripe->init();

        $state['payment_type'] = PaymentType::SOFORT;

        $data = [
            'payment_method' => $state['charge_id'],
            'payment_type' => $state['payment_type'],
            'amount' => $state['amount'],
            'gateway_type_id' => GatewayType::SOFORT,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        /** @todo: https://github.com/invoiceninja/invoiceninja/pull/3789/files#r436175798 */
        if (isset($state['hashed_ids'])) {
            $this->stripe->attachInvoices($payment, $state['hashed_ids']);
        }

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $logger_message = [
            'server_response' => $state,
            'data' => $data
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client);

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($state)
    {
        return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_cancelled'));
    }
}
