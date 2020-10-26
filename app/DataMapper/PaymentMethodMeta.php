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

namespace App\DataMapper;

class PaymentMethodMeta
{
    /** @var string */
    public $exp_month;

    /** @var string */
    public $exp_year;

    /** @var string */
    public $brand;

    /** @var string */
    public $last4;

    /** @var int */
    public $type;
}
