<?php

namespace App\Ninja\PaymentDrivers;

use Utils;

class SkrillPaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);
        $locale = strtoupper(Utils::getLocaleRegion());

        if (! in_array($locale, ['EN', 'DE', 'ES', 'FR', 'IT', 'PL', 'GR', 'RO', 'RU', 'TR', 'CN', 'CZ', 'NL', 'DA', 'SV', 'FI'])) {
            $locale = 'EN';
        }

        $details = [];
        foreach ($this->invoice()->invoice_items as $item) {
            $details[$item->product_key] = $item->notes;
        }

        $data['language'] = $locale;
        $data['details'] = $details;

        return $data;
    }
}
