<?php

namespace App\Models;

use App\Models\Currency;
use App\Models\Filterable;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    use NumberFormatter;
    use MakesDates;

	protected $guarded = [
		'id',
	];

    protected $casts = [
        'settings' => 'object'
    ];

    protected $with = [
        'company',
        'client',
    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCELLED = 5;

    const STATUS_OVERDUE = -1;
    const STATUS_UNPAID = -2;
    const STATUS_REVERSED = -3;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitations()
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /* ---------------- */
    /* Settings getters */
    /* ---------------- */

    /**
     * If True, prevents an invoice from being 
     * modified once it has been marked as sent
     * 
     * @return boolean isLocked
     */
    public function isLocked() : bool
    {
        return $this->client->getMergedSettings()->lock_sent_invoices;
    }

    /**
     * Gets the currency from the settings object.
     *
     * @return     Eloquent Model  The currency.
     */
    public function getCurrency()
    {
        return Currency::find($this->settings->currency_id);
    }


    /**
     * Determines if invoice overdue.
     *
     * @param      float    $balance   The balance
     * @param      date.    $due_date  The due date
     *
     * @return     boolean  True if overdue, False otherwise.
     */
    public static function isOverdue($balance, $due_date)
    {
        if (! $this->formatValue($balance,2) > 0 || ! $due_date) {
            return false;
        }

        // it isn't considered overdue until the end of the day
        return strtotime($this->createClientDate(date(), $this->client->timezone()->name)) > (strtotime($due_date) + (60 * 60 * 24));
    }

    
}
