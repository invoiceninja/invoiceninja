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

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\Nordigen\Nordigen;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Nordigen\ConfirmNordigenRequisitionRequest;
use App\Http\Requests\Nordigen\ConnectNordigenRequisitionRequest;
use App\Http\Requests\Yodlee\YodleeAuthRequest;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\BankIntegration;
use App\Models\Company;
use Cache;
use Illuminate\Http\Request;
use Log;

class NordigenController extends BaseController
{

    // TODO!!!!!
    public function auth(YodleeAuthRequest $request)
    {

        // create a user at this point
        // use the one time token here to pull in the actual user
        // store the user_account_id on the accounts table

        $nordigen = new Nordigen();

        $company = $request->getCompany();


        //ensure user is enterprise!!

        if ($company->account->bank_integration_nordigen_secret_id && $company->account->bank_integration_nordigen_secret_id) {

            $flow = 'edit';

            $token = $company->account->bank_integration_nordigen_secret_id;

        } else {

            $flow = 'add';

            $response = $nordigen->createUser($company);

            $token = $response->user->loginName;

            $company->account->bank_integration_nordigen_secret_id = $token;

            $company->push();

        }

        $yodlee = new Yodlee($token);

        if ($request->has('window_closed') && $request->input("window_closed") == "true")
            $this->getAccounts($company, $token);

        $data = [
            'access_token' => $yodlee->getAccessToken(),
            'fasttrack_url' => $yodlee->getFastTrackUrl(),
            'config_name' => config('ninja.yodlee.config_name'),
            'flow' => $flow,
            'company' => $company,
            'account' => $company->account,
            'completed' => $request->has('window_closed') ? true : false,
        ];

        return view('bank.yodlee.auth', $data);

    }
    /**
     * Process Nordigen Institutions GETTER.
     *
     *
     * @OA\Post(
     *      path="/api/v1/nordigen/institutions",
     *      operationId="nordigenRefreshWebhook",
     *      tags={"nordigen"},
     *      summary="Getting available institutions from nordigen",
     *      description="Used to determine the available institutions for sending and creating a new connect-link",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */

    /*
    {
      "event":{
         "info":"REFRESH.PROCESS_COMPLETED",
         "loginName":"fri21",
         "data":{
            "providerAccount":[
               {
                  "id":10995860,
                  "providerId":16441,
                  "isManual":false,
                  "createdDate":"2017-12-22T05:47:35Z",
                  "aggregationSource":"USER",
                  "status":"SUCCESS",
                  "requestId":"NSyMGo+R4dktywIu3hBIkc3PgWA=",
                  "dataset":[
                     {
                        "name":"BASIC_AGG_DATA",
                        "additionalStatus":"AVAILABLE_DATA_RETRIEVED",
                        "updateEligibility":"ALLOW_UPDATE",
                        "lastUpdated":"2017-12-22T05:48:16Z",
                        "lastUpdateAttempt":"2017-12-22T05:48:16Z"
                     }
                  ]
               }
            ]
         }
      }
   }*/
    public function institutions(Request $request)
    {
        $account = auth()->user()->account;

        if (!$account->bank_integration_nordigen_secret_id || !$account->bank_integration_nordigen_secret_key)
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);

