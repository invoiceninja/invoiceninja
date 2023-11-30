<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Yodlee;

use App\Exceptions\NordigenApiException;
use App\Helpers\Bank\Nordigen\Transformer\AccountTransformer;
use App\Helpers\Bank\Nordigen\Transformer\IncomeTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Generate new access token. Token is valid for 24 hours
// Token is automatically injected into every response
$token = $client->createAccessToken();

// Get access token
$accessToken = $client->getAccessToken();
// Get refresh token
$refreshToken = $client->getRefreshToken();

// Exchange refresh token for new access token
$newToken = $client->refreshAccessToken($refreshToken);

// Get list of institutions by country. Country should be in ISO 3166 standard.
$institutions = $client->institution->getInstitutionsByCountry("LV");

// Institution id can be gathered from getInstitutions response.
// Example Revolut ID
$institutionId = "REVOLUT_REVOGB21";
$redirectUri = "https://nordigen.com";

// Initialize new bank connection session
$session = $client->initSession($institutionId, $redirectUri);

// Get link to authorize in the bank
// Authorize with your bank via this link, to gain access to account data
$link = $session["link"];
// requisition id is needed to get accountId in the next step
$requisitionId = $session["requisition_id"];

class Nordigen
{
    public bool $test_mode = false;

    protected \Nordigen\NordigenPHP\API\NordigenClient $client;

    protected string $secret_id;

    protected string $secret_key;

    public function __construct()
    {
        $this->secret_id = config('ninja.nordigen.secret_id');

        $this->secret_key = config('ninja.nordigen.secret_key');

        $this->client = new \Nordigen\NordigenPHP\API\NordigenClient($this->secret_id, $this->secret_key);
    }

    public function getInstitutions()
    {
        return $this->client->institution->getInstitutions();
    }

    public function getValidAccounts()
    {

        // get all valid requisitions
        $requisitions = $this->client->requisition->getRequisitions();

        // fetch all valid accounts for activated requisitions
        $accounts = [];
        foreach ($requisitions as $requisition) {
            foreach ($requisition->accounts as $account) {
                $account = $account = $this->client->account($account);

                array_push($accounts, $account);
            }
        }

        return $accounts;

    }

    public function cleanup()
    {
        $requisitions = $this->client->requisition->getRequisitions();

        // TODO: filter to older than 2 days created AND (no accounts or invalid)

        foreach ($requisitions as $requisition) {
            $this->client->requisition->deleteRequisition($requisition->id);
        }
    }

    // account-section: these methods should be used to get data of connected accounts

    public function getAccountMetaData(string $account_id)
    {
        return $this->client->account($account_id)->getAccountMetaData();
    }

    public function getAccountDetails(string $account_id)
    {
        return $this->client->account($account_id)->getAccountDetails();
    }

    public function getAccountBalances(string $account_id)
    {
        return $this->client->account($account_id)->getAccountBalances();
    }

    public function getAccountTransactions(string $account_id)
    {
        return $this->client->account($account_id)->getAccountTransactions();
    }

}
