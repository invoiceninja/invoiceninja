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

namespace App\Http\Controllers;

use App\Exceptions\SystemError;
use App\Http\Requests\EInvoice\ValidateEInvoiceRequest;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EInvoiceController extends BaseController
{

    public function validateEntity(ValidateEInvoiceRequest $request)
    {
        $el = new EntityLevel();

        $data = [];

        match($request->entity){
            'invoices' => $data = $el->checkInvoice($request->getEntity()),
            'clients' => $data = $el->checkClient($request->getEntity()),
            'companies' => $data = $el->checkCompany($request->getEntity()),
            default => $data['passes'] = false,
        };

        return response()->json($data, $data['passes'] ? 200 : 400);

    }

}
