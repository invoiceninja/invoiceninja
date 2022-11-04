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

use Illuminate\Database\Eloquent\SoftDeletes;

class BankIntegration extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'bank_account_name',
        'provider_name',
        'bank_account_number',
        'bank_account_status',
        'bank_account_type',
        'balance',
        'currency',
        'nickname',
        'from_date',
    ];

    protected $dates = [
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class)->withTrashed();
    }

}