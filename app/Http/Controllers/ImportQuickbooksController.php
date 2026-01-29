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

use App\Http\Requests\Quickbooks\AuthorizedQuickbooksRequest;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Quickbooks\AuthQuickbooksRequest;
use App\Services\Quickbooks\QuickbooksService;

class ImportQuickbooksController extends BaseController
{
    
    /**
     * authorizeQuickbooks
     *
     * Starts the Quickbooks authorization process.
     * 
     * @param  AuthQuickbooksRequest $request
     * @param  string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeQuickbooks(AuthQuickbooksRequest $request, string $token)
    {

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        
        $company = $request->getCompany();
        
        $qb = new QuickbooksService($company);

        $authorizationUrl = $qb->sdk()->getAuthorizationUrl();

        $state = $qb->sdk()->getState();

        Cache::put($state, $token, 190);

        return redirect()->to($authorizationUrl);
    }
    
    /**
     * onAuthorized
     * 
     * Handles the callback from Quickbooks after authorization.
     * 
     * @param  AuthorizedQuickbooksRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function onAuthorized(AuthorizedQuickbooksRequest $request)
    {

        nlog($request->all());

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        $company = $request->getCompany();
        
        $qb = new QuickbooksService($company);

        $realm = $request->query('realmId');
        
        nlog($realm);

        $access_token_object = $qb->sdk()->accessTokenFromCode($request->query('code'), $realm);
        
        nlog($access_token_object);
        
        $qb->sdk()->saveOAuthToken($access_token_object);

        // Refresh the service to initialize SDK with the new access token
        $qb->refresh();

        $companyInfo = $qb->sdk()->company();
        
        $company->quickbooks->companyName = $companyInfo->CompanyName;
        $company->save();
        
        nlog($companyInfo);

        return redirect(config('ninja.react_url'));

    }



}
