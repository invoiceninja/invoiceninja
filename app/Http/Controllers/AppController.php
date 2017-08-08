<?php

namespace App\Http\Controllers;

use App\Events\UserSettingsChanged;
use App\Models\Account;
use App\Models\Industry;
use App\Ninja\Mailers\Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Services\EmailService;
use Artisan;
use Auth;
use Cache;
use Config;
use DB;
use Event;
use Exception;
use Input;
use Redirect;
use Response;
use Session;
use Utils;
use View;

class AppController extends BaseController
{
    protected $accountRepo;
    protected $mailer;
    protected $emailService;

    public function __construct(AccountRepository $accountRepo, Mailer $mailer, EmailService $emailService)
    {
        //parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->mailer = $mailer;
        $this->emailService = $emailService;
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
        if (Utils::isNinjaProd()) {
            return Redirect::to('/');
        }

        $valid = false;
        $test = Input::get('test');

        $app = Input::get('app');
        $app['key'] = env('APP_KEY') ?: strtolower(str_random(RANDOM_KEY_LENGTH));
        $app['debug'] = Input::get('debug') ? 'true' : 'false';
        $app['https'] = Input::get('https') ? 'true' : 'false';

        $database = Input::get('database');
        $dbType = 'mysql'; // $database['default'];
        $database['connections'] = [$dbType => $database['type']];
        $mail = Input::get('mail');

        if ($test == 'mail') {
            return self::testMail($mail);
        }

        $valid = self::testDatabase($database);

        if ($test == 'db') {
            return $valid === true ? 'Success' : $valid;
        } elseif (! $valid) {
            return Redirect::to('/setup')->withInput();
        }

        if (Utils::isDatabaseSetup() && Account::count() > 0) {
            return Redirect::to('/');
        }

        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = $app['debug'];
        $_ENV['APP_LOCALE'] = 'en';
        $_ENV['APP_URL'] = $app['url'];
        $_ENV['APP_KEY'] = $app['key'];
        $_ENV['APP_CIPHER'] = env('APP_CIPHER', 'AES-256-CBC');
        $_ENV['REQUIRE_HTTPS'] = $app['https'];
        $_ENV['DB_TYPE'] = $dbType;
        $_ENV['DB_HOST'] = $database['type']['host'];
        $_ENV['DB_DATABASE'] = $database['type']['database'];
        $_ENV['DB_USERNAME'] = $database['type']['username'];
        $_ENV['DB_PASSWORD'] = $database['type']['password'];
        $_ENV['MAIL_DRIVER'] = $mail['driver'];
        $_ENV['MAIL_PORT'] = $mail['port'];
        $_ENV['MAIL_ENCRYPTION'] = $mail['encryption'];
        $_ENV['MAIL_HOST'] = $mail['host'];
        $_ENV['MAIL_USERNAME'] = $mail['username'];
        $_ENV['MAIL_FROM_NAME'] = $mail['from']['name'];
        $_ENV['MAIL_FROM_ADDRESS'] = $mail['from']['address'];
        $_ENV['MAIL_PASSWORD'] = $mail['password'];
        $_ENV['PHANTOMJS_CLOUD_KEY'] = 'a-demo-key-with-low-quota-per-ip-address';
        $_ENV['PHANTOMJS_SECRET'] = strtolower(str_random(RANDOM_KEY_LENGTH));
        $_ENV['MAILGUN_DOMAIN'] = $mail['mailgun_domain'];
        $_ENV['MAILGUN_SECRET'] = $mail['mailgun_secret'];

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

        // Write Config Settings
        $fp = fopen(base_path().'/.env', 'w');
        fwrite($fp, $config);
        fclose($fp);

        // == DB Migrate & Seed == //
        $sqlFile = base_path() . '/database/setup.sql';
        DB::unprepared(file_get_contents($sqlFile));
        Cache::flush();
        Artisan::call('optimize', ['--force' => true]);

        $firstName = trim(Input::get('first_name'));
        $lastName = trim(Input::get('last_name'));
        $email = trim(strtolower(Input::get('email')));
        $password = trim(Input::get('password'));
        $account = $this->accountRepo->create($firstName, $lastName, $email, $password);
        $user = $account->users()->first();

        return Redirect::to('/login');
    }

    public function updateSetup()
    {
        if (Utils::isNinjaProd()) {
            return Redirect::to('/');
        }

        if (! Auth::check() && Utils::isDatabaseSetup() && Account::count() > 0) {
            return Redirect::to('/');
        }

        if (! $canUpdateEnv = @fopen(base_path().'/.env', 'w')) {
            Session::flash('error', 'Warning: Permission denied to write to .env config file, try running <code>sudo chown www-data:www-data /path/to/ninja/.env</code>');

            return Redirect::to('/settings/system_settings');
        }

        $app = Input::get('app');
        $db = Input::get('database');
        $mail = Input::get('mail');

        $_ENV['APP_URL'] = $app['url'];
        $_ENV['APP_DEBUG'] = Input::get('debug') ? 'true' : 'false';
        $_ENV['REQUIRE_HTTPS'] = Input::get('https') ? 'true' : 'false';

        $_ENV['DB_TYPE'] = 'mysql'; // $db['default'];
        $_ENV['DB_HOST'] = $db['type']['host'];
        $_ENV['DB_DATABASE'] = $db['type']['database'];
        $_ENV['DB_USERNAME'] = $db['type']['username'];
        $_ENV['DB_PASSWORD'] = $db['type']['password'];

        if ($mail) {
            $_ENV['MAIL_DRIVER'] = $mail['driver'];
            $_ENV['MAIL_PORT'] = $mail['port'];
            $_ENV['MAIL_ENCRYPTION'] = $mail['encryption'];
            $_ENV['MAIL_HOST'] = $mail['host'];
            $_ENV['MAIL_USERNAME'] = $mail['username'];
            $_ENV['MAIL_FROM_NAME'] = $mail['from']['name'];
            $_ENV['MAIL_FROM_ADDRESS'] = $mail['from']['address'];
            $_ENV['MAIL_PASSWORD'] = $mail['password'];
            $_ENV['MAILGUN_DOMAIN'] = $mail['mailgun_domain'];
            $_ENV['MAILGUN_SECRET'] = $mail['mailgun_secret'];
        }

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

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('/settings/system_settings');
    }

