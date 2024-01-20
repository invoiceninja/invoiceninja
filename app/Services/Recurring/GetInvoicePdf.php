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

namespace App\Services\Recurring;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\ClientContact;
use App\Services\AbstractService;

class GetInvoicePdf extends AbstractService
{
    public function __construct(public $entity, public ?ClientContact $contact = null)
    {
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->entity->client->primary_contact()->first();
        }

        $invitation = $this->entity->invitations->where('client_contact_id', $this->contact->id)->first();

        if (! $invitation) {
            $invitation = $this->entity->invitations->first();
        }

        return (new CreateRawPdf($invitation))->handle();

    }
}
