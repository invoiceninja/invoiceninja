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

namespace App\Services\Invoice;

use App\Jobs\Invoice\CreateEInvoice;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetInvoiceXInvoice extends AbstractService
{
    public function __construct(public Invoice $invoice, public ClientContact $contact = null)
    {
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->invoice->client->primary_contact()->first() ?: $this->invoice->client->contacts()->first();
        }

        $invitation = $this->invoice->invitations->where('client_contact_id', $this->contact->id)->first();

        if (! $invitation) {
            $invitation = $this->invoice->invitations->first();
        }

        $file_path = $this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()). $this->invoice->getFileName("xml");

        // $disk = 'public';
        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = (new CreateEInvoice($this->invoice, false))->handle();
        }

        return $file_path;
    }
}
