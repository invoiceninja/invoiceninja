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

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankSubaccount.
 */
class BankSubaccount extends BaseModel
{
    use SoftDeletes;

    /**
     * @var array
     */
    /**
     * @return BelongsTo
     */
    public function bank_account()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
