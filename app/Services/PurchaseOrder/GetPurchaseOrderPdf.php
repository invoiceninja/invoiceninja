<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\PurchaseOrder;

use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\AbstractService;
use App\Utils\TempFile;
use Illuminate\Support\Facades\Storage;

class GetPurchaseOrderPdf extends AbstractService
{
    public function __construct(PurchaseOrder $purchase_order, VendorContact $contact = null)
    {
        $this->purchase_order = $purchase_order;

        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->purchase_order->vendor->contacts()->orderBy('send_email', 'DESC')->first();
        }

        $invitation = $this->purchase_order->invitations()->where('vendor_contact_id', $this->contact->id)->first();

        if (! $invitation) {
            $invitation = $this->purchase_order->invitations()->first();
        }

        $path = $this->purchase_order->vendor->purchase_order_filepath($invitation);

        $file_path = $path.$this->purchase_order->numberFormatter().'.pdf';

        // $disk = 'public';
        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = (new CreatePurchaseOrderPdf($invitation))->handle();
        }

        return $file_path;
    }
}
