<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;

class BulkInvoiceRequest extends Request
{
    public function authorize() : bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'action' => 'required|string',
            'ids' => 'required'
        ];
    }
}
