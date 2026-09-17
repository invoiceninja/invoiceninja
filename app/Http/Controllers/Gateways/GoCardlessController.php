<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gateways\GoCardless\HostedPaymentPageReturnRequest;
use App\Http\Requests\Gateways\GoCardless\HostedPaymentPageSetupReturnRequest;
use App\PaymentDrivers\GoCardless\HostedPaymentPage;
use Illuminate\Http\RedirectResponse;

class GoCardlessController extends Controller
{
    public function hostedPaymentPageReturn(HostedPaymentPageReturnRequest $request): RedirectResponse
    {
        $payment_hash = $request->getPaymentHash();
        $invoice_id = data_get($payment_hash?->data, 'invoices.0.invoice_id');
        $gateway_type_id = (int) data_get($payment_hash->data, 'gocardless.gateway_type_id');
        $driver = $request->getCompanyGateway()
            ->driver($request->getClient())
            ->setPaymentMethod($gateway_type_id)
            ->setPaymentHash($payment_hash);
        $service = new HostedPaymentPage($driver);
        $billing_request_id = data_get($payment_hash->data, 'gocardless.billing_request');
        $billing_request = $service->get($billing_request_id);
        $payment = $service->complete($billing_request);

        if ($payment) {
            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
        }

        return redirect()
            ->route('client.invoice.show', ['invoice' => $invoice_id])
            ->with('message', ctrans('texts.processing'));
    }

    public function hostedPaymentPageSetupReturn(
        HostedPaymentPageSetupReturnRequest $request,
        string $company_key,
        string $company_gateway_id,
        string $context,
    ): RedirectResponse {
        $state = $request->getContextState();
        $contact = $request->getContact();
        $company_gateway = $request->getCompanyGateway();
        $driver = $company_gateway
            ->driver($contact->client)
            ->setPaymentMethod((int) $state['gateway_type_id']);
        $billing_request_id = data_get($state, 'gocardless.billing_request');
        $service = new HostedPaymentPage($driver, $context, false);
        $billing_request = $service->get($billing_request_id);
        $payment_method = $service->completeSetup($billing_request);

        return $payment_method
            ? redirect()->route('client.payment_methods.show', ['payment_method' => $payment_method->hashed_id])
            : redirect()->route('client.payment_methods.index')->with('message', ctrans('texts.processing'));
    }
}
