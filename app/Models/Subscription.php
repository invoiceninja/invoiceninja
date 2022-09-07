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

use App\Models\RecurringInvoice;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $hidden = [
        'id',
        'user_id',
        'assigned_user_id',
        'company_id',
        'product_ids',
        'recurring_product_ids',
        'group_id',
    ];

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

    protected $with = [
        'company',
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
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function group_settings()
    {
        return $this->belongsTo(GroupSetting::class, 'group_id', 'id');
    }

    public function nextDateByInterval($date, $frequency_id)
    {
        switch ($frequency_id) {

            case RecurringInvoice::FREQUENCY_DAILY:
                return $date->addDay();
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return $date->addWeek();
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return $date->addWeeks(2);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return $date->addWeeks(4);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return $date->addMonthNoOverflow();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return $date->addMonthsNoOverflow(2);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return $date->addMonthsNoOverflow(3);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return $date->addMonthsNoOverflow(4);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return $date->addMonthsNoOverflow(6);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return $date->addYear();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return $date->addYears(2);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return $date->addYears(3);
            default:
                return null;
        }
    }
}
