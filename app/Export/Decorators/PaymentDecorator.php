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

namespace App\Export\Decorators;

use App\Models\Payment;

class PaymentDecorator extends Decorator implements DecoratorInterface{

    private $key = 'payment';

    public function transform(string $key, $payment): string
    {
        $index = $this->getKeyPart(0,$key);
    
        // match($index)
        return 'Payment Decorator';
    }

    

}