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
use Illuminate\Http\RedirectResponse;

class BrowserPay implements MethodInterface
{
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

    public function paymentView(array $data) { }

    public function paymentResponse(PaymentResponseRequest $request) { }
}