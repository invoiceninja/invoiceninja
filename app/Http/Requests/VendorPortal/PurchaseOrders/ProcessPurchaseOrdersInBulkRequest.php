<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\VendorPortal\PurchaseOrders;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ProcessPurchaseOrdersInBulkRequest extends FormRequest
{
    public function authorize()
    {
        return (bool)(auth()->guard('vendor')->user()->vendor->company->enabled_modules & PortalComposer::MODULE_PURCHASE_ORDERS);
    }

    public function rules()
    {
        return [
            'purchase_orders' => ['array'],
        ];
    }
}
