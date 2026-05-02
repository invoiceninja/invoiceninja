<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;

class GetInvoicePdf extends AbstractService
{
    public function __construct(public Invoice $invoice, public ?ClientContact $contact = null) {}

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->invoice->client->primary_contact()->first() ?: $this->invoice->client->contacts()->first();
        }

        // Skip the client_contact_id match when the client has no contacts at all
        // (a degenerate state that still occurs in tests and imports). The fallback
        // below — first invitation, or auto-created — handles those cases.
        $invitation = $this->contact
            ? $this->invoice->invitations->where('client_contact_id', $this->contact->id)->first()
            : null;

        if (! $invitation) {
            $invitation = $this->invoice->invitations->first();
        }

        // Failsafe: an invoice persisted outside the repository (e.g. via factory
        // in tests, or hydrated from an import) may have no invitations yet. Mirror
        // BaseRepository::save()'s recovery path so PDF generation never receives null.
        if (! $invitation) {
            $this->invoice->service()->createInvitations()->save();
            $this->invoice->load('invitations');
            $invitation = $this->invoice->invitations->first();
        }

        return (new CreateRawPdf($invitation))->handle();

    }
}
