<?php

/**
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
