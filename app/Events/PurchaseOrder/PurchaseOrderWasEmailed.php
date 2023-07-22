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
use App\Models\PurchaseOrderInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class PurchaseOrderWasEmailed.
 */
class PurchaseOrderWasEmailed
{
    use SerializesModels;

    public function __construct(public PurchaseOrderInvitation $invitation, public Company $company, public array $event_vars)
    {
    }
}
