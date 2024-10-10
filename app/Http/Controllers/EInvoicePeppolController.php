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

use App\Http\Requests\EInvoice\Peppol\CreateRequest;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Http\Response;

class EInvoicePeppolController extends BaseController
{
    public function setup(CreateRequest $request, Storecove $storecove): Response
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        $legal_entity_response = $storecove->createLegalEntity($request->validated(), $user->company());

        $add_identifier_response = $storecove->addIdentifier(
            legal_entity_id: $legal_entity_response['id'],
            identifier: $user->company()->settings->vat_number,
            scheme: "{$request->country}:VAT"
        );  

        $user->company->legal_entity_id = $legal_entity_response['id'];
        $user->company->save();
        
        return response()->noContent();
    }
}
