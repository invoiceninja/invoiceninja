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

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\Request;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class Checkout implements MethodInterface
{
    protected MolliePaymentDriver $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /**
     * Show the authorization page for checkout portal.
     * 
     * @param array $data 
     * @return View 
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.mollie.checkout.authorize', $data);
    }

    /**
     * Handle authorization with checkout portal.
     * 
     * @param Request $request 
     * @return RedirectResponse 
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->back();
    }

    /**
     * Handle payment page for the checkout portal.
     * 
     * @param array $data 
     * @return Redirector|RedirectResponse|void 
     */
    public function paymentView(array $data)
    {        
        $amount = $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee);

        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::CUSTOM)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $payment = $this->mollie->gateway->payments->create([
                "amount" => [
                    "currency" => $this->mollie->client->currency()->code,
                    "value" => $amount,
                ],
                "description" => \sprintf('Hash: %s', $this->mollie->payment_hash->hash),
                "redirectUrl" => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ]),
                "webhookUrl" => $this->mollie->company_gateway->webhookUrl(),
                "metadata" => [
                    "client_id" => $this->mollie->client->hashed_id,
                ],
            ]);

            $this->mollie->payment_hash->withData('payment_id', $payment->id);

            return redirect($payment->getCheckoutUrl());
        } catch(\Exception $e) {
            return $this->processUnsuccessfulPayment($e);
        }
    }

    public function processUnsuccessfulPayment(\Exception $e)
    {
        PaymentFailureMailer::dispatch(
            $this->mollie->client,
            $e->getMessage(),
            $this->mollie->client->company,
            $this->mollie->payment_hash->data->amount_with_fee
        );

        SystemLogger::dispatch(
            $e->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        throw new PaymentFailed($e->getMessage(), $e->getCode());
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        if (! \property_exists('payment_id', $this->mollie->payment_hash->data)) {
            throw new PaymentFailed('Missing [payment_id] property. Please contact administrator.');
        }
    }
}
