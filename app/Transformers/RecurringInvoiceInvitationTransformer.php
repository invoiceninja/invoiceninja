<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\RecurringInvoiceInvitation;
use App\Utils\Traits\MakesHash;

class RecurringInvoiceInvitationTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(RecurringInvoiceInvitation $invitation)
    {
        return [
            'id' => $this->encodePrimaryKey($invitation->id),
            'client_contact_id' => $this->encodePrimaryKey($invitation->client_contact_id),
            'key' => $invitation->key,
            'link' => $invitation->getLink() ?: '',
            'sent_date' => $invitation->sent_date ?: '',
            'viewed_date' => $invitation->viewed_date ?: '',
            'opened_date' => $invitation->opened_date ?: '',
            'updated_at'        => (int) $invitation->updated_at,
            'archived_at'       => (int) $invitation->deleted_at,
            'created_at'       => (int) $invitation->created_at,
            'email_status'      => $invitation->email_status ?: '',
            'email_error'       => (string) $invitation->email_error,
        ];
    }
}
