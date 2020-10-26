<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Credit;

use App\Jobs\Credit\CreateEntityPdf;
use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetCreditPdf extends AbstractService
{
    private $credit;

    private $contact;

    public function __construct(Credit $credit, ClientContact $contact = null)
    {
        $this->credit = $credit;
        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->credit->client->primary_contact()->first();
        }

        $path = $this->credit->client->credit_filepath();

        $file_path = $path.$this->credit->number.'.pdf';

        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = CreateEntityPdf::dispatchNow($this->credit, $this->credit->company, $this->contact);
        }

        return Storage::disk($disk)->path($file_path);
    }
}
