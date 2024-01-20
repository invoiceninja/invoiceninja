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

namespace App\Http\Requests\ClientPortal\RecurringInvoices;

use App\Http\Requests\Request;
use App\Http\ViewComposers\PortalComposer;

class ShowRecurringInvoiceRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->guard('contact')->user()->client->id == $this->recurring_invoice->client_id
            && auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES;
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
