<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyUser extends Pivot
{
    protected $guarded = ['id'];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'collection',
        'permissions' => 'object',
    ];

    public function account()
    {
        return $this->hasOne(Account::class);
    }

    public function user_pivot()
    {
        return $this->hasOne(User::class)->withPivot('permissions', 'settings', 'is_admin', 'is_owner', 'is_locked');
    }

    public function company_pivot()
    {
    	return $this->hasOne(Company::class)->withPivot('permissions', 'settings', 'is_admin', 'is_owner', 'is_locked');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function token()
    {

        return $this->hasOneThrough(
            CompanyToken::class,
            CompanyUser::class,
            'user_id', // Foreign key on CompanyUser table...
            'company_id', // Foreign key on CompanyToken table...
            'user_id', // Local key on CompanyToken table...
            'company_id' // Local key on CompanyUser table...
        );

    }
}
