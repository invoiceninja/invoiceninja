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

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class for Recurring Invoices.
 */
class RecurringInvoice extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    /**
     * Invoice Statuses
     */
    const STATUS_DRAFT = 2;
    const STATUS_ACTIVE = 3;
    const STATUS_PENDING = -1;
    const STATUS_COMPLETED = -2;
    const STATUS_CANCELLED = -3;


    /**
     * Recurring intervals
     */
    const FREQUENCY_WEEKLY = 1;
    const FREQUENCY_TWO_WEEKS = 2;
    const FREQUENCY_FOUR_WEEKS = 3;
    const FREQUENCY_MONTHLY = 4;
    const FREQUENCY_TWO_MONTHS = 5;
    const FREQUENCY_THREE_MONTHS = 6;
    const FREQUENCY_FOUR_MONTHS = 7;
    const FREQUENCY_SIX_MONTHS = 8;
    const FREQUENCY_ANNUALLY = 9;
    const FREQUENCY_TWO_YEARS = 10;

    const RECURS_INDEFINITELY = -1;
    
	protected $fillable = [
		'client_id',
        'invoice_number',
        'discount',
        'is_amount_discount',
        'po_number',
        'invoice_date',
        'due_date',
        'line_items',
        'settings',
        'footer',
        'public_notes',
        'private_notes',
        'terms',
        'tax_name1',
        'tax_name2',
        'tax_rate1',
        'tax_rate2',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'amount',
        'partial',
        'frequency_id',
        'start_date',
	];

    protected $casts = [
        'settings' => 'object',
        'line_items' => 'object',
    ];

    protected $with = [
   //     'client',
   //     'company',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitations()
    {
        $this->morphMany(RecurringInvoiceInvitation::class);
    }

    public function nextSendDate() :?Carbon
    {

        switch ($this->frequency_id) 
        {
            case FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date->addWeek());
            case FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date->addWeeks(2));
            case FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date->addWeeks(4));
            case FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date->addMonth());
            case FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date->addMonths(2));
            case FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date->addMonths(3));
            case FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date->addMonths(4));
            case FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date->addMonths(6));
            case FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date->addYear());
            case FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date->addYears(2));
            default:
                return null;
        }

    }

    public function remainingCycles() : int
    {

        if($this->remaining_cycles == 0)
            return 0;
        else
            return $this->remaining_cycles - 1;

    }

    public function setCompleted() :  void
    {

        $this->status_id = self::STATUS_COMPLETED;
        $this->next_send_date = null;
        $this->remaining_cycles = 0;
        $this->save();

    }
}
