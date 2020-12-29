<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Console\Commands\ImportMigrations;
use App\DataMapper\CompanySettings;
use App\Jobs\Mail\MailRouter;
use App\Jobs\Util\StartMigration;
use App\Mail\ExistingMigration;
use App\Models\Company;
use App\Models\CompanyToken;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MigrationController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Purge Company.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge/{company}",
     *      operationId="postPurgeCompany",
     *      tags={"migration"},
     *      summary="Attempts to purge a company record and all its child records",
     *      description="Attempts to purge a company record and all its child records",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="company",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function purgeCompany(Company $company)
    {
        $account = $company->account;
        $company_id = $company->id;

        $company->delete();

        /*Update the new default company if necessary*/
        if ($company_id == $account->default_company_id && $account->companies->count() >= 1) {
            $new_default_company = $account->companies->first();

            if ($new_default_company) {
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }

        return response()->json(['message' => 'Company purged'], 200);
    }

    private function purgeCompanyWithForceFlag(Company $company)
    {
        $account = $company->account;
        $company_id = $company->id;

        $company->delete();

        /*Update the new default company if necessary*/
        if ($company_id == $account->default_company_id && $account->companies->count() >= 1) {
            $new_default_company = $account->companies->first();

            if ($new_default_company) {
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }
    }

    /**
     * Purge Company but save settings.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge_save_settings/{company}",
     *      operationId="postPurgeCompanySaveSettings",
     *      tags={"migration"},
     *      summary="Attempts to purge a companies child records but save the company record and its settings",
     *      description="Attempts to purge a companies child records but save the company record and its settings",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="company",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Request $request
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function purgeCompanySaveSettings(Request $request, Company $company)
    {
        $company->clients()->forceDelete();
        $company->products()->forceDelete();

        $company->save();

        return response()->json(['message' => 'Settings preserved'], 200);
    }

    /**
     * Start the migration from V1.
     *
     * @OA\Post(
     *      path="/api/v1/migration/start",
     *      operationId="postStartMigration",
     *      tags={"migration"},
     *      summary="Starts the migration from previous version of Invoice Ninja",
     *      description="Starts the migration from previous version of Invoice Ninja",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Password"),
     *      @OA\Parameter(
     *          name="migration",
     *          in="query",
     *          description="The migraton file",
     *          example="migration.zip",
     *          required=true,
     *          @OA\Schema(
     *              type="object",
     *              format="file",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Request $request
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function startMigration(Request $request)
    {
        $companies = json_decode($request->companies);

        if (app()->environment() === 'local') {
            nlog($request->all());
        }

        foreach ($companies as $company) {
            $is_valid = $request->file($company->company_index)->isValid();

            if (!$is_valid) {
                // We might want to send user something's wrong with migration or nope?
                continue;
            }

            $user = auth()->user();

            // Look for possible existing company (based on company keys).
            $existing_company = Company::whereRaw('BINARY `company_key` = ?', [$company->company_key])->first();

            $checks = [
                'existing_company' => $existing_company ? (bool)1 : false,
                'force' => property_exists($company, 'force') ? (bool) $company->force : false,
            ];

            // If there's existing company and ** no ** force is provided - skip migration.
            if ($checks['existing_company'] == true && $checks['force'] == false) {
                nlog('Migrating: Existing company without force. (CASE_01)');

                MailRouter::dispatch(new ExistingMigration(), $existing_company, $user);

                return response()->json([
                    '_id' => Str::uuid(),
                    'method' => config('queue.default'),
                    'started_at' => now(),
                ], 200);
            }

            // If there's existing company and force ** is provided ** - purge the company and migrate again.
            if ($checks['existing_company'] == true && $checks['force'] == true) {
                nlog("purging the existing company here");
                $this->purgeCompanyWithForceFlag($existing_company);

                $account = auth()->user()->account;
                $fresh_company = (new ImportMigrations())->getCompany($account);
                $fresh_company->is_disabled = true;
                $fresh_company->save();

                $account->default_company_id = $fresh_company->id;
                $account->save();

                $fresh_company_token = new CompanyToken();
                $fresh_company_token->user_id = $user->id;
                $fresh_company_token->company_id = $fresh_company->id;
                $fresh_company_token->account_id = $account->id;
                $fresh_company_token->name = $request->token_name ?? Str::random(12);
                $fresh_company_token->token = $request->token ?? Str::random(64);
                $fresh_company_token->is_system = true;
                $fresh_company_token->save();

                $user->companies()->attach($fresh_company->id, [
                    'account_id' => $account->id,
                    'is_owner' => 1,
                    'is_admin' => 1,
                    'is_locked' => 0,
                    'notifications' => CompanySettings::notificationDefaults(),
                    'permissions' => '',
                    'settings' => null,
                ]);
            }

            // If there's no existing company migrate just normally.
            if ($checks['existing_company'] == false) {
                $account = auth()->user()->account;
                $fresh_company = (new ImportMigrations())->getCompany($account);

                $fresh_company->is_disabled = true;
                $fresh_company->save();

                $fresh_company_token = new CompanyToken();
                $fresh_company_token->user_id = $user->id;
                $fresh_company_token->company_id = $fresh_company->id;
                $fresh_company_token->account_id = $account->id;
                $fresh_company_token->name = $request->token_name ?? Str::random(12);
                $fresh_company_token->token = $request->token ?? Str::random(64);
                $fresh_company_token->is_system = true;

                $fresh_company_token->save();

                $user->companies()->attach($fresh_company->id, [
                    'account_id' => $account->id,
                    'is_owner' => 1,
                    'is_admin' => 1,
                    'is_locked' => 0,
                    'notifications' => CompanySettings::notificationDefaults(),
                    'permissions' => '',
                    'settings' => null,
                ]);
            }

            $migration_file = $request->file($company->company_index)
                ->storeAs(
                    'migrations',
                    $request->file($company->company_index)->getClientOriginalName(),
                    'public'
                );

            if (app()->environment() == 'testing') {
                return;
            }

            try {
                // StartMigration::dispatch(base_path("storage/app/public/$migration_file"), $user, $fresh_company)->delay(now()->addSeconds(5));
                nlog($migration_file);
                StartMigration::dispatch($migration_file, $user, $fresh_company);
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        }

        return response()->json([
            '_id' => Str::uuid(),
            'method' => config('queue.default'),
            'started_at' => now(),
        ], 200);
    }
}
