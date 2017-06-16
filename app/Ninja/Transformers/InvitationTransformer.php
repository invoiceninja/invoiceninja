<?php

namespace App\Ninja\Transformers;

use App\Models\Invitation;

class InvitationTransformer extends EntityTransformer
{
    public function transform(Invitation $invitation)
    {
        $invitation->setRelation('account', $this->account);

        return [
            'id' => (int) $invitation->public_id,
            'key' => $invitation->getName(),
            'link' => $invitation->getLink(),
            'sent_date' => $invitation->sent_date,
            'viewed_date' => $invitation->sent_date,
        ];
    }
}
