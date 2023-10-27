<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quote;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Services\AbstractService;

class GetQuotePdf extends AbstractService
{
    public function __construct(public Quote $quote, public ?ClientContact $contact = null)
    {
    }

    public function run()
    {
        if (!$this->contact) {
            $this->contact = $this->quote->client->primary_contact()->first() ?: $this->quote->client->contacts()->first();
        }

        $invitation = $this->quote->invitations->where('client_contact_id', $this->contact->id)->first();

        if (!$invitation) {
            $invitation = $this->quote->invitations->first();
        }

        return (new CreateRawPdf($invitation))->handle();

    }
}
