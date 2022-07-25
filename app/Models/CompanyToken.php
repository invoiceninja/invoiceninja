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

class CompanyToken extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use \Awobaz\Compoships\Compoships;

    protected $fillable = [
        'name',
    ];

    protected $with = [
        'company',
        'user',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function company_user()
    {
        return $this->hasOne(CompanyUser::class, 'user_id', 'user_id')
                    ->where('company_id', $this->company_id)
                    ->where('user_id', $this->user_id);
    }

    public function cu()
    {
        return $this->hasOne(CompanyUser::class, 'user_id', 'user_id')
            ->where('company_id', $this->company_id)
            ->where('user_id', $this->user_id);
    }
}
