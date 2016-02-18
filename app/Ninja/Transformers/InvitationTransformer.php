<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Invitation;
use League\Fractal;

class InvitationTransformer extends EntityTransformer
{
    public function transform(Invitation $invitation)
    {
        return [
            'id' => (int) $invitation->public_id,
            'key' => $invitation->getName(),
            'status' => $invitation->getStatus(),
            'link' => $invitation->getLink(),
            'sent_date' => $invitation->sent_date,
            'viewed_date' => $invitation->sent_date,
        ];
    }
}