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
use App\Http\Requests\Nordigen\ConfirmNordigenBankIntegrationRequest;
use App\Http\Requests\Nordigen\ConnectNordigenBankIntegrationRequest;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\BankIntegration;
use App\Models\Company;
use App\Utils\Ninja;
use Cache;
use Illuminate\Http\Request;
use Log;
use Nordigen\NordigenPHP\Exceptions\NordigenExceptions\NordigenException;

class NordigenController extends BaseController
{

    public function connect(ConnectNordigenBankIntegrationRequest $request)
    {
        $data = $request->all();
        $context = $request->getTokenContent();

        if (!$context || $context["context"] != "nordigen" || array_key_exists("requisitionId", $context))
            return response()->redirectTo(($context && array_key_exists("redirect", $context) ? $context["redirect"] : config('ninja.app_url')) . "?action=nordigen_connect&status=failed&reason=token-invalid");

        $company = $request->getCompany();
        $account = $company->account;

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key')))
            return response()->redirectTo($data["redirect"] . "?action=nordigen_connect&status=failed&reason=account-config-invalid");

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise')))
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=not-available");

        $nordigen = new Nordigen();

        // show bank_selection_screen, when institution_id is not present
        if (!array_key_exists("institution_id", $data)) {
            $data = [
                'token' => $request->token,
                'context' => $context,
                'institutions' => $nordigen->getInstitutions(),
                'company' => $company,
                'account' => $company->account,
            ];

            return view('bank.nordigen.connect', $data);
        }

        // redirect to requisition flow
        try {
            $requisition = $nordigen->createRequisition(config('ninja.app_url') . '/api/v1/nordigen/confirm', $data['institution_id'], $request->token);
        } catch (NordigenException $e) { // TODO: property_exists returns null in these cases... => why => therefore we just get unknown error everytime $responseBody is typeof GuzzleHttp\Psr7\Stream
            Log::error($e);
            $responseBody = $e->getResponse()->getBody();
            Log::info($responseBody);

            if (property_exists($responseBody, "institution_id")) // provided institution_id was wrong
                return response()->redirectTo($data["redirect"] . "?action=nordigen_connect&status=failed&reason=institution-invalid");
            else if (property_exists($responseBody, "reference")) // this error can occur, when a reference was used double or is invalid => therefor we suggest the frontend to use another token
                return response()->redirectTo($data["redirect"] . "?action=nordigen_connect&status=failed&reason=token-invalid");
            else
                return response()->redirectTo($data["redirect"] . "?action=nordigen_connect&status=failed&reason=unknown");
        }

        // save cache
        if (array_key_exists("redirect", $data))
            $context["redirect"] = $data["redirect"];
        $context["requisitionId"] = $requisition["id"];
        Cache::put($request->token, $context, 3600);

        return response()->redirectTo($requisition["link"]);
    }

    /**
     * Process Nordigen Institutions GETTER.
     * @param ConfirmNordigenBankIntegrationRequest $request
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
    public function confirm(ConfirmNordigenBankIntegrationRequest $request)
    {

        $data = $request->all();

        $context = Cache::get($data["ref"]);
        if (!$context || $context["context"] != "nordigen" || !array_key_exists("requisitionId", $context))
            return response()->redirectTo(($context && array_key_exists("redirect", $context) ? $context["redirect"] : config('ninja.app_url')) . "?action=nordigen_connect&status=failed&reason=ref-invalid");


        $company = Company::where('company_key', $context["company_key"])->firstOrFail();
        $account = $company->account;

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key')))
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=account-config-invalid");

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise')))
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=not-available");

        // fetch requisition
        $nordigen = new Nordigen();
        $requisition = $nordigen->getRequisition($context["requisitionId"]);

        // check validity of requisition
        if (!$requisition)
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-not-found");
        if ($requisition["status"] != "LN")
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-invalid-status&status=" . $requisition["status"]);
        if (sizeof($requisition["accounts"]) == 0)
            return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-no-accounts");

        // connect new accounts
        $bank_integration_ids = [];
        foreach ($requisition["accounts"] as $nordigenAccountId) {

            $nordigen_account = $nordigen->getAccount($nordigenAccountId);

            $existing_bank_integration = BankIntegration::where('nordigen_account_id', $nordigen_account['id'])->where('company_id', $company->id)->first();

            if (!$existing_bank_integration) {

                $bank_integration = new BankIntegration();
                $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_NORDIGEN;
                $bank_integration->company_id = $company->id;
                $bank_integration->account_id = $company->account_id;
                $bank_integration->user_id = $company->owner()->id;
                $bank_integration->nordigen_account_id = $nordigen_account['id'];
                $bank_integration->bank_account_type = $nordigen_account['account_type'];
                $bank_integration->bank_account_name = $nordigen_account['account_name'];
                $bank_integration->bank_account_status = $nordigen_account['account_status'];
                $bank_integration->bank_account_number = $nordigen_account['account_number'];
                $bank_integration->nordigen_institution_id = $nordigen_account['provider_id'];
                $bank_integration->provider_name = $nordigen_account['provider_name'];
                $bank_integration->nickname = $nordigen_account['nickname'];
                $bank_integration->balance = $nordigen_account['current_balance'];
                $bank_integration->currency = $nordigen_account['account_currency'];
                $bank_integration->disabled_upstream = false;
                $bank_integration->auto_sync = true;
                $bank_integration->from_date = now()->subDays(90); // default max-fetch interval of nordigen is 90 days

                $bank_integration->save();

                array_push($bank_integration_ids, $bank_integration->id);

            } else {

                // resetting metadata for account status
                $existing_bank_integration->balance = $account['current_balance'];
                $existing_bank_integration->bank_account_status = $account['account_status'];
                $existing_bank_integration->disabled_upstream = false;
                $existing_bank_integration->auto_sync = true;
                $bank_integration->from_date = now()->subDays(90); // default max-fetch interval of nordigen is 90 days

                $existing_bank_integration->save();

                array_push($bank_integration_ids, $existing_bank_integration->id);
            }

        }

        // perform update in background
        $company->account->bank_integrations->where("integration_type", BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('auto_sync', true)->each(function ($bank_integration) {

            ProcessBankTransactionsNordigen::dispatch($bank_integration);

        });

        // prevent rerun of this method with same ref
        Cache::delete($data["ref"]);

        // Successfull Response => Redirect
        return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=success&bank_integrations=" . implode(',', $bank_integration_ids));

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

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key')))
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);

        $nordigen = new Nordigen();
        return response()->json($nordigen->getInstitutions());
    }

}
