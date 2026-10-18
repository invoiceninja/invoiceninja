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

namespace App\PaymentDrivers;

use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\PayPal\PayPalBasePaymentDriver;

class PayPalPPCPPaymentDriver extends PayPalBasePaymentDriver
{
    use MakesHash;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL_PPCP;

    public function processPaymentView($data)
    {
        $data = $this->processPaymentViewData($data);

        if ($this->gateway_type_id == 29) {
            return render('gateways.paypal.ppcp.card', $data);
        }

        return render('gateways.paypal.ppcp.pay', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function processPaymentViewData(array $data): array
    {
        $data = parent::processPaymentViewData($data);
        $data['merchantId'] = $this->company_gateway->getConfigField('merchantId');

        return $data;
    }

    protected function beforeCheckout(): void
    {
        $this->checkPaymentsReceivable();
    }

    protected function resolveSdkClientId(): string
    {
        return config('ninja.paypal.client_id');
    }

    protected function getCheckoutIdentifier(): string
    {
        return 's:INN_' . $this->company_gateway->getConfigField('merchantId') . '_CHCK';
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function enrichPurchaseUnit(array &$unit): void
    {
        $unit['payee'] = [
            'merchant_id' => $this->company_gateway->getConfigField('merchantId'),
        ];
        $unit['payment_instruction'] = [
            'disbursement_mode' => 'INSTANT',
        ];
    }

    private function checkPaymentsReceivable(): self
    {
        if ($this->company_gateway->getConfigField('status') != 'activated') {
            if (class_exists(\Modules\Admin\Services\PayPal\PayPalService::class)) {
                $pp = new \Modules\Admin\Services\PayPal\PayPalService($this->company_gateway->company, $this->company_gateway->user);
                $pp->updateMerchantStatus($this->company_gateway);

                $this->company_gateway = $this->company_gateway->fresh();
                $config = $this->company_gateway->getConfig();

                if ($config->status == 'activated') {
                    return $this;
                }
            }

            throw new PaymentFailed('Unable to accept payments at this time, please contact PayPal for more information.', 401);
        }

        return $this;
    }

    public function livewirePaymentView(array $data): string
    {
        if ($this->gateway_type_id == 29) {
            return 'gateways.paypal.ppcp.card_livewire';
        }

        return 'gateways.paypal.ppcp.pay_livewire';
    }
}
