<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace App\Transformers;

use App\Models\PaymentTerm;


class PaymentTermTransformer extends EntityTransformer
{

    public function transform(PaymentTerm $payment_term)
    {
        return array_merge($this->getDefaults($payment_term), [
            'num_days'    => (int) $payment_term->num_days,
            'name'        => (string) ctrans('texts.payment_terms_net') . ' ' . $payment_term->getNumDays(),
            'created_at'  => (int)$payment_term->created_at,
            'updated_at'  => (int)$payment_term->updated_at,
            'archived_at' => (int)$payment_term->deleted_at,
        ]);
    }

}
