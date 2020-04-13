<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
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
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Factory\CompanyFactory;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Migration\UploadMigrationFileRequest;
use App\Jobs\Account\CreateAccount;
use App\Jobs\Util\StartMigration;
use App\Mail\ExistingMigration;
use App\Mail\MigrationFailed;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyUserTransformer;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use function GuzzleHttp\Promise\queue;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class MigrationController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {
        parent::__construct();
    }


    /**
     *
     * Purge Company
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
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
     */
    public function purgeCompany(Company $company)
    {
        $company->delete();

        return response()->json(['message' => 'Company purged'], 200);
    }


    /**
     *
     * Purge Company but save settings
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
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
     */
    public function purgeCompanySaveSettings(Company $company)
    {
        $company->client->delete();
        $company->save();

        return response()->json(['message' => 'Settings preserved'], 200);
    }

    /**
     *
     * Start the migration from V1
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
     *          in="path",
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
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
     */
    public function startMigration(Request $request, Company $company)
    {
        $user = auth()->user();
        $existing_company = Company::where('company_key', $request->company_key)->first();

        info('Request key: ' . $request->company_key);

        if ($request->company_key !== $company->company_key) {
            info('Migration type: Fresh migration with new company. MigrationController::203');

            // If there's company with same 'company_key' as from request we would crash it
            // to avoid duplicate 'company_key' insert error. This should never happen, but just in case to prevent it.
            if ($existing_company) {
                Mail::to($user)->send(new ExistingMigration());
                return;
            }

            $account = (new ImportMigrations())->getAccount();
            $company = (new ImportMigrations())->getCompany($account);

            CompanyToken::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'account_id' => $account->id,
                'name' => $request->token_name ?? Str::random(12),
                'token' => $request->token ?? \Illuminate\Support\Str::random(64),
            ]);

            $user->companies()->attach($company->id, [
                'account_id' => $account->id,
                'is_owner' => 1,
                'is_admin' => 1,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'permissions' => '',
                'settings' => null,
            ]);
        }

        if (($request->company_key == $company->company_key) && ($request->has('force') && !empty($request->force))) {
            info('Migration type: Completely wipe company and start again. MigrationController::228');

            $this->purgeCompany($company);

            $account = (new ImportMigrations())->getAccount();
            $company = (new ImportMigrations())->getCompany($account);

            CompanyToken::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'account_id' => $account->id,
                'name' => $request->token_name ?? Str::random(12),
                'token' => $request->token ?? \Illuminate\Support\Str::random(64),
            ]);

            $user->companies()->attach($company->id, [
                'account_id' => $account->id,
                'is_owner' => 1,
                'is_admin' => 1,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'permissions' => '',
                'settings' => null,
            ]);
        }

        if (($request->company_key == $company->company_key) && !$request->force) {
            info('Migration type: Nothing, skip this since no "force" is provided.. MigrationController::255');

            Mail::to($user)->send(new ExistingMigration());

            return response()->json([
                '_id' => Str::uuid(),
                'method' => config('queue.default'),
                'started_at' => now(),
            ], 200);
        }

        $migration_file = $request->file('migration')
            ->storeAs('migrations', $request->file('migration')->getClientOriginalName());

        if (app()->environment() == 'testing') {
            return;
        }
        
        StartMigration::dispatch(base_path("storage/app/public/$migration_file"), $user, $company);

        return response()->json([
            '_id' => Str::uuid(),
            'method' => config('queue.default'),
            'started_at' => now(),
        ], 200);
    }
}
