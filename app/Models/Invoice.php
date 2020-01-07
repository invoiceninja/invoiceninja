<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Client\UpdateClientBalance;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Invoice\ApplyInvoiceNumber;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Models\Currency;
use App\Models\Filterable;
use App\Models\PaymentTerm;
use App\Utils\Number;
use App\Utils\Traits\InvoiceEmailBuilder;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesInvoiceValues;
use App\Utils\Traits\MakesReminders;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laracasts\Presenter\PresentableTrait;

class Invoice extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use NumberFormatter;
    use MakesDates;
    use PresentableTrait;
    use MakesInvoiceValues;
    use InvoiceEmailBuilder;
    use MakesReminders;

    protected $presenter = 'App\Models\Presenters\InvoicePresenter';

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'client_id',
        'company_id',
        'backup',
    ];

    protected $fillable = [
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'invoice_type_id',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'is_amount_discount',
        'footer',
        'partial',
        'partial_due_date',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
    ];

    protected $casts = [
        'line_items' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [
        'company',
        'client',
    ];

    protected $appends = [
        'hashed_id',
        'status'
    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCELLED = 5;

    const STATUS_OVERDUE = -1;
    const STATUS_UNPAID = -2;
    const STATUS_REVERSED = -3;


    public function getStatusAttribute()
    {
        if ($this->status_id == Invoice::STATUS_SENT && $this->due_date > Carbon::now()) {
            return Invoice::STATUS_UNPAID;
        } elseif ($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date > Carbon::now()) {
            return Invoice::STATUS_UNPAID;
        } elseif ($this->status_id == Invoice::STATUS_SENT && $this->due_date < Carbon::now()) {
            return Invoice::STATUS_OVERDUE;
        } elseif ($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date < Carbon::now()) {
            return Invoice::STATUS_OVERDUE;
        } else {
            return $this->status_id;
        }
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function invitations()
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function credits()
    {
        return $this->belongsToMany(Credit::class)->using(Paymentable::class);
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
        return $this->client->getSetting('lock_sent_invoices');
    }

//    /**
//     * Determines if invoice overdue.
//     *
//     * @param      float    $balance   The balance
//     * @param      date.    $due_date  The due date
//     *
//     * @return     boolean  True if overdue, False otherwise.
//     */
//    public static function isOverdue($balance, $due_date)
//    {
//        if (! $this->formatValue($balance,2) > 0 || ! $due_date) {
//            return false;
//        }
//
//        // it isn't considered overdue until the end of the day
//        return strtotime($this->createClientDate(date(), $this->client->timezone()->name)) > (strtotime($due_date) + (60 * 60 * 24));
//    }

    public function markViewed() :void
    {
        $this->last_viewed = Carbon::now()->format('Y-m-d H:i');
        $this->save();
    }

    public function isPayable() : bool
    {
        if ($this->status_id == Invoice::STATUS_SENT && $this->due_date > Carbon::now()) {
            return true;
        } elseif ($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date > Carbon::now()) {
            return true;
        } elseif ($this->status_id == Invoice::STATUS_SENT && $this->due_date < Carbon::now()) {
            return true;
        } elseif ($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date < Carbon::now()) {
            return true;
        } else {
            return false;
        }
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case Invoice::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
                break;
            case Invoice::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
                break;
            case Invoice::STATUS_PARTIAL:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.partial').'</span></h5>';
                break;
            case Invoice::STATUS_PAID:
                return '<h5><span class="badge badge-success">'.ctrans('texts.paid').'</span></h5>';
                break;
            case Invoice::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
                break;
            case Invoice::STATUS_OVERDUE:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.overdue').'</span></h5>';
                break;
            case Invoice::STATUS_UNPAID:
                return '<h5><span class="badge badge-warning">'.ctrans('texts.unpaid').'</span></h5>';
                break;
            case Invoice::STATUS_REVERSED:
                return '<h5><span class="badge badge-info">'.ctrans('texts.reversed').'</span></h5>';
                break;
            default:
                # code...
                break;
        }
    }

    public static function stringStatus(int $status)
    {
        switch ($status) {
            case Invoice::STATUS_DRAFT:
                return ctrans('texts.draft');
                break;
            case Invoice::STATUS_SENT:
                return ctrans('texts.sent');
                break;
            case Invoice::STATUS_PARTIAL:
                return ctrans('texts.partial');
                break;
            case Invoice::STATUS_PAID:
                return ctrans('texts.paid');
                break;
            case Invoice::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
                break;
            case Invoice::STATUS_OVERDUE:
                return ctrans('texts.overdue');
                break;
            case Invoice::STATUS_UNPAID:
                return ctrans('texts.unpaid');
                break;
            case Invoice::STATUS_REVERSED:
                return ctrans('texts.reversed');
                break;
            default:
                # code...
                break;
        }
    }
    /**
     * Returns the template for the invoice
     *
     * @return string Either the template view, OR the template HTML string
     * @todo  this needs attention, invoice->settings needs clarification
     */
    public function design() :string
    {
        if ($this->client->getSetting('design')) {
            return File::exists(resource_path($this->client->getSetting('design'))) ? File::get(resource_path($this->client->getSetting('design'))) : File::get(resource_path('views/pdf/design1.blade.php'));
        } else {
            return File::get(resource_path('views/pdf/design1.blade.php'));
        }
    }

    /**
     * Access the invoice calculator object
     *
     * @return object The invoice calculator object getters
     */
    public function calc()
    {
        $invoice_calc = null;

        if ($this->uses_inclusive_taxes) {
            $invoice_calc = new InvoiceSumInclusive($this);
        } else {
            $invoice_calc = new InvoiceSum($this);
        }

        return $invoice_calc->build();
    }

    /** TODO// DOCUMENT THIS FUNCTIONALITY */
    public function pdf_url()
    {
        $public_path = 'storage/' . $this->client->client_hash . '/invoices/'. $this->number . '.pdf';

        $storage_path = 'public/' . $this->client->client_hash . '/invoices/'. $this->number . '.pdf';

        if (!Storage::exists($storage_path)) {
            event(new InvoiceWasUpdated($this, $this->company));
            CreateInvoicePdf::dispatch($this, $this->company);
        }

        return $public_path;
    }

    public function pdf_file_path()
    {
        $storage_path = 'storage/' . $this->client->client_hash . '/invoices/'. $this->number . '.pdf';

        if (!Storage::exists($storage_path)) {
            CreateInvoicePdf::dispatchNow($this, $this->company);
        }

        return $storage_path;
    }

    /**
     * @param bool $save
     */
    public function updatePaidStatus($paid = false, $save = true) : bool
    {
        $status_id = false;
        if ($paid && $this->balance == 0) {
            $status_id = self::STATUS_PAID;
        } elseif ($paid && $this->balance > 0 && $this->balance < $this->amount) {
            $status_id = self::STATUS_PARTIAL;
        } elseif ($this->hasPartial() && $this->balance > 0) {
            $status_id = ($this->balance == $this->amount ? self::STATUS_SENT : self::STATUS_PARTIAL);
        }

        if ($status_id && $status_id != $this->status_id) {
            $this->status_id = $status_id;
            if ($save) {
                $this->save();
            }
        }
    }

    /**
     * @return bool
     */
    public function hasPartial() : bool
    {
        return ($this->partial && $this->partial > 0) === true;
    }

    /**
     * @return bool
     */
    public function isPartial() : bool
    {
        return $this->status_id >= self::STATUS_PARTIAL;
    }

    /**
     * Clear partial fields
     * @return void
     */
    public function clearPartial() : void
    {
        $this->partial = null;
        $this->partial_due_date = null;
        $this->save();
    }

    /**
     * @param float $balance_adjustment
     */
    public function updateBalance($balance_adjustment)
    {
        if ($this->is_deleted) {
            return;
        }

        $balance_adjustment = floatval($balance_adjustment);

        $this->balance = $this->balance + $balance_adjustment;

        if ($this->balance == 0) {
            $this->status_id = self::STATUS_PAID;
            $this->save();
            event(new InvoiceWasPaid($this, $this->company));

            return;
        }

        $this->save();
    }

    public function setDueDate()
    {
        $this->due_date = Carbon::now()->addDays($this->client->getSetting('payment_terms'));
        $this->save();
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->save();
    }

    public function markSent()
    {
        /* Return immediately if status is not draft */
        if ($this->status_id != Invoice::STATUS_DRAFT) {
            return $this;
        }

        $this->status_id = Invoice::STATUS_SENT;

        $this->markInvitationsSent();

        $this->setReminder();

        event(new InvoiceWasMarkedSent($this, $this->company));

        UpdateClientBalance::dispatchNow($this->client, $this->balance, $this->company);

        ApplyInvoiceNumber::dispatchNow($this, $this->client->getMergedSettings(), $this->company);

        UpdateCompanyLedgerWithInvoice::dispatchNow($this, $this->balance, $this->company);

        $this->save();

        return $this;
    }

    /**
     * Updates Invites to SENT
     *
     */
    private function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (!isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }
        });
    }
}