        $nordigen = new Nordigen($account->bank_integration_nordigen_secret_id, $account->bank_integration_nordigen_secret_key);
        return response()->json($nordigen->getInstitutions());
    }

    /** Creates a new requisition (oAuth like connection of bank-account)
     *
     * @param ConnectNordigenRequisitionRequest $request
     *
     * @OA\Post(
     *      path="/api/v1/nordigen/institutions",
     *      operationId="nordigenRefreshWebhook",
     *      tags={"nordigen"},
     *      summary="Getting available institutions from nordigen",
     *      description="Used to determine the available institutions for sending and creating a new connect-link",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */

    /* TODO
    {
      "event":{
         "info":"REFRESH.PROCESS_COMPLETED",
         "loginName":"fri21",
         "data":{
            "providerAccount":[
               {
                  "id":10995860,
                  "providerId":16441,
                  "isManual":false,
                  "createdDate":"2017-12-22T05:47:35Z",
                  "aggregationSource":"USER",
                  "status":"SUCCESS",
                  "requestId":"NSyMGo+R4dktywIu3hBIkc3PgWA=",
                  "dataset":[
                     {
                        "name":"BASIC_AGG_DATA",
                        "additionalStatus":"AVAILABLE_DATA_RETRIEVED",
                        "updateEligibility":"ALLOW_UPDATE",
                        "lastUpdated":"2017-12-22T05:48:16Z",
                        "lastUpdateAttempt":"2017-12-22T05:48:16Z"
                     }
                  ]
               }
            ]
         }
      }
   }*/
    public function connect(ConnectNordigenRequisitionRequest $request)
    {

        $account = auth()->user()->account;
        if (!$account->bank_integration_nordigen_secret_id || !$account->bank_integration_nordigen_secret_key)
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);

        $data = $request->all();

        $context = Cache::get($data["hash"]);
        Log::info($context);
        if (!$context || $context["context"] != "nordigen" || array_key_exists("requisition", $context)) // TODO: check for requisition array key
            return response()->json(['message' => 'Invalid context one_time_token. (not-found|invalid-context|already-used) Call /api/v1/one_time_token with context: \'nordigen\' first.'], 400);

        Log::info(config('ninja.app_url') . '/api/v1/nordigen/confirm');

        $nordigen = new Nordigen($account->bank_integration_nordigen_secret_id, $account->bank_integration_nordigen_secret_key);
        $requisition = $nordigen->createRequisition(config('ninja.app_url') . '/api/v1/nordigen/confirm', $data['institutionId'], $data["hash"]);

        // save cache
        if (array_key_exists("redirectUri", $data))
            $context["redirectUri"] = $data["redirectUri"];
        $context["requisitionId"] = $requisition["id"];
        Cache::put($data["hash"], $context, 3600);

        return response()->json([
            'result' => $requisition,
            'redirectUri' => array_key_exists("redirectUri", $data) ? $data["redirectUri"] : null,
        ]);

    }

    /**
     * Process Nordigen Institutions GETTER.
     * @param ConfirmNordigenRequisitionRequest $request
     *
     * @OA\Post(
     *      path="/api/v1/nordigen/institutions",
     *      operationId="nordigenRefreshWebhook",
     *      tags={"nordigen"},
     *      summary="Getting available institutions from nordigen",
     *      description="Used to determine the available institutions for sending and creating a new connect-link",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */

    /*
    {
      "event":{
         "info":"REFRESH.PROCESS_COMPLETED",
         "loginName":"fri21",
         "data":{
            "providerAccount":[
               {
                  "id":10995860,
                  "providerId":16441,
                  "isManual":false,
                  "createdDate":"2017-12-22T05:47:35Z",
                  "aggregationSource":"USER",
                  "status":"SUCCESS",
                  "requestId":"NSyMGo+R4dktywIu3hBIkc3PgWA=",
                  "dataset":[
                     {
                        "name":"BASIC_AGG_DATA",
                        "additionalStatus":"AVAILABLE_DATA_RETRIEVED",
                        "updateEligibility":"ALLOW_UPDATE",
                        "lastUpdated":"2017-12-22T05:48:16Z",
                        "lastUpdateAttempt":"2017-12-22T05:48:16Z"
                     }
                  ]
               }
            ]
         }
      }
   }*/
    public function confirm(ConfirmNordigenRequisitionRequest $request)
    {

        $data = $request->all();

        $context = Cache::get($data["ref"]);
        if (!$context || $context["context"] != "nordigen" || !array_key_exists("requisitionId", $context)) {
            if ($context && array_key_exists("redirectUri", $context))
                return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=failed&reason=ref-invalid");

            return response()->json([
                'status' => 'failed',
                'reason' => 'ref-invalid',
            ], 400);
        }

        $company = Company::where('company_key', $context["company_key"])->first();
        $account = $company->account;

        if (!$account->bank_integration_nordigen_secret_id || !$account->bank_integration_nordigen_secret_key) {
            if (array_key_exists("redirectUri", $context))
                return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=failed&reason=account-config-invalid");

            return response()->json([
                'status' => 'failed',
                'reason' => 'account-config-invalid',
            ], 400);
        }

        // fetch requisition
        $nordigen = new Nordigen($account->bank_integration_nordigen_secret_id, $account->bank_integration_nordigen_secret_key);
        $requisition = $nordigen->getRequisition($context["requisitionId"]);

        // check validity of requisition
        if (!$requisition) {
            if (array_key_exists("redirectUri", $context))
                return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=failed&reason=requisition-not-found");

            return response()->json([
                'status' => 'failed',
                'reason' => 'requisition-not-found',
            ], 400);
        }
        if ($requisition["status"] != "LN") {
            if (array_key_exists("redirectUri", $context))
                return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=failed&reason=requisition-invalid-status");

            return response()->json([
                'status' => 'failed',
                'reason' => 'requisition-invalid-status',
            ], 400);
        }
        if (sizeof($requisition["accounts"]) == 0) {
            if (array_key_exists("redirectUri", $context))
                return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=failed&reason=requisition-no-accounts");

            return response()->json([
                'status' => 'failed',
                'reason' => 'requisition-no-accounts',
            ], 400);
        }


        // connect new accounts
        $bank_integration_ids = [];
        foreach ($requisition["accounts"] as $accountId) {

            $account = $nordigen->getAccount($accountId);

            $existing_bank_integration = BankIntegration::where('bank_account_id', $account['id'])->where('company_id', $company->id)->first();

            if (!$existing_bank_integration) {

                $bank_integration = new BankIntegration();
                $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_NORDIGEN;
                $bank_integration->company_id = $company->id;
                $bank_integration->account_id = $company->account_id;
                $bank_integration->user_id = $company->owner()->id;
                $bank_integration->bank_account_id = $account['id'];
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_status = $account['account_status'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_id = $account['provider_id'];
                $bank_integration->provider_name = $account['provider_name'];
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->balance = $account['current_balance'];
                $bank_integration->currency = $account['account_currency'];
                $bank_integration->disabled_upstream = false;
                $bank_integration->auto_sync = true;
                $bank_integration->from_date = now()->subYear();

                $bank_integration->save();

                array_push($bank_integration_ids, $bank_integration->id);

            } else {

                // resetting metadata for account status
                $existing_bank_integration->balance = $account['current_balance'];
                $existing_bank_integration->bank_account_status = $account['account_status'];
                $existing_bank_integration->disabled_upstream = false;
                $existing_bank_integration->auto_sync = true;

                $existing_bank_integration->save();

                array_push($bank_integration_ids, $existing_bank_integration->id);
            }

        }

        // perform update in background
        $company->account->bank_integrations->each(function ($bank_integration) use ($company) {

            ProcessBankTransactionsNordigen::dispatch($company->account, $bank_integration);

        });

        // prevent rerun of this method with same ref
        Cache::delete($data["ref"]);

        // Successfull Response
        if (array_key_exists("redirectUri", $context))
            return response()->redirectTo($context["redirectUri"] . "?action=nordigen_connect&status=success&bank_integrations=" . implode(',', $bank_integration_ids));

        return response()->json([
            'status' => 'success',
            'bank_integrations' => $bank_integration_ids,
        ]);

    }

}
