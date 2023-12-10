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

use Crypt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankAccount.
 *
 * @property-read \App\Models\Bank|null $bank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read int|null $bank_subaccounts_count
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankAccount withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankSubaccount> $bank_subaccounts
 * @mixin \Eloquent
 */
class BankAccount extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'bank_id',
        'app_version',
        'ofx_version',
    ];

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return Crypt::decrypt($this->username);
    }

    /**
     * @param $value
     */
    public function setUsername($value)
    {
        $this->username = Crypt::encrypt($value);
    }

    /**
     * @return BelongsTo
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return HasMany
     */
    public function bank_subaccounts()
    {
        return $this->hasMany(BankSubaccount::class);
    }

    public function getEntityType()
    {
        return self::class;
    }
}
