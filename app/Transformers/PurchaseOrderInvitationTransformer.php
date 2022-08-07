<?php

namespace App\Transformers;

use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\MakesHash;

class PurchaseOrderInvitationTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(PurchaseOrderInvitation $invitation)
    {
        return [
            'id' => $this->encodePrimaryKey($invitation->id),
            'vendor_contact_id' => $this->encodePrimaryKey($invitation->vendor_contact_id),
            'key' => $invitation->key,
            'link' => $invitation->getLink() ?: '',
            'sent_date' => $invitation->sent_date ?: '',
            'viewed_date' => $invitation->viewed_date ?: '',
            'opened_date' => $invitation->opened_date ?: '',
            'updated_at' => (int) $invitation->updated_at,
            'archived_at' => (int) $invitation->deleted_at,
            'created_at' => (int) $invitation->created_at,
            'email_status' => $invitation->email_status ?: '',
            'email_error' => (string) $invitation->email_error,
        ];
    }
}
