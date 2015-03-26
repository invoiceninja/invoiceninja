<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Invoice Ninja | Setup</title> 
    <meta charset="utf-8">    
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="{{ asset('built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <link href="{{ asset('built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
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
        @if (!extension_loaded('fileinfo'))
            <div class="alert alert-warning">Warning: The <a href="http://php.net/manual/en/book.fileinfo.php" target="_blank">fileinfo</a> extension needs to be installed and enabled.</div>
        @endif
        @if (!@fopen(base_path()."/.env", 'w'))
            <div class="alert alert-warning">Warning: Permission denied to write config file
                <pre>sudo chown yourname:www-data /path/to/ninja</pre>
            </div>
        @endif
        If you need help you can either post to our <a href="https://groups.google.com/forum/#!forum/invoiceninja" target="_blank">Google Group</a> 
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
        {!! Former::text('app[url]')->label('URL')->value(Request::root()) !!}        
      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Database Connection</h3>
      </div>
      <div class="panel-body">
        {!! Former::select('database[default]')->label('Driver')->options(['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite']) !!}
        {!! Former::text('database[type][host]')->label('Host')->value('localhost') !!}
        {!! Former::text('database[type][database]')->label('Database')->value('ninja') !!}
        {!! Former::text('database[type][username]')->label('Username')->value('ninja') !!}
        {!! Former::password('database[type][password]')->label('Password')->value('ninja') !!}
        {!! Former::actions( Button::normal('Test connection')->withAttributes(['onclick' => 'testDatabase()']), '&nbsp;&nbsp;<span id="dbTestResult"/>' ) !!}      
      </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Email Settings</h3>
      </div>
      <div class="panel-body">
        {!! Former::select('mail[driver]')->label('Driver')->options(['smtp' => 'SMTP', 'mail' => 'Mail', 'sendmail' => 'Sendmail']) !!}
        {!! Former::text('mail[host]')->label('Host')->value('localhost') !!}
        {!! Former::text('mail[port]')->label('Port')->value('587') !!}
        {!! Former::select('mail[encryption]')->label('Encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL']) !!}
        {!! Former::text('mail[from][name]')->label('From Name') !!}
        {!! Former::text('mail[username]')->label('Email') !!}
        {!! Former::password('mail[password]')->label('Password') !!}    
        {!! Former::actions( Button::normal('Send test email')->withAttributes(['onclick' => 'testMail()']), '&nbsp;&nbsp;<span id="mailTestResult"/>' ) !!}            
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
    {!! Former::actions( Button::primary('Submit')->submit() ) !!}        
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