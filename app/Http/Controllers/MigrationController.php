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
use Illuminate\Support\Facades\Mail;
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
     */
    public function startMigration(Request $request, Company $company)
    {
        $user = auth()->user();

        if (app()->environment() === 'local') {
            info([
                'Company key' => $company->company_key,
                'Request key' => $request->company_key,
            ]);
        }

        $existing_company = Company::where('company_key', $request->company_key)->first();

        $checks = [
            'same_keys' => $request->company_key == $company->company_key,
            'existing_company' => (bool) $existing_company,
            'with_force' => (bool) ($request->has('force') && ! empty($request->force)),
        ];

        // If same company keys, and force provided.
        if ($checks['same_keys'] && $checks['with_force']) {
            info('Migrating: Same company keys, with force.');

            if ($company) {
                $this->purgeCompany($company);
            }

            $account = auth()->user()->account;
            $company = (new ImportMigrations())->getCompany($account);

            $account->default_company_id = $company->id;
            $account->save();

            $company_token = new CompanyToken();
            $company_token->user_id = $user->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = $request->token_name ?? Str::random(12);
            $company_token->token = $request->token ?? \Illuminate\Support\Str::random(64);
            $company_token->is_system = true;
            $company_token->save();

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

        // If keys are same and no force has been provided.
        if ($checks['same_keys'] && ! $checks['with_force']) {
            info('Migrating: Same company keys, no force provided.');

            MailRouter::dispatch(new ExistingMigration(), $company, $user);

            return response()->json([
                '_id' => Str::uuid(),
                'method' => config('queue.default'),
                'started_at' => now(),
            ], 200);
        }

        // If keys ain't same, but existing company without force.
        if (! $checks['same_keys'] && $checks['existing_company'] && ! $checks['with_force']) {
            info('Migrating: Different keys, existing company with the key without the force option.');

            MailRouter::dispatch(new ExistingMigration(), $company, $user);

            return response()->json([
                '_id' => Str::uuid(),
                'method' => config('queue.default'),
                'started_at' => now(),
            ], 200);
        }

        // If keys ain't same, but existing company with force.
        if (! $checks['same_keys'] && $checks['existing_company'] && $checks['with_force']) {
            info('Migrating: Different keys, exisiting company with force option.');

            if ($company) {
                $this->purgeCompany($company);
            }

            $account = auth()->user()->account;
            $company = (new ImportMigrations())->getCompany($account);

            $account->default_company_id = $company->id;
            $account->save();

            $company_token = new CompanyToken();
            $company_token->user_id = $user->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = $request->token_name ?? Str::random(12);
            $company_token->token = $request->token ?? \Illuminate\Support\Str::random(64);
            $company_token->is_system = true;

            $company_token->save();

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

        // If keys ain't same, but with force.
        if (! $checks['same_keys'] && $checks['with_force']) {
            info('Migrating: Different keys with force.');

            if ($existing_company) {
                $this->purgeCompany($existing_company);
            }

            $account = auth()->user()->account;
            $company = (new ImportMigrations())->getCompany($account);

            $account->default_company_id = $company->id;
            $account->save();

            $company_token = new CompanyToken();
            $company_token->user_id = $user->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = $request->token_name ?? Str::random(12);
            $company_token->token = $request->token ?? \Illuminate\Support\Str::random(64);
            $company_token->is_system = true;

            $company_token->save();

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

        // If keys ain't same, fresh migrate.
        if (! $checks['same_keys'] && ! $checks['with_force']) {
            info('Migrating: Vanilla, fresh migrate.');

            $account = auth()->user()->account;
            $company = (new ImportMigrations())->getCompany($account);

            $company_token = new CompanyToken();
            $company_token->user_id = $user->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = $request->token_name ?? Str::random(12);
            $company_token->token = $request->token ?? \Illuminate\Support\Str::random(64);
            $company_token->is_system = true;

            $company_token->save();

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

        $migration_file = $request->file('migration')
            ->storeAs('migrations', $request->file('migration')->getClientOriginalName());

        if (app()->environment() == 'testing') {
            return;
        }

        StartMigration::dispatch(base_path("storage/app/public/$migration_file"), $user, $company)->delay(now()->addSeconds(60));

        return response()->json([
            '_id' => Str::uuid(),
            'method' => config('queue.default'),
            'started_at' => now(),
        ], 200);
    }
}
