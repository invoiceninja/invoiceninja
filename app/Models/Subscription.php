<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Services\Subscription\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assigned_user_id',
        'product_ids',
        'recurring_product_ids',
        'frequency_id',
        'auto_bill',
        'promo_code',
        'promo_discount',
        'is_amount_discount',
        'allow_cancellation',
        'per_seat_enabled',
        'max_seats_limit',
        'trial_enabled',
        'trial_duration',
        'allow_query_overrides',
        'allow_plan_changes',
        'refund_period',
        'webhook_configuration',
        'currency_id',
        'group_id',
        'price',
        'name',
        'currency_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'webhook_configuration' => 'array',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function service(): SubscriptionService
    {
        return new SubscriptionService($this);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
