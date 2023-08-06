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

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\CompanyToken
 *
 * @property int $id
 * @property int $company_id
 * @property int $account_id
 * @property int $user_id
 * @property string|null $token
 * @property string|null $name
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $is_deleted
 * @property bool $is_system
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CompanyUser|null $company_user
 * @property-read \App\Models\CompanyUser|null $cu
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyToken withoutTrashed()
 * @mixin \Eloquent
 */
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

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function company_user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CompanyUser::class, 'user_id', 'user_id')
                    ->where('company_id', $this->company_id)
                    ->where('user_id', $this->user_id);
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function cu()
    {
        return $this->hasOne(CompanyUser::class, 'user_id', 'user_id')
            ->where('company_id', $this->company_id)
            ->where('user_id', $this->user_id);
    }
}
