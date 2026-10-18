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
use App\PaymentDrivers\PayPal\PayPalBasePaymentDriver;

class PayPalRestPaymentDriver extends PayPalBasePaymentDriver
{
    use MakesHash;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public function processPaymentView($data)
    {
        $data = $this->processPaymentViewData($data);

        if ($this->gateway_type_id == 29) {
            return render('gateways.paypal.ppcp.card', $data);
        }

        return render('gateways.paypal.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        if ($this->gateway_type_id == 29) {
            return 'gateways.paypal.ppcp.card_livewire';
        }

        return 'gateways.paypal.pay_livewire';
    }
}
