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

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\GatewayType;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Revolut card field embedded in the client portal.
 *
 * The order is created before the page renders (the card field mounts on
 * the order's public token). When the merchant allows saving cards, the
 * order carries a Revolut customer and the card is saved for
 * merchant-initiated charges, so recurring invoices can be auto-billed.
 *
 * @see https://developer.revolut.com/docs/guides/merchant/accept-payments/online-payments/card-field/web
 */
class CreditCard implements MethodInterface, LivewireMethodInterface
{
    private const SETUP_ORDER_SESSION_KEY = 'revolut_setup_order';

    public function __construct(protected RevolutPaymentDriver $revolut)
    {
    }

    /**
     * Add a card from the portal without paying: the card field mounts on a
     * zero-amount order and the card is saved for merchant use.
     */
    public function authorizeView(array $data): View
    {
        $customer_id = $this->revolut->revolutCustomerId();

        if (! $customer_id) {
            throw new PaymentFailed(ctrans('texts.provide_email'));
        }

        $order = $this->revolut->createSetupOrder($customer_id);

        session()->put(self::SETUP_ORDER_SESSION_KEY, ['order_id' => $order['id'], 'customer_id' => $customer_id]);

        $data['gateway'] = $this->revolut;
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;

        return render('gateways.revolut.credit_card.authorize', array_merge($data, $this->widgetData($order['token'])));
    }

    /**
     * The card field saved the card: check the setup order is the one this
     * session created, then store the card.
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        $setup = session()->pull(self::SETUP_ORDER_SESSION_KEY);

        $order = isset($setup['order_id']) ? $this->revolut->api()->retrieveOrder($setup['order_id']) : null;

        if (! $order || ($order['customer']['id'] ?? null) !== ($setup['customer_id'] ?? null) || (int) ($order['amount'] ?? -1) !== 0) {
            throw new PaymentFailed(ctrans('texts.payment_error'));
        }

        $order = $this->revolut->awaitOrder($order);

        $token = $this->revolut->storeSavedPaymentMethod($order, GatewayType::CREDIT_CARD);

        if (! $token) {
            throw new PaymentFailed(ctrans('texts.payment_error'));
        }

        return redirect()->route('client.payment_methods.show', $token->hashed_id);
    }

    public function paymentData(array $data): array
    {
        $order = $this->revolut->pageOrder(GatewayType::CREDIT_CARD);

        if (empty($order['token'])) {
            $this->revolut->failPayment('Revolut did not return an order token', $order);
        }

        $data['gateway'] = $this->revolut;

        return array_merge($data, $this->widgetData($order['token']));
    }

    /**
     * What the card field needs to mount on an order.
     */
    private function widgetData(string $order_token): array
    {
        $contact = $this->revolut->getContact();

        return [
            'order_token' => $order_token,
            'revolut_mode' => $this->revolut->api()->mode(),
            'embed_url' => $this->revolut->api()->embedScriptUrl(),
            'cardholder_name' => $contact ? trim($contact->first_name . ' ' . $contact->last_name) : $this->revolut->client->present()->name(),
            'customer_email' => $contact->email ?? '',
        ];
    }

    public function paymentView(array $data): View
    {
        return render('gateways.revolut.credit_card.pay', $this->paymentData($data));
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.revolut.credit_card.pay_livewire';
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        return $this->revolut->completePortalPayment($request, GatewayType::CREDIT_CARD);
    }
}
