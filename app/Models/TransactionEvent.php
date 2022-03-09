<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

/**
 * Class Bank.
 */
class TransactionEvent extends StaticModel
{

    public $timestamps = false;

    public $guarded = ['id'];

    public $casts = [
        'metadata' => 'object',
        'payment_request' => 'array',
        'paymentables' => 'array',
    ];

}
