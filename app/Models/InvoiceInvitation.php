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

use App\Models\Invoice;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class InvoiceInvitation extends BaseModel
{

	use MakesDates;
    use SoftDeletes;
    
    protected $fillable = [
        'id',
        'client_contact_id',
    ];

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
        return $this->belongsTo(ClientContact::class,'client_contact_id','id')->withTrashed();
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
        return $this->invitation_key;
    }

    public function getLink()
    {
        
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }
}
