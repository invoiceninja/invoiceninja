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

use App\Models\Quote;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class QuoteInvitation extends BaseModel
{
    use MakesDates;
    use Inviteable;

    protected $fillable = [
        'id',
        'client_contact_id',
    ];

    public function getSignatureDateAttribute($value)
    {
        if (!$value) {
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function getSentDateAttribute($value)
    {
        if (!$value) {
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function getViewedDateAttribute($value)
    {
        if (!$value) {
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function getOpenedDateAttribute($value)
    {
        if (!$value) {
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }
    
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

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }
}
