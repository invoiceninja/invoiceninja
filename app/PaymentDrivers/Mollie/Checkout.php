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

use App\Http\Requests\Request;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class Checkout implements MethodInterface
{
    protected MolliePaymentDriver $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;
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

    public function paymentView(array $data) { }

    public function paymentResponse(PaymentResponseRequest $request) { }
}
