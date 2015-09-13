<?php namespace App\Http\Controllers;

use Auth;
use Artisan;
use Cache;
use Config;
use DB;
use Exception;
use Input;
use Utils;
use View;
use Event;
use Session;
use Cookie;
use Response;
use Redirect;
use App\Models\User;
use App\Models\Account;
use App\Models\Industry;
use App\Ninja\Mailers\Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Events\UserSettingsChanged;

class AppController extends BaseController
{
    protected $accountRepo;
    protected $mailer;

    public function __construct(AccountRepository $accountRepo, Mailer $mailer)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->mailer = $mailer;
    }

    public function showSetup()
    {
        if (Utils::isNinjaProd() || (Utils::isDatabaseSetup() && Account::count() > 0)) {
            return Redirect::to('/');
        }

        return View::make('setup');
    }

    public function doSetup()
    {
        if (Utils::isNinjaProd() || (Utils::isDatabaseSetup() && Account::count() > 0)) {
            return Redirect::to('/');
        }

        $valid = false;
        $test = Input::get('test');

        $app = Input::get('app');
        $app['key'] = str_random(RANDOM_KEY_LENGTH);

        $database = Input::get('database');
        $dbType = $database['default'];
        $database['connections'] = [$dbType => $database['type']];

        $mail = Input::get('mail');
        $email = $mail['username'];
        $mail['from']['address'] = $email;

        if ($test == 'mail') {
            return self::testMail($mail);
        }

        $valid = self::testDatabase($database);

        if ($test == 'db') {
            return $valid === true ? 'Success' : $valid;
        } elseif (!$valid) {
            return Redirect::to('/setup')->withInput();
        }
        
        $config = "APP_ENV=production\n".
                    "APP_DEBUG=false\n".
                    "APP_URL={$app['url']}\n".
                    "APP_KEY={$app['key']}\n\n".
                    "DB_TYPE={$dbType}\n".
                    "DB_HOST={$database['type']['host']}\n".
                    "DB_DATABASE={$database['type']['database']}\n".
                    "DB_USERNAME={$database['type']['username']}\n".
                    "DB_PASSWORD={$database['type']['password']}\n\n".
                    "MAIL_DRIVER={$mail['driver']}\n".
                    "MAIL_PORT={$mail['port']}\n".
                    "MAIL_ENCRYPTION={$mail['encryption']}\n".
                    "MAIL_HOST={$mail['host']}\n".
                    "MAIL_USERNAME={$mail['username']}\n".
                    "MAIL_FROM_NAME={$mail['from']['name']}\n".
                    "MAIL_PASSWORD={$mail['password']}";

        // Write Config Settings
        $fp = fopen(base_path()."/.env", 'w');
        fwrite($fp, $config);
        fclose($fp);

        // == DB Migrate & Seed == //
        // Artisan::call('migrate:rollback', array('--force' => true)); // Debug Purposes
        Artisan::call('migrate', array('--force' => true));
        if (Industry::count() == 0) {
            Artisan::call('db:seed', array('--force' => true));
        }
        Cache::flush();
        Artisan::call('optimize', array('--force' => true));
        
        $firstName = trim(Input::get('first_name'));
        $lastName = trim(Input::get('last_name'));
        $email = trim(strtolower(Input::get('email')));
        $password = trim(Input::get('password'));
        $account = $this->accountRepo->create($firstName, $lastName, $email, $password);
        $user = $account->users()->first();

        return Redirect::to('/login');
    }

    private function testDatabase($database)
    {
        $dbType = $database['default'];

        Config::set('database.default', $dbType);
        
        foreach ($database['connections'][$dbType] as $key => $val) {
            Config::set("database.connections.{$dbType}.{$key}", $val);
        }

        try {
            $valid = DB::connection()->getDatabaseName() ? true : false;
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return $valid;
    }

    private function testMail($mail)
    {
        $email = $mail['username'];
        $fromName = $mail['from']['name'];

        foreach ($mail as $key => $val) {
            Config::set("mail.{$key}", $val);
        }

        Config::set('mail.from.address', $email);
        Config::set('mail.from.name', $fromName);

        $data = [
            'text' => 'Test email',
        ];

        try {
            $this->mailer->sendTo($email, $email, $fromName, 'Test email', 'contact', $data);

            return 'Sent';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function install()
    {
        if (!Utils::isNinjaProd() && !Utils::isDatabaseSetup()) {
            try {
                Artisan::call('migrate', array('--force' => true));
                if (Industry::count() == 0) {
                    Artisan::call('db:seed', array('--force' => true));
                }
                Artisan::call('optimize', array('--force' => true));
            } catch (Exception $e) {
                Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }

    public function update()
    {
        if (!Utils::isNinjaProd()) {
            try {
                Artisan::call('migrate', array('--force' => true));
                Artisan::call('db:seed', array('--force' => true, '--class' => 'PaymentLibrariesSeeder'));
                Artisan::call('optimize', array('--force' => true));
                Cache::flush();
                Event::fire(new UserSettingsChanged());
                Session::flash('message', trans('texts.processed_updates'));
            } catch (Exception $e) {
                Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }
}
