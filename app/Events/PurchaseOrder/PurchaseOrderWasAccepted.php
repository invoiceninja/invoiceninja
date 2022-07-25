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

namespace App\Events\PurchaseOrder;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\VendorContact;
use Illuminate\Queue\SerializesModels;

/**
 * Class PurchaseOrderWasAccepted.
 */
class PurchaseOrderWasAccepted
{
    use SerializesModels;

    /**
     * @var PurchaseOrder
     */
    public $purchase_order;

    public $company;

    public $event_vars;

    public $contact;

    /**
     * Create a new event instance.
     *
     * @param PurchaseOrder $purchase_order
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(PurchaseOrder $purchase_order, VendorContact $contact, Company $company, array $event_vars)
    {
        $this->purchase_order = $purchase_order;
        $this->contact = $contact;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
