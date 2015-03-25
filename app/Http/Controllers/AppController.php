<?php namespace App\Http\Controllers;

use Artisan;
use Cache;
use Config;
use DB;
use Exception;
use Input;
use Utils;
use View;
use App\Ninja\Mailers\Mailer;
use App\Ninja\Repositories\AccountRepository;
use Redirect;

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
        if (Utils::isNinja() || Utils::isDatabaseSetup()) {
            return Redirect::to('/');
        }

        return View::make('setup');
    }

    public function doSetup()
    {
        // if (Utils::isNinja() || Utils::isDatabaseSetup()) {
        //     return Redirect::to('/');
        // }

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
        
        // == ENV Settings (Production) == //
        $config = "APP_ENV=development\n".
                    "APP_DEBUG=true\n".
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
                    "MAIL_PASSWORD={$mail['password']}\n";

        // Write Config Settings
        $fp = fopen(base_path()."/.env", 'w');
        fwrite($fp, $config);
        fclose($fp);

        // == DB Migrate & Seed == //
        Artisan::call('migrate', array('--force' => true));
        Artisan::call('db:seed', array('--force' => true));

        $account = $this->accountRepo->create();
        $user = $account->users()->first();

        $user->first_name = trim(Input::get('first_name'));
        $user->last_name = trim(Input::get('last_name'));
        $user->email = trim(strtolower(Input::get('email')));
        $user->username = $user->email;
        $user->password = trim(Input::get('password'));
        $user->password_confirmation = trim(Input::get('password'));
        $user->registered = true;
        $user->save();

        //Auth::login($user, true);
        //$this->accountRepo->registerUser($user);

        return Redirect::to('/invoices/create');
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

    // QUSTION: Does this actually get used???
    public function install()
    {
        if (!Utils::isNinja() && !Utils::isDatabaseSetup()) {
            try {
                // DB Migrate & Seed
                // I Really don't want to do it this way but its the best I've found so far. See Above.
                $process = new \Symfony\Component\Process\Process('cd ' . base_path() . ' && php artisan migrate --seed');
                $process->run();

            } catch (Exception $e) {
                Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }

    public function update()
    {
        if (!Utils::isNinja()) {
            try {
                // DB Migrate & Seed
                // I Really don't want to do it this way but its the best I've found so far. See Above.
                $process = new \Symfony\Component\Process\Process('cd ' . base_path() . ' && php artisan migrate');
                $process->run();

                // Artisan::call('migrate');
                Cache::flush();
            } catch (Exception $e) {
                Response::make($e->getMessage(), 500);
            }
        }

        return Redirect::to('/');
    }
}
