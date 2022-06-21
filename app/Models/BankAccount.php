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

use Crypt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankAccount.
 */
class BankAccount extends BaseModel
{
    use SoftDeletes;

    /**
     * @var array
     */
    /**
     * @var array
     */
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
