<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

/**
 * Class PaymentLibrary.
 */
class PaymentLibrary extends BaseModel
{
    protected $casts = [
        'visible' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];
}
