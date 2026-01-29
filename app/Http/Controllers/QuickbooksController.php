<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Libraries\MultiDB;
use Illuminate\Http\Response;
use App\Services\Quickbooks\QuickbooksService;
use App\Http\Requests\Quickbooks\SyncQuickbooksRequest;
use App\Http\Requests\Quickbooks\ConfigQuickbooksRequest;
use App\Http\Requests\Quickbooks\DisconnectQuickbooksRequest;

class QuickbooksController extends BaseController
{

    public function sync(SyncQuickbooksRequest $request)
    {
        
        return response()->noContent();
    }

    public function configuration(ConfigQuickbooksRequest $request)
    {
        
        $user = auth()->user();
        $company = $user->company();
        
        $quickbooks = $company->quickbooks;
        $quickbooks->settings->client->direction = $request->clients ? SyncDirection::PUSH : SyncDirection::NONE;
        $quickbooks->settings->vendor->direction = $request->vendors ? SyncDirection::PUSH : SyncDirection::NONE;
        $quickbooks->settings->product->direction = $request->products ? SyncDirection::PUSH : SyncDirection::NONE;
        $quickbooks->settings->invoice->direction = $request->invoices ? SyncDirection::PUSH : SyncDirection::NONE;
        $quickbooks->settings->quote->direction = $request->quotes ? SyncDirection::PUSH : SyncDirection::NONE;
        $quickbooks->settings->payment->direction = $request->payments ? SyncDirection::PUSH : SyncDirection::NONE;
        $company->quickbooks = $quickbooks;
        $company->save();

        return response()->noContent();
    }

    public function disconnect(DisconnectQuickbooksRequest $request)
    {
        
        $user = auth()->user();
        $company = $user->company();

        $qb = new QuickbooksService($company);
        $rs = $qb->sdk()->revokeAccessToken();
        
        nlog($rs);
        
        $company->quickbooks = null;
        $company->save();

        return response()->noContent();
    }
}