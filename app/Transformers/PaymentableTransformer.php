<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Credit;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;

class PaymentableTransformer extends EntityTransformer
{
    use MakesHash;

    protected $serializer;

    protected $defaultIncludes = [];

    protected $availableIncludes = [];

    public function __construct($serializer = null)
    {
        $this->serializer = $serializer;
    }

    public function transform(Paymentable $paymentable)
    {
        $entity_key = 'invoice_id';

        if ($paymentable->paymentable_type == Credit::class) {
            $entity_key = 'credit_id';
        }

        return  [
            'id' => $this->encodePrimaryKey($paymentable->id),
            $entity_key => $this->encodePrimaryKey($paymentable->paymentable_id),
            'amount' => (float) $paymentable->amount,
            'refunded' => (float) $paymentable->refunded,
            'created_at' => (int) $paymentable->created_at,
            'updated_at' => (int) $paymentable->updated_at,
        ];
    }
}
