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

use App\Http\Requests\Setup\StoreSetupRequest;
use App\Models\Account;
use App\Utils\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;


/**
 * Class SetupController
 */
class SetupController extends Controller
{

	public function index()
	{

		$system_health = SystemHealth::check();

		return view();

	}

	public function doSetup(StoreSetupRequest $request)
	{

        $_ENV['APP_URL'] = $request->input('url');
        $_ENV['APP_DEBUG'] = $request->input('debug') ? 'true' : 'false';
        $_ENV['REQUIRE_HTTPS'] = $request->input('https') ? 'true' : 'false';
        $_ENV['DB_TYPE'] = 'mysql'; 
        $_ENV['DB_HOST1'] = $request->input('host');
        $_ENV['DB_DATABASE1'] = $request->input('db_username');
        $_ENV['DB_USERNAME1'] = $request->input('db_password');
        $_ENV['DB_PASSWORD1'] = $request->input('db_password');
	    $_ENV['MAIL_DRIVER'] = $request->input('mail_driver');
	    $_ENV['MAIL_PORT'] = $request->input('port');
	    $_ENV['MAIL_ENCRYPTION'] = $request->input('encryption');
	    $_ENV['MAIL_HOST'] = $request->input('mail_host');
	    $_ENV['MAIL_USERNAME'] = $request->input('mail_username');
	    $_ENV['MAIL_FROM_NAME'] = $request->input('mail_name');
	    $_ENV['MAIL_FROM_ADDRESS'] = $request->input('mail_address');
	    $_ENV['MAIL_PASSWORD'] = $request->input('mail_password');
        $_ENV['NINJA_ENVIRONMENT'] = 'selfhost';
		$_ENV['SELF_UPDATER_REPO_VENDOR'] = 'invoiceninja';
		$_ENV['SELF_UPDATER_REPO_NAME'] = 'invoiceninja';
		$_ENV['SELF_UPDATER_USE_BRANCH'] = 'v2';
		$_ENV['SELF_UPDATER_MAILTO_ADDRESS'] = $request->input('mail_address');
		$_ENV['SELF_UPDATER_MAILTO_NAME'] = $request->input('mail_name');
		$_ENV['DB_CONNECTION'] = 'db-ninja-01';
		$_ENV['APP_DEBUG'] = false;

        $config = '';

        foreach ($_ENV as $key => $val) {
            if (is_array($val)) {
                continue;
            }
            if (preg_match('/\s/', $val)) {
                $val = "'{$val}'";
            }
            $config .= "{$key}={$val}\n";
        }

        $filePath = base_path().'/.env';
        $fp = fopen($filePath, 'w');
        fwrite($fp, $config);
        fclose($fp);

        Artisan::call('optimize');
        Artisan::call('migrate');
        Artisan::call('db:seed');

        if(Account::count() == 0) 
        	$account = CreateAccount::dispatchNow($request->all());
        
        return redirect('/');

	}

}