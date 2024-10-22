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

use Illuminate\Http\Response;
use App\Http\Requests\EInvoice\Peppol\StoreEntityRequest;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Http\Requests\EInvoice\Peppol\DisconnectRequest;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;
use App\Http\Requests\EInvoice\Peppol\ShowEntityRequest;

class EInvoicePeppolController extends BaseController
{    
    public function show(ShowEntityRequest $request, Storecove $storecove)
    {
        $company = auth()->user()->company();

        $response = $storecove->getLegalEntity($company->legal_entity_id);

        return response()->json($response, 200);
    }

    /**
     * Create a legal entity id
     *
     * @param  CreateRequest $request
     * @param  Storecove $storecove
     * @return Response
     */
    public function setup(StoreEntityRequest $request, Storecove $storecove): Response
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
    
    /**
     * Add an additional tax identifier to
     * an existing legal entity id
     *
     * @param  AddTaxIdentifierRequest $request
     * @param  Storecove $storecove
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAdditionalTaxIdentifier(AddTaxIdentifierRequest $request, Storecove $storecove): \Illuminate\Http\JsonResponse
    {
        
        $company = auth()->user()->company();
        $tax_data = $company->tax_data;

        $additional_vat = $tax_data->regions->EU->subregions->{$request->country}->vat_number ?? null;

        if (!is_null($additional_vat) && !empty($additional_vat)) {
            return response()->json(['message' => 'Identifier already exists for this region.'], 400);
        }

        $scheme = $storecove->router->resolveRouting($request->country, $company->settings->classification);

        $storecove->addAdditionalTaxIdentifier($company->legal_entity_id, $request->identifier, $scheme);

        $tax_data->regions->EU->subregions->{$request->country}->vat_number = $request->identifier;
        $company->tax_data = $tax_data;
        $company->save();

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
