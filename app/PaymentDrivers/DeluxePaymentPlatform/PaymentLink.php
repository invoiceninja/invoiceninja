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

namespace App\PaymentDrivers\DeluxePaymentPlatform;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\Payment;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\DeluxePaymentPlatformPaymentDriver;
use Illuminate\Http\Request;

/**
 * Shared Payment Link flow: the client pays on a Deluxe-hosted page and the
 * payment is recorded by the TRANSACTION webhook, never by the browser.
 */
abstract class PaymentLink implements MethodInterface, LivewireMethodInterface
{
    /**
     * Deluxe acceptPaymentMethod value, e.g. 'Card' or 'ACH'.
     */
    abstract protected function acceptPaymentMethod(): string;

    public function __construct(public DeluxePaymentPlatformPaymentDriver $driver)
    {
    }

    public function authorizeView(array $data)
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function authorizeResponse(Request $request)
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        return redirect()->away($this->paymentLinkUrl());
    }

    /**
     * Payment Link does not post back to Invoice Ninja; if the client returns
     * here, show the payment once the webhook has recorded it.
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $payment_id = $this->driver->payment_hash->fresh()?->payment_id;

        $payment = $payment_id
            ? Payment::where('id', $payment_id)->where('client_id', $this->driver->client->id)->first()
            : null;

        if ($payment) {
            return redirect()->route('client.payments.show', ['payment' => $this->driver->encodePrimaryKey($payment->id)]);
        }

        return redirect()->route('client.invoices.index');
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->driver;
        $data['payment_link_url'] = $this->paymentLinkUrl();

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.deluxe.pay_livewire';
    }

    /**
     * Reuse the link for this payment hash so re-rendering the payment page
     * does not create a second Deluxe payment link.
     */
    private function paymentLinkUrl(): string
    {
        $hash = $this->driver->payment_hash;
        $key = 'deluxe_'.strtolower($this->acceptPaymentMethod()).'_link';

        if (! empty($hash->data->{$key})) {
            return $hash->data->{$key};
        }

        $url = $this->driver->createPaymentLink([$this->acceptPaymentMethod()]);

        $hash->withData($key, $url);

        return $url;
    }
}
