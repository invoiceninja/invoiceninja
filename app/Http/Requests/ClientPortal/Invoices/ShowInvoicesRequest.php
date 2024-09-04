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

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ShowInvoicesRequest extends FormRequest
{
    public function authorize()
    {

        auth()->guard('contact')->user()->loadMissing(['company']);

        return (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES);
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
