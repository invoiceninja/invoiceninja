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
        $app['debug'] = 'false';

        $database = Input::get('database');
        $dbType = $database['driver'];
        
        $test_database = $database;
        $test_database['connections'] = [$dbType => $test_database];        

        $mail = Input::get('mail');
        $email = $mail['username'];
        $mail['from']['address'] = $email;

        if ($test == 'mail') {
            return self::testMail($mail);
        }

        $valid = self::testDatabase($test_database);

        if ($test == 'db') {
            return $valid === true ? 'Success' : $valid;
        } elseif (!$valid) {
            return Redirect::to('/setup')->withInput();
        }

        /*$content = "<?php return 'production';";
        $fp = fopen(base_path()."/bootstrap/environment.php", 'w');
        fwrite($fp, $content);
        fclose($fp);*/

        /*$configDir = base_path().'/config/production';
        if (!file_exists($configDir)) {
            mkdir($configDir);
        }*/

        // TODO: GET THIS WORKING PROPERLY!!!!
        
        // == ENV Settings (Production) == //

        $env_config = '';
        $settings = ['app' => $app, 'database' => $database, 'mail' => $mail];

        // Save each config area to $env varible
        foreach ($settings as $key => $config) {

            // Write a nice Comment to lay out each config area
            $env_config .= "# " . $key . " Settings \n";


            // For Each config varible : Write to env
            foreach ($config as $name => $value) {
                if(is_array($value)){
                    continue; // BREAKS ON THE MAIL ARRAY
                    dd($value);
                }
                $env_config .= strtoupper($name) . '=' . $value . "\n";
            }
        }

        // Check Config Settings !empty
        if(empty($env_config)){
            dd('ERROR: No Config settings saved to content var');
        }
        
        // Write Config Settings
        // $fp = fopen(base_path()."/.env", 'w');
        // fwrite($fp, $env_config);
        // fclose($fp);

        // == END ENV Settings == //


        // == DB Migrate & Seed == //

        /* Laravel 5 thows an error when performing these calls
         * See: https://laracasts.com/discuss/channels/general-discussion/l5-artisancall-issue
        
        Artisan::call('migrate');
        Artisan::call('db:seed');

         */
        
        // I Really don't want to do it this way but its the best I've found so far.
        $process = new \Symfony\Component\Process\Process('cd ' . base_path() . ' && php artisan migrate --seed');
        $process->run();

        // == END DB Migrate & Seed == //


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
        $this->accountRepo->registerUser($user);

        return Redirect::to('/invoices/create');
    }

    private function testDatabase($database)
    {
        // dd($database);
        $dbType = $database['driver'];

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
