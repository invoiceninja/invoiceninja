<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Jobs\Entity\CreateEntityPdf;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\TempFile;
use Illuminate\Support\Facades\Storage;

class GetInvoicePdf extends AbstractService
{
    public function __construct(Invoice $invoice, ClientContact $contact = null)
    {
        $this->invoice = $invoice;

        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->invoice->client->primary_contact()->first();
        }

        $invitation = $this->invoice->invitations->where('client_contact_id', $this->contact->id)->first();

        $path = $this->invoice->client->invoice_filepath();

        $file_path = $path.$this->invoice->numberFormatter().'.pdf';

        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = CreateEntityPdf::dispatchNow($invitation);
        }


        /* Copy from remote disk to local when using cloud file storage. */
        if(config('filesystems.default') == 's3')
            return TempFile::path(Storage::disk($disk)->url($file_path));

        // return Storage::disk($disk)->url($file_path);
        return Storage::disk($disk)->path($file_path);
    }
}
