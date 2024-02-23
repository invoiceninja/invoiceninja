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

use Awobaz\Compoships\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\CompanyUser
 *
 * @property int $id
 * @property int $company_id
 * @property int $account_id
 * @property int $user_id
 * @property string|null $permissions
 * @property object|null $notifications
 * @property object|null $settings
 * @property string $slack_webhook_url
 * @property bool $is_owner
 * @property bool $is_admin
 * @property bool $is_locked
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $permissions_updated_at
 * @property string $ninja_portal_url
 * @property object|null $react_settings
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CompanyUser $cu
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $token
 * @property-read int|null $token_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser authCompany()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereIsOwner($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereNinjaPortalUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser wherePermissionsUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereReactSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereSlackWebhookUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyUser withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $token
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $token
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyUser> $cu
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $token
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @mixin \Eloquent
 */
class CompanyUser extends Pivot
{
    use SoftDeletes;
    use \Awobaz\Compoships\Compoships;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that should be cast to native types.
     *
     */
    protected $casts = [
        'permissions_updated_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'settings' => 'object',
        'notifications' => 'object',
        'permissions' => 'string',
        'react_settings' => 'object',
    ];

    protected $fillable = [
        'account_id',
        'permissions',
        'notifications',
        'settings',
        'react_settings',
        'is_admin',
        'is_owner',
        'is_locked',
        'slack_webhook_url',
        'shop_restricted',
    ];

    protected $touches = ['user'];

    protected $with = ['user', 'account'];

    public function getEntityType()
    {
        return self::class;
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user_pivot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class)->withPivot('permissions', 'settings', 'react_settings', 'is_admin', 'is_owner', 'is_locked', 'slack_webhook_url', 'migrating');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function company_pivot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Company::class)->withPivot('permissions', 'settings', 'react_settings', 'is_admin', 'is_owner', 'is_locked', 'slack_webhook_url', 'migrating');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function token()
    {
        return $this->hasMany(CompanyToken::class, 'user_id', 'user_id');
    }

    /**
     * @return HasMany
     */
    public function tokens()
    {
        return $this->hasMany(CompanyToken::class, 'user_id', 'user_id');
    }

    public function scopeAuthCompany($query): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query->where('company_id', $user->companyId());

        return $query;
    }

    /**
     * Determines if the notifications should be React or Flutter links
     *
     * @return bool
     */
    public function portalType(): bool
    {
        nlog(isset($this->react_settings->react_notification_link) && $this->react_settings->react_notification_link);
        return isset($this->react_settings->react_notification_link) && $this->react_settings->react_notification_link;
    }

}
