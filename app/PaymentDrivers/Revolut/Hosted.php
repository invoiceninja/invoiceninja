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

namespace App\PaymentDrivers\Revolut;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\GatewayType;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Revolut hosted checkout page: the customer is sent to the order's
 * checkout_url (card, Revolut Pay, wallets, pay by bank) and comes back to
 * redirect_url. Nothing is saved for later charges.
 */
class Hosted implements MethodInterface, LivewireMethodInterface
{
    public function __construct(protected RevolutPaymentDriver $revolut)
    {
    }

    public function authorizeView(array $data): View
    {
        return render('gateways.revolut.hosted.authorize', $data);
    }

    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentData(array $data): array
    {
        $order = $this->revolut->pageOrder(GatewayType::HOSTED_PAGE, [
            'redirect_url' => route('client.payments.response.get', [
                'company_gateway_id' => $this->revolut->company_gateway->id,
                'payment_hash' => $this->revolut->payment_hash->hash,
                'payment_method_id' => GatewayType::HOSTED_PAGE,
            ]),
        ]);

        if (empty($order['checkout_url'])) {
            $this->revolut->failPayment('Revolut did not return a checkout URL', $order);
        }

        $data['gateway'] = $this->revolut;
        $data['checkout_url'] = $order['checkout_url'];
        $data['order_id'] = $order['id'];

        return $data;
    }

    public function paymentView(array $data): View
    {
        return render('gateways.revolut.hosted.pay', $this->paymentData($data));
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.revolut.hosted.pay_livewire';
    }

    /**
     * The customer is back from the checkout page.
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        return $this->revolut->completeCustomerPayment();
    }
}
