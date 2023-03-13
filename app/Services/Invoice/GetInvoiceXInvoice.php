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

use App\Jobs\Invoice\CreateXInvoice;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetInvoiceXInvoice extends AbstractService
{
    public function __construct(Invoice $invoice, ClientContact $contact = null)
    {
        $this->invoice = $invoice;

        $this->contact = $contact;
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

        $path = $this->invoice->client->invoice_filepath($invitation);

        $file_path = $path.$this->invoice->numberFormatter().'-xinvoice.xml';

        // $disk = 'public';
        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = (new CreateXInvoice($this->invoice))->handle();
        }

        return $file_path;
    }
}
