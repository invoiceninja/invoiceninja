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

namespace App\Http\Controllers;

use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use App\Http\Requests\Quickbooks\SyncTaxRatesRequest;
use App\Http\Requests\Quickbooks\SyncQuickbooksRequest;
use App\Http\Requests\Quickbooks\DisconnectQuickbooksRequest;

class QuickbooksController extends BaseController
{
    public function sync(SyncQuickbooksRequest $request)
    {

        $user = auth()->user();
        $company = $user->company();

        $syncable = [];

        if($request->client) {
            $syncable[] = 'Customer';
        }
        if($request->product) {
            $syncable[] = 'Item';
        }
        if($request->invoice) {
            $syncable[] = 'Invoice';
        }

        QuickbooksImport::dispatch($company->id, $company->db, $syncable);
        
        return response()->noContent();
    }

    /**
     * syncTaxRates
     *
     * Syncs tax rates from Quickbooks to Invoice Ninja
     *
     * @param  SyncTaxRatesRequest $request
     * @return \Illuminate\Http\Response
     */
    public function syncTaxRates(SyncTaxRatesRequest $request)
    {
        $user = auth()->user();
        $company = $user->company();

        $qb = new QuickbooksService($company);
        $qb->syncTaxRates();

        return response()->noContent();
    }

    /**
     * disconnect
     *
     * Disconnects the Quickbooks Account From the Invoice Ninja Company
     *
     * @param  DisconnectQuickbooksRequest $request
     * @return \Illuminate\Http\Response
     */
    public function disconnect(DisconnectQuickbooksRequest $request)
    {

        $user = auth()->user();
        $company = $user->company();

        try{
            $qb = new QuickbooksService($company);
            $qb->disconnect();
        }
        catch(\Throwable $e){
            /** Regardless of what happens, we should always set the quickbooks object to null */
            $company->quickbooks = null;
            $company->save();

        }
        
        return response()->noContent();
    }
}
