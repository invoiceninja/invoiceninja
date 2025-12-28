<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\VendorPortal\PurchaseOrders;

use App\Http\Requests\Request;
use App\Http\ViewComposers\PortalComposer;

class ShowPurchaseOrdersRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return (bool)(auth()->guard('vendor')->user()->company->enabled_modules & PortalComposer::MODULE_PURCHASE_ORDERS);
    }
}
