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

namespace App\DataMapper;

class FeesAndLimits
{
    public $min_limit = -1; //equivalent to null

    public $max_limit = -1; //equivalent to null

    public $fee_amount = 0;

    public $fee_percent = 0;

    public $fee_tax_name1 = '';

    public $fee_tax_name2 = '';

    public $fee_tax_name3 = '';

    public $fee_tax_rate1 = 0;

    public $fee_tax_rate2 = 0;

    public $fee_tax_rate3 = 0;

    public $fee_cap = 0;

    public $adjust_fee_percent = false;

    public $is_enabled = true;

    //public $gateway_type_id = 1;

    public static $casts = [
        'is_enabled' => 'bool',
        'gateway_type_id' => 'int',
        'min_limit' => 'float',
        'max_limit' => 'float',
        'fee_amount' => 'float',
        'fee_percent' => 'float',
        'fee_tax_name1' => 'string',
        'fee_tax_name2' => 'string',
        'fee_tax_name3' => 'string',
        'fee_tax_rate1' => 'float',
        'fee_tax_rate2' => 'float',
        'fee_tax_rate3' => 'float',
        'fee_cap' => 'float',
        'adjust_fee_percent' => 'bool',
    ];
}
