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

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankSubaccount.
 *
 * @property-read \App\Models\BankAccount|null $bank_account
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankSubaccount withoutTrashed()
 * @mixin \Eloquent
 */
class BankSubaccount extends BaseModel
{
    use SoftDeletes;

    /**
     * @return BelongsTo
     */
    public function bank_account()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
