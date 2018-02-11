<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\LookupProposalInvitation;
use App\Models\Traits\Inviteable;

/**
 * Class Invitation.
 */
class ProposalInvitation extends EntityModel
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
        return ENTITY_PROPOSAL_INVITATION;
    }

    /**
     * @return mixed
     */
    public function proposal()
    {
        return $this->belongsTo('App\Models\Proposal')->withTrashed();
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

ProposalInvitation::creating(function ($invitation)
{
    LookupProposalInvitation::createNew($invitation->account->account_key, [
        'invitation_key' => $invitation->invitation_key,
    ]);
});

ProposalInvitation::updating(function ($invitation)
{
    $dirty = $invitation->getDirty();
    if (array_key_exists('message_id', $dirty)) {
        LookupProposalInvitation::updateInvitation($invitation->account->account_key, $invitation);
    }
});

ProposalInvitation::deleted(function ($invitation)
{
    if ($invitation->forceDeleting) {
        LookupProposalInvitation::deleteWhere([
            'invitation_key' => $invitation->invitation_key,
        ]);
    }
});
