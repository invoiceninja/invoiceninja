<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\LookupProposalInvitation;
use App\Models\Traits\Inviteable;

/**
 * Class Invitation.
 */
class TicketInvitation extends EntityModel
{
    use SoftDeletes;
    use Inviteable;

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_INVITATION;
    }

    /**
     * @return mixed
     */
    public function ticket()
    {
        return $this->belongsTo('App\Models\Ticket')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }
}

TicketInvitation::creating(function ($invitation)
{
    LookupTicketInvitation::createNew($invitation->account->account_key, [
        'invitation_key' => $invitation->invitation_key,
    ]);
});

TicketInvitation::updating(function ($invitation)
{
    $dirty = $invitation->getDirty();
    if (array_key_exists('message_id', $dirty)) {
        LookupTicketInvitation::updateInvitation($invitation->account->account_key, $invitation);
    }
});

TicketInvitation::deleted(function ($invitation)
{
    if ($invitation->forceDeleting) {
        LookupTicketInvitation::deleteWhere([
            'invitation_key' => $invitation->invitation_key,
        ]);
    }
});