    private function testDatabase($database)
    {
        $dbType = 'mysql'; // $database['default'];
        Config::set('database.default', $dbType);
        foreach ($database['connections'][$dbType] as $key => $val) {
            Config::set("database.connections.{$dbType}.{$key}", $val);
        }

        try {
            DB::reconnect();
            $valid = DB::connection()->getDatabaseName() ? true : false;
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return $valid;
    }

    private function testMail($mail)
    {
        $email = $mail['from']['address'];
        $fromName = $mail['from']['name'];

        foreach ($mail as $key => $val) {
            Config::set("mail.{$key}", $val);
        }

        Config::set('mail.from.address', $email);
        Config::set('mail.from.name', $fromName);

        $data = [
            'text' => 'Test email',
            'fromEmail' =>  $email
        ];

        try {
            $response = $this->mailer->sendTo($email, $email, $fromName, 'Test email', 'contact', $data);

            return $response === true ? 'Sent' : $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function install()
    {
        if (! Utils::isNinjaProd() && ! Utils::isDatabaseSetup()) {
            try {
                set_time_limit(60 * 5); // shouldn't take this long but just in case
                Artisan::call('migrate', ['--force' => true]);
                if (Industry::count() == 0) {
                    Artisan::call('db:seed', ['--force' => true]);
                }
                Artisan::call('optimize', ['--force' => true]);
            } catch (Exception $e) {
                Utils::logError($e);

                return Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }

    public function update()
    {
        if (! Utils::isNinjaProd()) {
            try {
                set_time_limit(60 * 5);
                $this->checkInnoDB();
                Artisan::call('clear-compiled');
                Artisan::call('cache:clear');
                Artisan::call('debugbar:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                Artisan::call('config:clear');
                Artisan::call('optimize', ['--force' => true]);
                Auth::logout();
                Cache::flush();
                Session::flush();
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('db:seed', ['--force' => true, '--class' => 'UpdateSeeder']);
                Event::fire(new UserSettingsChanged());

                // legacy fix: check cipher is in .env file
                if (! env('APP_CIPHER')) {
                    $fp = fopen(base_path().'/.env', 'a');
                    fwrite($fp, "\nAPP_CIPHER=AES-256-CBC");
                    fclose($fp);
                }

                // show message with link to Trello board
                $message = trans('texts.see_whats_new', ['version' => NINJA_VERSION]);
                $message = link_to(RELEASES_URL, $message, ['target' => '_blank']);
                $message = sprintf('%s - %s', trans('texts.processed_updates'), $message);
                Session::flash('warning', $message);
            } catch (Exception $e) {
                Utils::logError($e);

                return Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }

    // MySQL changed the default table type from MyISAM to InnoDB
    // We need to make sure all tables are InnoDB to prevent migration failures
    public function checkInnoDB()
    {
        $result = DB::select("SELECT engine
                    FROM information_schema.TABLES
                    WHERE TABLE_NAME='clients' AND TABLE_SCHEMA='ninja'");

        if (count($result) && $result[0]->engine == 'InnoDB') {
            return;
        }

        $tables = DB::select('SHOW TABLES');
        $sql = "SET sql_mode = 'ALLOW_INVALID_DATES';\n";

        foreach($tables as $table) {
            $fieldName = 'Tables_in_' . env('DB_DATABASE');
            $sql .= "ALTER TABLE {$table->$fieldName} engine=InnoDB;\n";
        }

        DB::unprepared($sql);
    }

    public function emailBounced()
    {
        $messageId = Input::get('MessageID');
        $error = Input::get('Name') . ': ' . Input::get('Description');

        return $this->emailService->markBounced($messageId, $error) ? RESULT_SUCCESS : RESULT_FAILURE;
    }

    public function emailOpened()
    {
        $messageId = Input::get('MessageID');

        return $this->emailService->markOpened($messageId) ? RESULT_SUCCESS : RESULT_FAILURE;

        return RESULT_SUCCESS;
    }

    public function checkData()
    {
        try {
            Artisan::call('ninja:check-data');
            Artisan::call('ninja:init-lookup', ['--validate' => true]);
            return RESULT_SUCCESS;
        } catch (Exception $exception) {
            return $exception->getMessage() ?: RESULT_FAILURE;
        }
    }

    public function stats()
    {
        if (! hash_equals(Input::get('password'), env('RESELLER_PASSWORD'))) {
            sleep(3);

            return '';
        }

        if (Utils::getResllerType() == RESELLER_REVENUE_SHARE) {
            $data = DB::table('accounts')
                            ->leftJoin('payments', 'payments.account_id', '=', 'accounts.id')
                            ->leftJoin('clients', 'clients.id', '=', 'payments.client_id')
                            ->where('accounts.account_key', '=', NINJA_ACCOUNT_KEY)
                            ->where('payments.is_deleted', '=', false)
                            ->get([
                                'clients.public_id as client_id',
                                'payments.public_id as payment_id',
                                'payments.payment_date',
                                'payments.amount',
                            ]);
        } else {
            $data = DB::table('users')->count();
        }

        return json_encode($data);
    }
}
