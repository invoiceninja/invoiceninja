<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\RecurringInvoices;

use App\Http\Requests\Request;
use App\Http\ViewComposers\PortalComposer;

class ShowRecurringInvoiceRequest extends Request
{
    public function authorize(): bool
    {
        auth()->guard('contact')->user()->loadMissing(['company']);

        return (int)auth()->guard('contact')->user()->client->id == $this->recurring_invoice->client_id
            && (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES);
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
