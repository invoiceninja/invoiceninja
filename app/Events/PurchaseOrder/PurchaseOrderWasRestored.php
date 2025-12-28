<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\PurchaseOrder;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Queue\SerializesModels;

/**
 * Class PurchaseOrderWasRestored.
 */
class PurchaseOrderWasRestored
{
    use SerializesModels;

    public function __construct(public PurchaseOrder $purchase_order, public bool $fromDeleted, public Company $company, public array $event_vars)
    {
    }
}
