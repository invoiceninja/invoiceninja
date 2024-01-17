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

namespace App\Services\PurchaseOrder;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\AbstractService;

class GetPurchaseOrderPdf extends AbstractService
{
    public function __construct(public PurchaseOrder $purchase_order, public ?VendorContact $contact = null)
    {
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

        return (new CreateRawPdf($invitation))->handle();

    }
}
