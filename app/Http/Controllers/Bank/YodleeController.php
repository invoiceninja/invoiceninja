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

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Yodlee\YodleeAdminRequest;
use App\Http\Requests\Yodlee\YodleeAuthRequest;
use App\Jobs\Bank\ProcessBankTransactionsYodlee;
use App\Models\BankIntegration;
use Illuminate\Http\Request;

class YodleeController extends BaseController
{
    public function auth(YodleeAuthRequest $request)
    {

        $yodlee = new Yodlee();

        $company = $request->getCompany();

        if ($company->account->bank_integration_account_id) {
            $flow = 'edit';

            $token = $company->account->bank_integration_account_id;
        } else {
            $flow = 'add';

            $response = $yodlee->createUser($company);

            $token = $response->user->loginName;

            $company->account->bank_integration_account_id = $token;

            $company->push();
        }

        $yodlee = new Yodlee($token);

        if ($request->has('window_closed') && $request->input("window_closed") == "true") {
            $this->getAccounts($company, $token);
        }

        $redirect_url = isset($request->getTokenContent()['is_react']) && $request->getTokenContent()['is_react'] ? config('ninja.react_url') : config('ninja.app_url');

        $data = [
            'access_token' => $yodlee->getAccessToken(),
            'fasttrack_url' => $yodlee->getFastTrackUrl(),
            'config_name' => config('ninja.yodlee.config_name'),
            'flow' => $flow,
            'company' => $company,
            'account' => $company->account,
            'completed' => $request->has('window_closed') ? true : false,
            'redirect_url' => $redirect_url,
        ];

        return view('bank.yodlee.auth', $data);
    }

    private function getAccounts($company, $token)
    {
        $yodlee = new Yodlee($token);

        $accounts = $yodlee->getAccounts();

        foreach ($accounts as $account) {
            if (!BankIntegration::where('bank_account_id', $account['id'])->where('company_id', $company->id)->exists()) {
                $bank_integration = new BankIntegration();
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
                $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_YODLEE;
                $bank_integration->auto_sync = true;

                $bank_integration->save();
            }
        }

        $company->account->bank_integrations->where("integration_type", BankIntegration::INTEGRATION_TYPE_YODLEE)->where('auto_sync', true)->each(function ($bank_integration) use ($company) { // TODO: filter to yodlee only
            ProcessBankTransactionsYodlee::dispatch($company->account->bank_integration_account_id, $bank_integration);
        });
    }

    /**
     * Process Yodlee Refresh Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/yodlee/refresh",
     *      operationId="yodleeRefreshWebhook",
     *      tags={"yodlee"},
     *      summary="Processing webhooks from Yodlee",
     *      description="Notifies the system when a data point can be refreshed",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
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
    public function refreshWebhook(Request $request)
    {
        //we should ignore this one
        // nlog("yodlee refresh");
        // nlog($request->all());

        return response()->json(['message' => 'Success'], 200);

        //

        // return response()->json(['message' => 'Unauthorized'], 403);
    }

    /*
    {
       "event":{
          "notificationId":"63c73475-4db5-49ef-8553-8303337ca7c3",
          "info":"LATEST_BALANCE_UPDATES",
          "loginName":"user1",
          "data":{
             "providerAccountId":658552,
             "latestBalanceEvent":[
                {
                   "accountId":12345,
                   "status":"SUCCESS"
                },
                {
                   "accountId":12346,
                   "status":"FAILED"
                }
             ]
          }
       }
    }
    */
    public function balanceWebhook(Request $request)
    {
        nlog("yodlee refresh");
        nlog($request->all());

        return response()->json(['message' => 'Success'], 200);

        //

        // return response()->json(['message' => 'Unauthorized'], 403);
    }

    /*
    {
       "event":{
          "data":[
             {
                "autoRefresh":{
                   "additionalStatus":"SCHEDULED",
                   "status":"ENABLED"
                },
                "accountIds":[
                   1112645899,
                   1112645898
                ],
                "loginName":"YSL1555332811628",
                "providerAccountId":11381459
             }
          ],
          "notificationTime":"2019-06-14T04:49:39Z",
          "notificationId":"4e672150-156048777",
          "info":"AUTO_REFRESH_UPDATES"
       }
    }
    */
    public function refreshUpdatesWebhook(Request $request)
    {
        //notifies a user if there are problems with yodlee accessing the data
        // nlog("update refresh");
        // nlog($request->all());

        return response()->json(['message' => 'Success'], 200);

        //

        // return response()->json(['message' => 'Unauthorized'], 403);
    }


    /*
    "event": {
        "notificationId": "64b7ed1a-1530523285",
        "info": "DATA_UPDATES.USER_DATA",
        "data": {
            "userCount": 1,
            "fromDate": "2017-11-10T10:18:44Z",
            "toDate": "2017-11-10T11:18:43Z",
            "userData": [{
                "user": {
                    "loginName": "YSL1484052178554"
                },
                "links": [{
                    "methodType": "GET",
                    "rel": "getUserData",
                    "href": "dataExtracts/userData?fromDate=2017-11-10T10:18:44Z&toDate=2017-11-10T11:18:43Z&loginName=YSL1484052178554"
                }]
            }]
        }
    }
    */
    public function dataUpdatesWebhook(Request $request)
    {
        //this is the main hook we use for notifications


        return response()->json(['message' => 'Success'], 200);

        //

        // return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function accountStatus(YodleeAdminRequest $request, $account_number)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $bank_integration = BankIntegration::query()
            ->withTrashed()
            ->where('company_id', $user->company()->id)
            ->where('account_id', $account_number)
            ->exists();

        if (!$bank_integration) {
            return response()->json(['message' => 'Account does not exist.'], 400);
        }

        $yodlee = new Yodlee($user->account->bank_integration_account_id);

        $summary = $yodlee->getAccountSummary($account_number);

        $transformed_summary = $this->transformSummary($summary[0]);

        return response()->json($transformed_summary, 200);
    }

    private function transformSummary($summary): array
    {
        $dto = new \stdClass();
        $dto->id = $summary['id'] ?? 0;
        $dto->account_type = $summary['CONTAINER'] ?? '';

        $dto->account_status = $summary['accountStatus'] ?? '';
        $dto->account_number = $summary['accountNumber'] ?? '';
        $dto->provider_account_id = $summary['providerAccountId'] ?? '';
        $dto->provider_id = $summary['providerId'] ?? '';
        $dto->provider_name = $summary['providerName'] ?? '';
        $dto->nickname = $summary['nickname'] ?? '';
        $dto->account_name = $summary['accountName'] ?? '';
        $dto->current_balance = $summary['currentBalance']['amount'] ?? 0;
        $dto->account_currency = $summary['currentBalance']['currency'] ?? 0;

        return (array)$dto;

    }
}
