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
 * App\Models\BankIntegration
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int $user_id
 * @property string $integration_type
 * @property string $provider_name
 * @property int $provider_id
 * @property int $bank_account_id
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_status
 * @property string|null $bank_account_type
 * @property float $balance
 * @property int|null $currency
 * @property string $nickname
 * @property string $nordigen_account_id
 * @property string $nordigen_institution_id
 * @property string|null $from_date
 * @property bool $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $disabled_upstream
 * @property bool $auto_sync
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\Company $company
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\BankIntegrationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankIntegration withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransaction> $transactions
 * @mixin \Eloquent
 */
class BankIntegration extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'bank_account_name',
        'provider_name',
        'bank_account_number',
        'bank_account_status',
        'bank_account_type',
        'balance',
        'currency',
        'from_date',
        'auto_sync',
    ];

    public const INTEGRATION_TYPE_YODLEE = 'YODLEE';

    public const INTEGRATION_TYPE_NORDIGEN = 'NORDIGEN';

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
