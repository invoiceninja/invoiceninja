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

use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;
use App\Http\Requests\EInvoice\Peppol\CreateRequest;
use App\Http\Requests\EInvoice\Peppol\DisconnectRequest;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Http\Response;

class EInvoicePeppolController extends BaseController
{
    public function setup(CreateRequest $request, Storecove $storecove): Response
    {
        /**
         * @var \App\Models\Company
         */
        $company = auth()->user()->company();

        $legal_entity_response = $storecove->createLegalEntity($request->validated(), $company);

        $scheme = $storecove->router->resolveRouting($request->country, $company->settings->classification);

        $add_identifier_response = $storecove->addIdentifier(
            legal_entity_id: $legal_entity_response['id'],
            identifier: $company->settings->vat_number,
            scheme: $scheme,
        );

        if ($add_identifier_response) {
            $company->legal_entity_id = $legal_entity_response['id'];
            $company->save();

            return response()->noContent();
        }

        // @todo: Improve with proper error.

        return response()->noContent(status: 422);
    }

    public function addAdditionalTaxIdentifier(AddTaxIdentifierRequest $request, Storecove $storecove): Response
    {
        
        $company = auth()->user()->company();

        $scheme = $storecove->router->resolveRouting($request->country, $company->settings->classification);

        $storecove->addAdditionalTaxIdentifier($company->legal_entity_id, $request->identifier, $scheme);

        return response()->json(['message' => 'ok'], 200);
    }

    public function disconnect(DisconnectRequest $request, Storecove $storecove): Response
    {
        /**
         * @var \App\Models\Company
         */
        $company = auth()->user()->company();

        $response = $storecove->deleteIdentifier(
            legal_entity_id: $company->legal_entity_id,
        );

        if ($response) {
            $company->legal_entity_id = null;
            $company->save();

            return response()->noContent();

        }

        // @todo: Improve with proper error.

        return response()->noContent(status: 422);
    }
}
