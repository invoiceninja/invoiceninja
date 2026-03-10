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
     * reconnect
     *
     * Starts the Quickbooks re-authorization process for an existing connection.
     * Stores the expected realmID to validate on return.
     *
     * @param  AuthQuickbooksRequest $request
     * @param  string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reconnect(AuthQuickbooksRequest $request, string $token)
    {
        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $company = $request->getCompany();

        // Ensure there's an existing QB connection to reconnect
        if (!$company->quickbooks || !$company->quickbooks->isConfigured()) {
            return redirect(config('ninja.react_url') . '?error=no_existing_connection');
        }

        $qb = new QuickbooksService($company);

        $authorizationUrl = $qb->sdk()->getAuthorizationUrl();
        $state = $qb->sdk()->getState();

        // Store token AND expected realmID for validation on return
        Cache::put($state, [
            'token' => $token,
            'expected_realm_id' => $company->quickbooks->realmID,
            'is_reconnect' => true,
        ], 190);

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

        $state_data = Cache::get($request->query('state'));
        
        // Handle both old format (string token) and new format (array with metadata)
        $expected_realm_id = is_array($state_data) ? ($state_data['expected_realm_id'] ?? null) : null;
        $is_reconnect = is_array($state_data) ? ($state_data['is_reconnect'] ?? false) : false;

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $company = $request->getCompany();
        $realm = $request->query('realmId');

        // Validate realmID matches for reconnections
        if ($is_reconnect && $expected_realm_id && $realm !== $expected_realm_id) {
            nlog("QB Reconnect: realmID mismatch. Expected: {$expected_realm_id}, Got: {$realm}");
            return redirect(config('ninja.react_url') . '?error=realm_mismatch&message=' . urlencode(
                'You must reconnect to the same QuickBooks company. Expected company ID: ' . $expected_realm_id
            ));
        }

        $qb = new QuickbooksService($company);

        nlog($realm);

        $access_token_object = $qb->sdk()->accessTokenFromCode($request->query('code'), $realm);

        nlog($access_token_object);

        // saveOAuthToken resets requires_reconnect = false
        $qb->sdk()->saveOAuthToken($access_token_object);

        // Refresh the service to initialize SDK with the new access token
        $qb->refresh();

        // Sync the company information from Quickbooks to Invoice Ninja
        $qb->companySync();

        $redirect_url = $is_reconnect 
            ? config('ninja.react_url') . '?quickbooks_reconnected=true'
            : config('ninja.react_url');

        return redirect($redirect_url);
    }
}
