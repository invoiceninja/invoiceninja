<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Http\Requests\Setup\CheckDatabaseRequest;
use App\Http\Requests\Setup\CheckMailRequest;
use App\Http\Requests\Setup\StoreSetupRequest;
use App\Jobs\Account\CreateAccount;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Utils\CurlUtils;
use App\Utils\Ninja;
use App\Utils\SystemHealth;
use App\Utils\Traits\AppSetup;
use Beganovich\Snappdf\Snappdf;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Support\Facades\DB;

/**
 * Class SetupController.
 */
class SetupController extends Controller
{
    use AppSetup;

    public function index()
    {
        $check = SystemHealth::check(false);

        if ($check['system_health'] == true && $check['simple_db_check'] && Schema::hasTable('accounts') && $account = Account::all()->first()) {
            return redirect('/');
        }

        // not sure if we really need this.
        // if(File::exists(base_path('.env')))
        //     abort(400, '.env file already exists, delete file to start Setup again.');

        return view('setup.index', ['check' => $check]);
    }

    public function doSetup(StoreSetupRequest $request)
    {
        try {
            $check = SystemHealth::check(false);
        } catch (Exception $e) {
            nlog(['message' => $e->getMessage(), 'action' => 'SetupController::doSetup()']);

            return response()->json(['message' => $e->getMessage()], 400);
        }

        if ($check['system_health'] === false) {
            nlog($check);

            return response('Oops, something went wrong. Check your logs.'); /* We should never reach this block, but just in case. */
        }

        try {
            $db = SystemHealth::dbCheck($request);

            if ($db['success'] == false) {
                throw new Exception($db['message']);
            }
        } catch (Exception $e) {
            return response([
                'message' => 'Oops, connection to database was not successful.',
                'error' => $e->getMessage(),
            ]);
        }

        try {
            if ($request->mail_driver != 'log') {
                $smtp = SystemHealth::testMailServer($request);

                if ($smtp['success'] == false) {
                    throw new Exception($smtp['message']);
                }
            }
        } catch (Exception $e) {
            return response([
                'message' => 'Oops, connection to mail server was not successful.',
                'error' => $e->getMessage(),
            ]);
        }

        $mail_driver = $request->input('mail_driver');

        $env_values = [
            'APP_URL' => $request->input('url'),
            'REQUIRE_HTTPS' => $request->input('https') ? 'true' : 'false',
            'APP_DEBUG' => 'false',

            'DB_HOST1' => $request->input('db_host'),
            'DB_PORT1' => $request->input('db_port'),
            'DB_DATABASE1' => $request->input('db_database'),
            'DB_USERNAME1' => $request->input('db_username'),
            'DB_PASSWORD1' => $request->input('db_password'),

            'MAIL_MAILER' => $mail_driver,
            'MAIL_PORT' => $request->input('mail_port'),
            'MAIL_ENCRYPTION' => $request->input('encryption'),
            'MAIL_HOST' => $request->input('mail_host'),
            'MAIL_USERNAME' => $request->input('mail_username'),
            'MAIL_FROM_NAME' => $request->input('mail_name'),
            'MAIL_FROM_ADDRESS' => $request->input('mail_address'),
            'MAIL_PASSWORD' => $request->input('mail_password'),

            'NINJA_ENVIRONMENT' => 'selfhost',
        ];

        if (config('ninja.db.multi_db_enabled')) {
            $env_values['DB_CONNECTION'] = 'db-ninja-01';
        }

        if (config('ninja.preconfigured_install')) {
            // Database connection was already configured. Don't let the user override it.
            unset($env_values['DB_HOST1']);
            unset($env_values['DB_PORT1']);
            unset($env_values['DB_DATABASE1']);
            unset($env_values['DB_USERNAME1']);
            unset($env_values['DB_PASSWORD1']);
        }

        try {
            foreach ($env_values as $property => $value) {
                $this->updateEnvironmentProperty($property, $value);
            }

            /* We need this in some environments that do not have STDIN defined */
            define('STDIN', fopen('php://stdin', 'r'));

            /* Make sure no stale connections are cached */
            DB::purge('db-ninja-01');
            
            /* Run migrations */
            if (!config('ninja.disable_auto_update')) {
                Artisan::call('optimize');
            }

            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            
            Storage::disk('local')->delete('test.pdf');

            /* Create the first account. */
            if (Account::count() == 0) {
                CreateAccount::dispatchNow($request->all());
            }

            VersionCheck::dispatchNow();

            $this->buildCache(true);

            return redirect('/');
        } catch (Exception $e) {
            nlog($e->getMessage());
            info($e->getMessage());

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
            if (config('ninja.phantomjs_pdf_generation')) {
                return $this->testPhantom();
            }

            $pdf = new Snappdf();

            if (config('ninja.snappdf_chromium_path')) {
                $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
            }

            $pdf = $pdf
                ->setHtml('GENERATING PDFs WORKS! Thank you for using Invoice Ninja!')
                ->generate();

            Storage::disk(config('filesystems.default'))->put('test.pdf', $pdf);
            Storage::disk('local')->put('test.pdf', $pdf);

            return response(['url' => Storage::disk('local')->url('test.pdf')], 200);
        } catch (Exception $e) {
            nlog($e->getMessage());

            return response([], 500);
        }
    }

    private function testPhantom()
    {
        try {
            $key = config('ninja.phantomjs_pdf_generation');
            $url = 'https://www.invoiceninja.org/';

            $phantom_url = "https://phantomjscloud.com/api/browser/v2/{$key}/?request=%7Burl:%22{$url}%22,renderType:%22pdf%22%7D";
            $pdf = CurlUtils::get($phantom_url);

            Storage::disk(config('filesystems.default'))->put('test.pdf', $pdf);
            Storage::disk('local')->put('test.pdf', $pdf);

            return response(['url' => Storage::disk('local')->url('test.pdf')], 200);
        } catch (Exception $e) {
            return response([], 500);
        }
    }

    public function update()
    {

        // if( Ninja::isNinja() || !request()->has('secret') || (request()->input('secret') != config('ninja.update_secret')) )
        if(!request()->has('secret') || (request()->input('secret') != config('ninja.update_secret')) )
            return redirect('/');

        $cacheCompiled = base_path('bootstrap/cache/compiled.php');
        if (file_exists($cacheCompiled)) {
            unlink ($cacheCompiled);
        }
        $cacheServices = base_path('bootstrap/cache/services.php');
        if (file_exists($cacheServices)) {
            unlink ($cacheServices);
        }

        Artisan::call('clear-compiled');
        Artisan::call('cache:clear');
        Artisan::call('debugbar:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Cache::flush();
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        $this->buildCache(true);

        return redirect('/');

    }
}
