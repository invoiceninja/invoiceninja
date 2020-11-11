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

use App\Jobs\Entity\CreateEntityPdf;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetCreditPdf extends AbstractService
{
    public $credit;

    public $contact;

    public $invitation;

    public function __construct($invitation)
    {
        $this->invitation = $invitation;
        $this->credit = $invitation->credit;
        $this->contact = $invitation->contact;
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
            $file_path = CreateEntityPdf::dispatchNow($this->invitation);
        }

        return Storage::disk($disk)->path($file_path);
    }
}
