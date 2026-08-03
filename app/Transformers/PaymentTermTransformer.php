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

namespace App\Transformers;

use App\Models\PaymentTerm;
use App\Utils\Traits\MakesHash;

class PaymentTermTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(PaymentTerm $payment_term)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($payment_term->id),
            'num_days' => (int) $payment_term->num_days,
            'cash_discount_days' => $payment_term->cash_discount_days !== null ? (int) $payment_term->cash_discount_days : null,
            'cash_discount_percent' => $payment_term->cash_discount_percent !== null ? (float) $payment_term->cash_discount_percent : null,
            'name' => (string) ($payment_term->cash_discount_percent !== null ? ctrans('texts.payment_terms_net_cash_discount', [
                'num_days' => $payment_term->getNumDays(),
                'cash_discount_percent' => $payment_term->cash_discount_percent,
                'cash_discount_days' => $payment_term->cash_discount_days,
            ]) : ctrans('texts.payment_terms_net') . ' ' . $payment_term->getNumDays()),
            'is_deleted' => (bool) $payment_term->is_deleted,
            'created_at' => (int) $payment_term->created_at,
            'updated_at' => (int) $payment_term->updated_at,
            'archived_at' => (int) $payment_term->deleted_at,
        ];
    }
}
