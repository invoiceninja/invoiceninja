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

namespace App\PaymentDrivers\Razorpay;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\RazorpayPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class Hosted implements MethodInterface
{
    protected RazorpayPaymentDriver $razorpay;

    public function __construct(RazorpayPaymentDriver $razorpay)
    {
        $this->razorpay = $razorpay;

        $this->razorpay->init();
    }

    /**
     * Show the authorization page for Razorpay.
     *
     * @param array $data
     * @return View
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.razorpay.hosted.authorize', $data);
    }

    /**
     * Handle the authorization page for Razorpay.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
    }
}
