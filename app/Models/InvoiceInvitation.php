<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Events\Invoice\InvoiceWasUpdated;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class InvoiceInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        //'key',
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['invoice'];

    public function getEntityType()
    {
        return self::class;
    }

    // public function getSignatureDateAttribute($value)
    // {
    //     if (!$value) {
    //         return (new Carbon($value))->format('Y-m-d');
    //     }
    //     return $value;
    // }

    // public function getSentDateAttribute($value)
    // {
    //     if (!$value) {
    //         return (new Carbon($value))->format('Y-m-d');
    //     }
    //     return $value;
    // }

    // public function getViewedDateAttribute($value)
    // {
    //     if (!$value) {
    //         return (new Carbon($value))->format('Y-m-d');
    //     }
    //     return $value;
    // }

    // public function getOpenedDateAttribute($value)
    // {
    //     if (!$value) {
    //         return (new Carbon($value))->format('Y-m-d');
    //     }
    //     return $value;
    // }

    public function entityType()
    {
        return Invoice::class;
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    /**
     * @return mixed
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

    public function signatureDiv()
    {
        if (! $this->signature_base64) {
            return false;
        }

        return sprintf('<img src="data:image/svg+xml;base64,%s"></img><p/>%s: %s', $this->signature_base64, ctrans('texts.signed'), $this->createClientDate($this->signature_date, $this->contact->client->timezone()->name));
    }

    public function getName()
    {
        return $this->key;
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function markOpened()
    {
        $this->opened_date = Carbon::now();
        $this->save();
    }

    public function pdf_file_path()
    {
        $storage_path = Storage::url($this->invoice->client->invoice_filepath().$this->invoice->number.'.pdf');

        if (! Storage::exists($this->invoice->client->invoice_filepath().$this->invoice->number.'.pdf')) {
            event(new InvoiceWasUpdated($this->invoice, $this->company, Ninja::eventVars()));
            CreateInvoicePdf::dispatchNow($this);
        }

        return $storage_path;
    }
}
