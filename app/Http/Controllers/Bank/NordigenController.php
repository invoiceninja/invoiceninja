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
use App\Http\Requests\Nordigen\CreateNordigenRequisitionRequest;
use App\Http\Requests\Yodlee\YodleeAuthRequest;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\BankIntegration;
use App\Models\Company;
use Cache;
use Illuminate\Http\Request;

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
     * @param CreateNordigenRequisitionRequest $request
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
    public function connect(CreateNordigenRequisitionRequest $request) // TODO: error, when using class CreateNordigenRequisitionRequest
    {

        $account = auth()->user()->account;

        if (!$account->bank_integration_nordigen_secret_id || !$account->bank_integration_nordigen_secret_key)
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);

        $data = $request->all();

        $context = Cache::get($data["context"]);

        if (!$context || $context->context != "nordigen")
            return response()->json(['message' => 'Invalid context provided. Call /api/v1/one_time_token with context: \'nordigen\' first.'], 400);

        $nordigen = new Nordigen($account->bank_integration_nordigen_secret_id, $account->bank_integration_nordigen_secret_key);

        return response()->json([
            'result' => $nordigen->createRequisition($data['redirect'], $data['institutionId'], $data["context"])
        ]);
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
    public function confirm(Request $request)
    {

        $data = $request->all();

        $context = Cache::get($data["reference"]);

        if (!$context || $context->context != "nordigen")
            return response()->json(['message' => 'Invalid context provided. Call /api/v1/one_time_token with context: \'nordigen\' first.'], 400);

        $company = Company::where('id', $context["company_key"])->first(); // TODO: get from one-time-token

        $account = $company->account;

        if (!$account->bank_integration_nordigen_secret_id || !$account->bank_integration_nordigen_secret_key)
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);

        $nordigen = new Nordigen($account->bank_integration_nordigen_secret_id, $account->bank_integration_nordigen_secret_key);

        $requisition = $nordigen->getRequisition($data["requisitionId"]);

        foreach ($requisition["accounts"] as $accountId) {

            $account = $nordigen->getAccount($accountId);

            if (!BankIntegration::where('bank_account_id', $account['id'])->where('company_id', $company->id)->exists()) {

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
                $bank_integration->from_date = now()->subYear();

                $bank_integration->save();

            }

        }


        $company->account->bank_integrations->each(function ($bank_integration) use ($company) {

            ProcessBankTransactionsNordigen::dispatch($company->account, $bank_integration);

        });

        // TODO: get current frontend-url from hash
        response()->redirectTo();

    }

}
