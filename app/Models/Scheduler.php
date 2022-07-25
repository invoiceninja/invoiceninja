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

use App\Services\TaskScheduler\TaskSchedulerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property bool paused
 * @property bool is_deleted
 * @property \Carbon\Carbon|mixed start_from
 * @property string repeat_every
 * @property \Carbon\Carbon|mixed scheduled_run
 * @property int company_id
 * @property int updated_at
 * @property int created_at
 * @property int deleted_at
 * @property string action_name
 * @property mixed company
 * @property array parameters
 * @property string action_class
 */
class Scheduler extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'start_from',
        'paused',
        'repeat_every',
        'scheduled_run',
        'action_class',
        'action_name',
        'parameters',
        'company_id',
    ];

    protected $casts = [
        'start_from' => 'timestamp',
        'scheduled_run' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'paused' => 'boolean',
        'is_deleted' => 'boolean',
        'parameters' => 'array',
    ];

    const DAILY = 'DAY';

    const WEEKLY = 'WEEK';

    const BIWEEKLY = 'BIWEEKLY';

    const MONTHLY = 'MONTH';

    const QUARTERLY = '3MONTHS';

    const ANNUALLY = 'YEAR';

    const CREATE_CLIENT_REPORT = 'create_client_report';

    const CREATE_CLIENT_CONTACT_REPORT = 'create_client_contact_report';

    const CREATE_CREDIT_REPORT = 'create_credit_report';

    const CREATE_DOCUMENT_REPORT = 'create_document_report';

    const CREATE_EXPENSE_REPORT = 'create_expense_report';

    const CREATE_INVOICE_ITEM_REPORT = 'create_invoice_item_report';

    const CREATE_INVOICE_REPORT = 'create_invoice_report';

    const CREATE_PAYMENT_REPORT = 'create_payment_report';

    const CREATE_PRODUCT_REPORT = 'create_product_report';

    const CREATE_PROFIT_AND_LOSS_REPORT = 'create_profit_and_loss_report';

    const CREATE_QUOTE_ITEM_REPORT = 'create_quote_item_report';

    const CREATE_QUOTE_REPORT = 'create_quote_report';

    const CREATE_RECURRING_INVOICE_REPORT = 'create_recurring_invoice_report';

    const CREATE_TASK_REPORT = 'create_task_report';

    /**
     * Service entry points.
     */
    public function service(): TaskSchedulerService
    {
        return new TaskSchedulerService($this);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function nextScheduledDate(): ?Carbon
    {
        $offset = 0;

        $entity_send_time = $this->company->settings->entity_send_time;

        if ($entity_send_time != 0) {
            $timezone = $this->company->timezone();

            $offset -= $timezone->utc_offset;
            $offset += ($entity_send_time * 3600);
        }

        /*
        As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
        to add ON a day - a day = 86400 seconds
        */

        if ($offset < 0) {
            $offset += 86400;
        }

        switch ($this->repeat_every) {
            case self::DAILY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addDay()->addSeconds($offset);
            case self::WEEKLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addWeek()->addSeconds($offset);
            case self::BIWEEKLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::MONTHLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::QUARTERLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::ANNUALLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addYearNoOverflow()->addSeconds($offset);
            default:
                return null;
        }
    }
}
