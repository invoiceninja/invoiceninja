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

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\GatewayType;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstantBankPay implements MethodInterface, LivewireMethodInterface
{
    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    /**
     * Authorization page for Instant Bank Pay.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     * @throws BindingResolutionException
     */
    public function authorizeView(array $data): RedirectResponse
    {
        return redirect()->back();
    }

    /**
     * Handle authorization for Instant Bank Pay.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws BindingResolutionException
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->back();
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return redirect()->away($data['authorisation_url']);
    }

    public function paymentResponse(PaymentResponseRequest $request): never
    {
        throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.gocardless.instant_bank_pay.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->go_cardless;
        $data['amount'] = $this->go_cardless->convertToGoCardlessAmount($data['total']['amount_with_fee'], $this->go_cardless->client->currency()->precision);
        $data['currency'] = $this->go_cardless->client->getCurrencyCode();
        $data['authorisation_url'] = (new HostedPaymentPage($this->go_cardless))
            ->start(GatewayType::INSTANT_BANK_PAY);

        return $data;
    }
}
