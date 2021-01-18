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

namespace App\Services\Recurring;

use App\Jobs\Entity\CreateEntityPdf;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\TempFile;
use Illuminate\Support\Facades\Storage;

class GetInvoicePdf extends AbstractService
{
    public $entity;

    public function __construct($entity, ClientContact $contact = null)
    {
        $this->entity = $entity;

        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->entity->client->primary_contact()->first();
        }

        $invitation = $this->entity->invitations->where('client_contact_id', $this->contact->id)->first();

        $path = $this->entity->client->recurring_invoice_filepath();

        $file_path = $path.$this->entity->hashed_id.'.pdf';

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
