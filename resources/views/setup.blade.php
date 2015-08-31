<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
  <head>
    <title>Invoice Ninja | Setup</title> 
    <meta charset="utf-8">    
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="{{ asset('js/built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('favicon.png?test') }}" rel="shortcut icon">

    <style type="text/css">
    body {
        background-color: #FEFEFE;
    }
    </style>

  </head>

  <body>
  <div class="container">

    &nbsp;
    <div class="row">
    <div class="col-md-8 col-md-offset-2">

    <div class="jumbotron">
        <h2>Invoice Ninja Setup</h2>
        @if (version_compare(phpversion(), '5.4.0', '<'))
            <div class="alert alert-warning">Warning: The application requires PHP >= 5.4.0</div>
        @endif
        @if (!function_exists('proc_open'))
            <div class="alert alert-warning">Warning: <a href="http://php.net/manual/en/function.proc-open.php" target="_blank">proc_open</a> must be enabled.</div>
        @endif
        @if (!@fopen(base_path()."/.env", 'a'))
            <div class="alert alert-warning">Warning: Permission denied to write config file
                <pre>sudo chown yourname:www-data /path/to/ninja</pre>
            </div>
        @endif
        If you need help you can either post to our <a href="https://www.invoiceninja.com/forums/forum/support/" target="_blank">support forum</a> 
        or email us at <a href="mailto:contact@invoiceninja.com" target="_blank">contact@invoiceninja.com</a>.
        <p>
<pre>-- Commands to create a MySQL database and user
CREATE SCHEMA `ninja` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER 'ninja'@'localhost' IDENTIFIED BY 'ninja';
GRANT ALL PRIVILEGES ON `ninja`.* TO 'ninja'@'localhost';
FLUSH PRIVILEGES;</pre>
        </p>
    </div>

    {!! Former::open()->rules([
        'app[url]' => 'required',
        'database[type][host]' => 'required',
        'database[type][database]' => 'required',
        'database[type][username]' => 'required',
        'database[type][password]' => 'required',
        'first_name' => 'required',
        'last_name' => 'required',
        'email' => 'required|email',
        'password' => 'required',
        'terms_checkbox' => 'required'
      ]) !!}

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Application Settings</h3>
      </div>
      <div class="panel-body">
        {!! Former::text('app[url]')->label('URL')->value(isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : Request::root()) !!}
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Database Connection</h3>
      </div>
      <div class="panel-body">
        {!! Former::select('database[default]')->label('Driver')->options(['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'])
                ->value(isset($_ENV['DB_TYPE']) ? $_ENV['DB_TYPE'] : 'mysql') !!}
        {!! Former::text('database[type][host]')->label('Host')->value('localhost') 
                ->value(isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : '') !!}
        {!! Former::text('database[type][database]')->label('Database')->value('ninja') 
                ->value(isset($_ENV['DB_DATABASE']) ? $_ENV['DB_DATABASE'] : '') !!}
        {!! Former::text('database[type][username]')->label('Username')->value('ninja') 
                ->value(isset($_ENV['DB_USERNAME']) ? $_ENV['DB_USERNAME'] : '') !!}
        {!! Former::password('database[type][password]')->label('Password')->value('ninja') 
                ->value(isset($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : '') !!}
        {!! Former::actions( Button::primary('Test connection')->small()->withAttributes(['onclick' => 'testDatabase()']), '&nbsp;&nbsp;<span id="dbTestResult"/>' ) !!}      
      </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Email Settings</h3>
      </div>
      <div class="panel-body">
        {!! Former::select('mail[driver]')->label('Driver')->options(['smtp' => 'SMTP', 'mail' => 'Mail', 'sendmail' => 'Sendmail'])
                 ->value(isset($_ENV['MAIL_DRIVER']) ? $_ENV['MAIL_DRIVER'] : 'smtp') !!}
        {!! Former::text('mail[host]')->label('Host')
                ->value(isset($_ENV['MAIL_HOST']) ? $_ENV['MAIL_HOST'] : '') !!}
        {!! Former::text('mail[port]')->label('Port')
                ->value(isset($_ENV['MAIL_PORT']) ? $_ENV['MAIL_PORT'] : '587')  !!}
        {!! Former::select('mail[encryption]')->label('Encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL'])
                ->value(isset($_ENV['MAIL_ENCRYPTION']) ? $_ENV['MAIL_ENCRYPTION'] : 'tls')  !!}
        {!! Former::text('mail[from][name]')->label('From Name')
                ->value(isset($_ENV['MAIL_FROM_NAME']) ? $_ENV['MAIL_FROM_NAME'] : '')  !!}
        {!! Former::text('mail[username]')->label('Email')
                ->value(isset($_ENV['MAIL_USERNAME']) ? $_ENV['MAIL_USERNAME'] : '')  !!}
        {!! Former::password('mail[password]')->label('Password')
                ->value(isset($_ENV['MAIL_PASSWORD']) ? $_ENV['MAIL_PASSWORD'] : '')  !!}    
        {!! Former::actions( Button::primary('Send test email')->small()->withAttributes(['onclick' => 'testMail()']), '&nbsp;&nbsp;<span id="mailTestResult"/>' ) !!}            
      </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">User Details</h3>
      </div>
      <div class="panel-body">
        {!! Former::text('first_name') !!}
        {!! Former::text('last_name') !!}
        {!! Former::text('email') !!}
        {!! Former::password('password') !!}        
      </div>
    </div>


    {!! Former::checkbox('terms_checkbox')->label(' ')->text(trans('texts.agree_to_terms', ['terms' => '<a href="'.NINJA_APP_URL.'/terms" target="_blank">'.trans('texts.terms_of_service').'</a>'])) !!}
    {!! Former::actions( Button::primary('Submit')->large()->submit() ) !!}        
    {!! Former::close() !!}

  </div>

  <script type="text/javascript">

  /* 
   * TODO: 
   * - Add JS Validation to DB and Mail
   * - Add Function to clear valid vars fields if they change a setting
   * - Add Nicer Error Message
   *
   */

    var db_valid = false
    var mail_valid = false
      
    function testDatabase()
    {
      var data = $("form").serialize() + "&test=db";
      
      // Show Progress Text
      $('#dbTestResult').html('Working...').css('color', 'black');

      // Send / Test Information
      $.post( "/setup", data, function( data ) {
        var color = 'red';
        if(data == 'Success'){
          color = 'green';
          db_valid = true;
        }
        $('#dbTestResult').html(data).css('color', color);
      });

      return db_valid;
    }  

    function testMail()
    {      
      var data = $("form").serialize() + "&test=mail";
      
      // Show Progress Text
      $('#mailTestResult').html('Working...').css('color', 'black');

      // Send / Test Information
      $.post( "/setup", data, function( data ) {
        var color = 'red';
        if(data == 'Sent'){
          color = 'green';
          mail_valid = true;
        }
        $('#mailTestResult').html(data).css('color', color);
      });
      
      return mail_valid;
    }

    // Validate Settings
    /*$('form button[type="submit"]').click( function(e)
    {
      // Check DB Settings
      if( !db_valid && !testDatabase() ) {
        alert('Please check your Database Settings.');
        return false;
      }

      // If Mail Settings are incorrect, prompt for continue
      if( !mail_valid && !testMail() ) {
        var check = confirm("The mail settings are incomplete.\nAre you sure you want to continue?");
        if (!check) {
          return false;
        }
      }

      return true;
    });*/

    // Prevent the Enter Button from working
    $("form").bind("keypress", function (e) {
      if (e.keyCode == 13) {
        return false;
      }
    });

  </script>

  </body>  
</html>