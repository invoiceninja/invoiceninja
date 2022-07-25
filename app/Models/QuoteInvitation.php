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

use App\Events\Quote\QuoteWasUpdated;
use App\Jobs\Entity\CreateEntityPdf;
use App\Utils\Ninja;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class QuoteInvitation extends BaseModel
{
    use MakesDates;
    use Inviteable;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['quote'];

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
        return Quote::class;
    }

    /**
     * @return mixed
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class)->withTrashed();
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
     * @return BelongsTo
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

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function pdf_file_path()
    {
        $storage_path = Storage::url($this->quote->client->quote_filepath($this).$this->quote->numberFormatter().'.pdf');

        if (! Storage::exists($this->quote->client->quote_filepath($this).$this->quote->numberFormatter().'.pdf')) {
            event(new QuoteWasUpdated($this->quote, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
            CreateEntityPdf::dispatchSync($this);
        }

        return $storage_path;
    }
}
