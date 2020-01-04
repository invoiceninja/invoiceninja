<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

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
        return  [
            'id' => $this->encodePrimaryKey($paymentable->id),
            'invoice_id' => $this->encodePrimaryKey($paymentable->paymentable_id),
            'amount' => (float)$paymentable->amount,
        ];
    }
}
