<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Payment;

class PaymentTypeTransformer extends EntityTransformer
{
    public function transform(Payment $payment)
    {
        return [
            'name' => $payment->translatedType()
        ];
    }
}
