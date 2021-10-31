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

namespace App\PaymentDrivers\Stripe;

use Illuminate\Http\Request;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Stripe\ApplePayDomain;
use Stripe\Exception\ApiErrorException;

class BrowserPay implements MethodInterface
{
    protected StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;

        $this->stripe->init();

        $this->ensureApplePayDomainIsValidated();
    }

    /**
     * Authorization page for browser pay.
     * 
     * @param array $data 
     * @return RedirectResponse 
     */
    public function authorizeView(array $data): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Handle the authorization for browser pay.
     * 
     * @param Request $request 
     * @return RedirectResponse 
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data): View
    {
        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->decodeUnicodeString(ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number')),
        ];

        $data['gateway'] = $this->stripe;
        $data['pi_client_secret'] = $this->stripe->createPaymentIntent($payment_intent_data)->client_secret;

        $data['payment_request_data'] = [
            'country' => $this->stripe->client->country->iso_3166_2,
            'currency' => strtolower(
                $this->stripe->client->getCurrencyCode()
            ),
            'total' => [
                'label' => $payment_intent_data['description'],
                'amount' => $payment_intent_data['amount'],
            ],
            'requestPayerName' => true,
            'requestPayerEmail' => true
        ];

        return render('gateways.stripe.browser_pay.pay', $data);   
    }

    public function paymentResponse(PaymentResponseRequest $request) {}

    /**
     * Ensure Apple Pay domain is verified.
     * 
     * @return void 
     * @throws ApiErrorException 
     */
    protected function ensureApplePayDomainIsValidated()
    {
        $config = $this->stripe->company_gateway->getConfig();

        if (property_exists($config, 'apple_pay_domain_id')) {
            return;
        }

        $domain = config('ninja.app_url');

        if (Ninja::isHosted()) {
            $domain = isset($this->stripe_driver->company_gateway->company->portal_domain)
                ? $this->stripe_driver->company_gateway->company->portal_domain
                : $this->stripe_driver->company_gateway->company->domain();
        }

        $response = ApplePayDomain::create([
            'domain_name' => $domain,
        ]);

        $config->apple_pay_domain_id = $response->id;

        $this->stripe->company_gateway->setConfig($config);
        
        $this->stripe->company_gateway->save();
    }
}