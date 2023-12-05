<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 *
 * Documentation of Api-Usage: https://developer.gocardless.com/bank-account-data/overview
 */

namespace App\Helpers\Bank\Nordigen;

use App\Exceptions\NordigenApiException;
use App\Helpers\Bank\Nordigen\Transformer\AccountTransformer;
use App\Helpers\Bank\Nordigen\Transformer\IncomeTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Nordigen
{
    public bool $test_mode = false; // https://developer.gocardless.com/bank-account-data/sandbox

    public string $sandbox_institutionId = "SANDBOXFINANCE_SFIN0000";

    protected \Nordigen\NordigenPHP\API\NordigenClient $client;

    public function __construct(string $secret_id, string $secret_key)
    {

        Log::info($secret_id);
        Log::info($secret_key);

        $this->client = new \Nordigen\NordigenPHP\API\NordigenClient($secret_id, $secret_key);

        $this->client->createAccessToken(); // access_token is valid 24h -> so we dont have to implement a refresh-cycle
    }

    // metadata-section for frontend
    public function getInstitutions()
    {
        if ($this->test_mode)
            return (array) $this->client->institution->getInstitution($this->sandbox_institutionId);

        return $this->client->institution->getInstitutions();
    }

    // requisition-section
    public function createRequisition(string $redirect, string $initutionId, string $nAccountId)
    {
        if ($this->test_mode && $initutionId != $this->sandbox_institutionId)
            throw new \Exception('invalid institutionId while in test-mode');

        return $this->client->requisition->createRequisition($redirect, $initutionId, null, $nAccountId); // we dont reuse existing requisitions, to prevent double usage of them. see: deleteAccount
    }

    public function getRequisition(string $requisitionId)
    {
        return $this->client->requisition->getRequisition($requisitionId);
    }

    public function cleanupRequisitions()
    {
        $requisitions = $this->client->requisition->getRequisitions();

        foreach ($requisitions as $requisition) {
            // filter to expired OR older than 7 days created and no accounts
            if ($requisition->status == "EXPIRED" || (sizeOf($requisition->accounts) != 0 && strtotime($requisition->created) > (new \DateTime())->modify('-7 days')))
                continue;

            $this->client->requisition->deleteRequisition($requisition->id);
        }
    }

    // account-section: these methods should be used to get data of connected accounts
    public function getAccounts()
    {

        // get all valid requisitions
        $requisitions = $this->client->requisition->getRequisitions(); // no pagination used?!

        // fetch all valid accounts for activated requisitions
        $nordigen_accountIds = [];
        foreach ($requisitions["results"] as $requisition) {
            foreach ($requisition["accounts"] as $accountId) {
                array_push($nordigen_accountIds, $accountId);
            }
        }

        $nordigen_accountIds = array_unique($nordigen_accountIds);

        Log::info($nordigen_accountIds);

        $nordigen_accounts = [];
        foreach ($nordigen_accountIds as $accountId) {
            $nordigen_account = $this->getAccount($accountId);

            array_push($nordigen_accounts, $nordigen_account);
        }

        Log::info($nordigen_accounts);

        return $nordigen_accounts;

    }

    public function getAccount(string $account_id)
    {

        $out = new \stdClass();

        $out->data = $this->client->account($account_id)->getAccountDetails()["account"];
        $out->metadata = $this->client->account($account_id)->getAccountMetaData();
        $out->balances = $this->client->account($account_id)->getAccountBalances()["balances"];
        $out->institution = $this->client->institution->getInstitution($out->metadata["institution_id"]);

        Log::info($out->data);

        $it = new AccountTransformer();
        return $it->transform($out);

    }

    public function isAccountActive(string $account_id)
    {

        try {
            $account = $this->client->account($account_id)->getAccountMetaData();

            if ($account["status"] != "READY")
                return false;

            return true;
        } catch (\Exception $e) {
            // TODO: check for not-found exception
            return false;
        }

    }

    /**
     * this method will remove all according requisitions => this can result in removing multiple accounts, if a user reuses a requisition
     */
    public function deleteAccount(string $account_id)
    {

        // get all valid requisitions
        $requisitions = $this->client->requisition->getRequisitions();

        // fetch all valid accounts for activated requisitions
        foreach ($requisitions as $requisition) {
            foreach ($requisition->accounts as $accountId) {

                if ($accountId) {
                    $this->client->requisition->deleteRequisition($accountId);
                }

            }
        }

    }

    public function getTransactions(string $accountId, string $dateFrom = null)
    {

        return $this->client->account($accountId)->getAccountTransactions($dateFrom);

    }
}
