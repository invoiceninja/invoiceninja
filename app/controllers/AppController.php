<?php

use ninja\mailers\Mailer;
use ninja\repositories\AccountRepository;

class AppController extends BaseController {

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
    if (Utils::isNinja() || Utils::isDatabaseSetup())
    {
      return Redirect::to('/');      
    }

    return View::make('setup');
  }

  public function doSetup()
  {
    if (Utils::isNinja() || Utils::isDatabaseSetup())
    {
      return Redirect::to('/');      
    }
    
    $valid = false;
    $test = Input::get('test');

    $app = Input::get('app');
    $app['key'] = str_random(RANDOM_KEY_LENGTH);

    $database = Input::get('database');
    $dbType = $database['default'];
    $database[$dbType] = $database['type'];
    //unset($database['type']);

    $mail = Input::get('mail');
    $email = $mail['username'];      
    $mail['from']['address'] = $email;
    
    if ($test == 'mail')
    {
      return self::testMail($mail);
    }

    $valid = self::testDatabase($database);

    if ($test == 'db')
    {
      return $valid ? 'Success' : 'Failed';
    }
    else if (!$valid)
    {
      return Redirect::to('/setup')->withInput();
    }    
    
    $content = "<?php return 'production';";
    $fp = fopen(base_path() . "/bootstrap/environment.php" , 'w');
    fwrite($fp, $content);
    fclose($fp);

    $configDir = app_path() . '/config/production';
    if (!file_exists($configDir))
    {
      mkdir($configDir);
    }    

    foreach(['app' => $app, 'database' => $database, 'mail' => $mail] as $key => $config)
    {
      $content = '<?php return ' . var_export($config, true) . ';';
      $fp = fopen(app_path() . "/config/production/{$key}.php" , 'w');
      fwrite($fp, $content);
      fclose($fp);      
    }

    Artisan::call('migrate');
    Artisan::call('db:seed');   

    $account = $this->accountRepo->create();
    $user = $account->users()->first();
    
    $user->first_name = trim(Input::get('first_name'));
    $user->last_name = trim(Input::get('last_name'));
    $user->email = trim(strtolower(Input::get('email')));
    $user->username = $user->email;
    $user->password = trim(Input::get('password'));
    $user->password_confirmation = trim(Input::get('password'));
    $user->registered = true;
    $user->amend();

    //Auth::login($user, true);
    $this->accountRepo->registerUser($user);

    return Redirect::to('/invoices/create');
  }

  private function testDatabase($database)
  {
    $dbType = $database['default'];
    
    Config::set('database.default', $dbType);
    
    foreach ($database[$dbType] as $key => $val)
    {
      Config::set("database.connections.{$dbType}.{$key}", $val);  
    }

    try 
    {
      $valid = DB::connection()->getDatabaseName() ? true : false;
    } 
    catch (Exception $e) 
    {
      return $e->getMessage();
    }

    return $valid;
  }

  private function testMail($mail)
  {
    $email = $mail['username'];          
    $fromName = $mail['from']['name'];

    foreach ($mail as $key => $val)
    {
      Config::set("mail.{$key}", $val);        
    }

    Config::set('mail.from.address', $email);
    Config::set('mail.from.name', $fromName);

    $data = [   
      'text' => 'Test email'
    ];

    try
    {
      $this->mailer->sendTo($email, $email, $fromName, 'Test email', 'contact', $data);
      return 'Sent';
    }
    catch (Exception $e) 
    {
      return $e->getMessage();        
    }    
  }

  public function install()
  {
    if (!Utils::isNinja() && !Utils::isDatabaseSetup()) {
      try {
        Artisan::call('migrate');
        Artisan::call('db:seed');   
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
        Artisan::call('migrate');
        Cache::flush();
      } catch (Exception $e) {
        Response::make($e->getMessage(), 500);
      }
    }

    return Redirect::to('/');
  }

}