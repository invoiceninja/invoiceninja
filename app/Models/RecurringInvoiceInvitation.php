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

use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringInvoiceInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = ['client_contact_id'];

    protected $touches = ['recurring_invoice'];

    protected $with = [
        'company',
        'contact',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return RecurringInvoice::class;
    }

    /**
     * @return mixed
     */
    public function recurring_invoice()
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
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

    public function markViewed()
    {
        $this->viewed_date = now();
        $this->save();
    }

    public function markOpened()
    {
        $this->opened_date = now();
        $this->save();
    }
}
