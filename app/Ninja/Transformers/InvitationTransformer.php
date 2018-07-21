<?php

namespace App\Ninja\Transformers;

use App\Models\Invitation;

class InvitationTransformer extends EntityTransformer
{
	  /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="key", type="string", example="Key")
    * @SWG\Property(property="link", type="string", example="Link")
    * @SWG\Property(property="sent_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="viewed_date", type="string", format="date", example="2018-01-01")
    */
    public function transform(Invitation $invitation)
    {
        $invitation->setRelation('account', $this->account);

        return [
            'id' => (int) $invitation->public_id,
            'key' => $invitation->getName(),
            'link' => $invitation->getLink(),
            'sent_date' => $invitation->sent_date ?: '',
            'viewed_date' => $invitation->sent_date ?: '',
        ];
    }
}
