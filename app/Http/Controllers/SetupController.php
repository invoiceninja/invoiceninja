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

namespace App\Http\Controllers;

use App\Http\Requests\Setup\CheckDatabaseRequest;
use App\Http\Requests\Setup\CheckMailRequest;
use App\Http\Requests\Setup\StoreSetupRequest;
use App\Jobs\Account\CreateAccount;
use App\Jobs\Util\SchedulerCheck;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Utils\CurlUtils;
use App\Utils\Ninja;
use App\Utils\SystemHealth;
use App\Utils\Traits\AppSetup;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Class SetupController.
 */
class SetupController extends Controller
{
    use AppSetup;

    public function index()
    {
        $check = SystemHealth::check(false, false);

        if ($check['system_health'] == true && $check['simple_db_check'] && Schema::hasTable('accounts') && $account = Account::first()) {
            return redirect('/');
        }

        if (Ninja::isHosted()) {
            return redirect('/');
        }

        return view('setup.index', ['check' => $check]);
    }

    public function doSetup(StoreSetupRequest $request)
    {
        try {
            $check = SystemHealth::check(false, false);
        } catch (Exception $e) {
            nlog(['message' => $e->getMessage(), 'action' => 'SetupController::doSetup()']);

            return response()->json(['message' => $e->getMessage()], 400);
        }

        if ($check['system_health'] === false) {
            return response('Oops, something went wrong. Check your logs.'); /* We should never reach this block, but just in case. */
        }

        $mail_driver = $request->input('mail_driver');

        $url = $request->input('url');
        $db_host = $request->input('db_host');
        $db_port = $request->input('db_port');
        $db_database = $request->input('db_database');
        $db_username = $request->input('db_username');
        $db_password = $request->input('db_password');
        $mail_port = $request->input('mail_port');
        $encryption = $request->input('encryption');
        $mail_host = $request->input('mail_host');
        $mail_username = $request->input('mail_username');
        $mail_name = $request->input('mail_name');
        $mail_address = $request->input('mail_address');
        $mail_password = $request->input('mail_password');

        $env_values = [
            'APP_URL' => $url,
            'REQUIRE_HTTPS' => $request->input('https') ? 'true' : 'false',
            'APP_DEBUG' => 'false',

            'DB_HOST' => $db_host,
            'DB_PORT' => $db_port,
            'DB_DATABASE' => $db_database,
            'DB_USERNAME' => $db_username,
            'DB_PASSWORD' => $db_password,

            'MAIL_MAILER' => $mail_driver,
            'MAIL_PORT' => $mail_port,
            'MAIL_ENCRYPTION' => $encryption,
            'MAIL_HOST' => $mail_host,
            'MAIL_USERNAME' => $mail_username,
            'MAIL_FROM_NAME' => $mail_name,
            'MAIL_FROM_ADDRESS' => $mail_address,
            'MAIL_PASSWORD' => $mail_password,

            'NINJA_ENVIRONMENT' => 'selfhost',
            'DB_CONNECTION' => 'mysql',
        ];

        if (config('ninja.db.multi_db_enabled')) {
            $env_values['DB_CONNECTION'] = 'db-ninja-01';
        }

        if (config('ninja.preconfigured_install')) {
            // Database connection was already configured. Don't let the user override it.
            unset($env_values['DB_HOST']);
            unset($env_values['DB_PORT']);
            unset($env_values['DB_DATABASE']);
            unset($env_values['DB_USERNAME']);
            unset($env_values['DB_PASSWORD']);
        } else {

            config(['database.connections.mysql.host' => $request->input('db_host')]);
            config(['database.connections.mysql.port' => $request->input('db_port')]);
            config(['database.connections.mysql.database' => $request->input('db_database')]);
            config(['database.connections.mysql.username' => $request->input('db_username')]);
            config(['database.connections.mysql.password' => $request->input('db_password')]);
            config(['database.default' => 'mysql']);

            DB::purge('mysql');
            /* Make sure no stale connections are cached */

        }

        try {
            foreach ($env_values as $property => $value) {
                $this->updateEnvironmentProperty($property, $value);
            }

            /* We need this in some environments that do not have STDIN defined */
            define('STDIN', fopen('php://stdin', 'r'));

            Artisan::call('config:clear');

            Artisan::call('key:generate', ['--force' => true]);

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            Artisan::call('config:clear');

            Storage::disk('local')->delete('test.pdf');

            /* Create the first account. */
            if (Account::count() == 0) {
                (new CreateAccount($request->all(), $request->getClientIp()))->handle();
            }

            (new VersionCheck())->handle();

            return redirect('/');
        } catch (Exception $e) {
            nlog($e->getMessage());
            info($e->getMessage());

            echo $e->getMessage();

            return redirect()
                ->back()
                ->with('setup_error', $e->getMessage());
        }
    }

    /**
     * Return status based on database check.
     *
     * @param CheckDatabaseRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     */
    public function checkDB(CheckDatabaseRequest $request)
    {

        try {
            $status = SystemHealth::dbCheck($request);

            if (is_array($status) && $status['success'] === true) {
                return response([], 200);
            }

            return response($status, 400);
        } catch (Exception $e) {
            nlog("failed?");
            nlog($e->getMessage());
            nlog(['message' => $e->getMessage(), 'action' => 'SetupController::checkDB()']);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Return status based on check of SMTP connection.
     *
     * @param CheckMailRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     */
    public function checkMail(CheckMailRequest $request)
    {
        try {
            $response = SystemHealth::testMailServer($request);

            if ($response['success']) {
                return response([], 200);
            } else {
                return response()->json(['message' => $response['message']], 400);
            }
        } catch (Exception $e) {
            nlog(['message' => $e->getMessage(), 'action' => 'SetupController::checkMail()']);

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function checkPdf(Request $request)
    {
        try {
            return response(['url' => ''], 200);
        } catch (Exception $e) {
            nlog($e->getMessage());

            return response([], 500);
        }
    }

    public function clearCompiledCache()
    {
        $cacheCompiled = base_path('bootstrap/cache/compiled.php');
        if (file_exists($cacheCompiled)) {
            unlink($cacheCompiled);
        }

        $cacheServices = base_path('bootstrap/cache/packages.php');
        if (file_exists($cacheServices)) {
            unlink($cacheServices);
        }

        $cacheServices = base_path('bootstrap/cache/services.php');
        if (file_exists($cacheServices)) {
            unlink($cacheServices);
        }

        $cacheRoute = base_path('bootstrap/cache/routes-v7.php');
        if (file_exists($cacheRoute)) {
            unlink($cacheRoute);
        }
    }

    public function update()
    {
        if (! request()->has('secret') || (request()->input('secret') != config('ninja.update_secret'))) {
            return redirect('/');
        }

        $cacheCompiled = base_path('bootstrap/cache/compiled.php');
        if (file_exists($cacheCompiled)) {
            unlink($cacheCompiled);
        }

        $cacheServices = base_path('bootstrap/cache/services.php');
        if (file_exists($cacheServices)) {
            unlink($cacheServices);
        }

        $cacheRoute = base_path('bootstrap/cache/routes-v7.php');
        if (file_exists($cacheRoute)) {
            unlink($cacheRoute);
        }

        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');

        // Artisan::call('optimize');

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        Artisan::call('cache:clear');

        (new SchedulerCheck())->handle();

        return redirect('/');
    }
}
