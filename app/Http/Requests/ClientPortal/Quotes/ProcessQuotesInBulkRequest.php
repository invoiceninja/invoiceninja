<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\Quotes;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ProcessQuotesInBulkRequest extends FormRequest
{
    public function authorize()
    {

        auth()->guard('contact')->user()->loadMissing(['company']);

        return (bool) (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_QUOTES);
    }

    public function rules()
    {
        return [
            'quotes' => ['required_without:request_hash', 'array'],
            'action' => ['required_without:request_hash', 'in:download,approve,reject'],
            'request_hash' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9]{64}$/'],
        ];
    }
}
