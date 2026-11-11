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
 * Revolut Pay button embedded in the client portal.
 *
 * Like the card field, the button pays an order created before the page
 * renders. When the merchant allows saving payment methods and the client
 * agrees, the Revolut Pay account is saved for merchant-initiated charges
 * (savePaymentMethodForMerchant), for auto-billing and to pay later invoices
 * from the portal. On mobile Revolut Pay redirects instead of opening a
 * pop-up, so the response also arrives on the GET response route.
 *
 * @see https://developer.revolut.com/docs/guides/merchant/accept-payments/online-payments/revolut-pay/web
 */
class RevolutPay implements MethodInterface, LivewireMethodInterface
{
    public function __construct(protected RevolutPaymentDriver $revolut)
    {
    }

    /**
     * Revolut Pay accounts are saved when paying an invoice.
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.revolut.revolut_pay.authorize', $data);
    }

    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentData(array $data): array
    {
        $public_key = trim((string) $this->revolut->company_gateway->getConfigField('publicKey'));

        if ($public_key === '') {
            $this->revolut->failPayment('Revolut Pay needs the public API key in the gateway settings.');
        }

        $order = $this->revolut->pageOrder(GatewayType::REVOLUT_PAY);

        if (empty($order['token'])) {
            $this->revolut->failPayment('Revolut did not return an order token', $order);
        }

        $data['gateway'] = $this->revolut;
        $data['public_key'] = $public_key;
        $data['order_token'] = $order['token'];
        $data['order_amount'] = $order['amount'];
        $data['order_currency'] = $order['currency'];
        $data['revolut_mode'] = $this->revolut->api()->mode();
        $data['embed_url'] = $this->revolut->api()->embedScriptUrl();
        $data['redirect_url'] = route('client.payments.response.get', [
            'company_gateway_id' => $this->revolut->company_gateway->id,
            'payment_hash' => $this->revolut->payment_hash->hash,
            'payment_method_id' => GatewayType::REVOLUT_PAY,
        ]);

        return $data;
    }

    public function paymentView(array $data): View
    {
        return render('gateways.revolut.revolut_pay.pay', $this->paymentData($data));
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.revolut.revolut_pay.pay_livewire';
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        return $this->revolut->completePortalPayment($request, GatewayType::REVOLUT_PAY);
    }
}
