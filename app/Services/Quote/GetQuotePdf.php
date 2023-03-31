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

use App\Jobs\Entity\CreateEntityPdf;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Services\AbstractService;

class GetQuotePdf extends AbstractService
{
    public function __construct(public Quote $quote, public ?ClientContact $contact = null)
    {
        $this->quote = $quote;

        $this->contact = $contact;
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

        $path = $this->quote->client->quote_filepath($invitation);

        $file_path = $path . $this->quote->numberFormatter() . '.pdf';

        // $disk = 'public';
        $disk = config('filesystems.default');


        $file_path = (new CreateEntityPdf($invitation))->handle();

        return $file_path;
    }
}
