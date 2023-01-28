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

namespace App\Events\PurchaseOrder;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class PurchaseOrderWasViewed.
 */
class PurchaseOrderWasViewed
{
    use SerializesModels;

    /**
     * @var PurchaseOrder
     */
    public $invitation;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param PurchaseOrder $purchase_order
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(PurchaseOrderInvitation $invitation, Company $company, array $event_vars)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
