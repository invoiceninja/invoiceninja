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

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CompanyLedger
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $client_id
 * @property int|null $user_id
 * @property int|null $activity_id
 * @property float|null $adjustment
 * @property float|null $balance
 * @property string|null $notes
 * @property string|null $hash
 * @property int $company_ledgerable_id
 * @property string $company_ledgerable_type
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read Model|\Eloquent $company_ledgerable
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereAdjustment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereCompanyLedgerableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereCompanyLedgerableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyLedger whereUserId($value)
 * @mixin \Eloquent
 */
class CompanyLedger extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function company_ledgerable()
    {
        return $this->morphTo();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
