<?php
/**
 * Invoice Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Models\RecurringQuote;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringQuoteInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = ['client_contact_id'];

    protected $touches = ['recurring_quote'];

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
        return RecurringQuote::class;
    }

    /**
     * @return mixed
     */
    public function recurring_quote()
    {
        return $this->belongsTo(RecurringQuote::class)->withTrashed();
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
