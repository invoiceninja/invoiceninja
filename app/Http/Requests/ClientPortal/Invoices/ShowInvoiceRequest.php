<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\Invoices;

use App\Http\Requests\Request;
use App\Http\ViewComposers\PortalComposer;

class ShowInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        auth()->guard('contact')->user()->loadMissing(['company']);

        return (int) auth()->guard('contact')->user()->client_id === (int) $this->invoice->client_id
            && (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES);
    }
}
