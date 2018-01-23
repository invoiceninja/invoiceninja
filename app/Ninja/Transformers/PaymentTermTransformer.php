<?php

namespace App\Ninja\Transformers;

use App\Models\PaymentTerm;

/**
 * @SWG\Definition(definition="PaymentTerm", required={"payment_term_id"}, @SWG\Xml(name="PaymentTerm"))
 */
class PaymentTermTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="num_days", type="number", format="integer", example=10, readOnly=true)
     * @SWG\Property(property="name", type="string", example="Net 7")
     */

    public function __construct($account = null, $serializer = null, $paymentTerm = null)
    {
        parent::__construct($account, $serializer);

        $this->paymentTerm = $paymentTerm;
    }


    public function transform(PaymentTerm $paymentTerm)
    {
        return array_merge($this->getDefaults($paymentTerm), [
            'num_days' => (int) $paymentTerm->num_days,
            'name' => trans('texts.payment_terms_net') . ' ' . $paymentTerm->getNumDays(),
            'updated_at' => $this->getTimestamp($paymentTerm->updated_at),
            'archived_at' => $this->getTimestamp($paymentTerm->deleted_at),
            'is_default' => (bool) $paymentTerm->account_id == 0 ? true : false,
        ]);
    }
}
