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

namespace App\Transformers;

use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;

/**
 * @SWG\Definition(definition="Invoice", required={"invoice_number"}, @SWG\Xml(name="Invoice"))
 */
class InvoiceInvitationTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(InvoiceInvitation $invitation)
    {

        return [
            'id' => $this->encodePrimaryKey($invitation->id),
            'key' => $invitation->getName(),
            'link' => $invitation->getLink(),
            'sent_date' => $invitation->sent_date ?: '',
            'viewed_date' => $invitation->sent_date ?: '',
            'opened_date' => $invitation->opened_date ?: '',
        ];
    }
}
