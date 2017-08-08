<div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{{ trans('texts.application_settings') }}</h3>
      </div>
      <div class="panel-body form-padding-right">
        {!! Former::text('app[url]')->label(trans('texts.url'))->value(isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : Request::root()) !!}
        {!! Former::checkbox('https')->text(trans('texts.require'))->check(env('REQUIRE_HTTPS'))->value(1) !!}
        {!! Former::checkbox('debug')->text(trans('texts.enable'))->check(config('app.debug'))->value(1) !!}

      </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{{ trans('texts.database_connection') }}</h3>
      </div>
      <div class="panel-body form-padding-right">
        {{--- Former::select('database[default]')->label('driver')->options(['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'])
                ->value(isset($_ENV['DB_TYPE']) ? $_ENV['DB_TYPE'] : 'mysql') ---}}
        {!! Former::plaintext('Driver')->value('MySQL') !!}
        {!! Former::text('database[type][host]')->label('host')->value(isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost') !!}
        {!! Former::text('database[type][database]')->label('database')->value(isset($_ENV['DB_DATABASE']) ? $_ENV['DB_DATABASE'] : 'ninja') !!}
        {!! Former::text('database[type][username]')->label('username')->value(isset($_ENV['DB_USERNAME']) ? $_ENV['DB_USERNAME'] : 'ninja') !!}
        {!! Former::password('database[type][password]')->label('password')->value(isset($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : 'ninja') !!}
        {!! Former::actions( Button::primary(trans('texts.test_connection'))->small()->withAttributes(['onclick' => 'testDatabase()']), '&nbsp;&nbsp;<span id="dbTestResult"/>' ) !!}
      </div>
    </div>

    @if (!isset($_ENV['POSTMARK_API_TOKEN']))
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{{ trans('texts.email_settings') }}</h3>
          </div>
          <div class="panel-body form-padding-right">
            {!! Former::select('mail[driver]')->label('driver')->options(['smtp' => 'SMTP', 'mail' => 'Mail', 'sendmail' => 'Sendmail', 'mailgun' => 'Mailgun'])
                     ->value(isset($_ENV['MAIL_DRIVER']) ? $_ENV['MAIL_DRIVER'] : 'smtp')->setAttributes(['onchange' => 'mailDriverChange()']) !!}
            {!! Former::text('mail[from][name]')->label('from_name')
                     ->value(isset($_ENV['MAIL_FROM_NAME']) ? $_ENV['MAIL_FROM_NAME'] : '')  !!}
            {!! Former::text('mail[from][address]')->label('from_address')
                     ->value(isset($_ENV['MAIL_FROM_ADDRESS']) ? $_ENV['MAIL_FROM_ADDRESS'] : '')  !!}
            {!! Former::text('mail[username]')->label('username')
                     ->value(isset($_ENV['MAIL_USERNAME']) ? $_ENV['MAIL_USERNAME'] : '')  !!}
            <div id="standardMailSetup">
              {!! Former::text('mail[host]')->label('host')
                      ->value(isset($_ENV['MAIL_HOST']) ? $_ENV['MAIL_HOST'] : '') !!}
              {!! Former::text('mail[port]')->label('port')
                      ->value(isset($_ENV['MAIL_PORT']) ? $_ENV['MAIL_PORT'] : '587')  !!}
              {!! Former::select('mail[encryption]')->label('encryption')
                      ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'])
                      ->value(isset($_ENV['MAIL_ENCRYPTION']) ? $_ENV['MAIL_ENCRYPTION'] : 'tls')  !!}
              {!! Former::password('mail[password]')->label('password')
                      ->value(isset($_ENV['MAIL_PASSWORD']) ? $_ENV['MAIL_PASSWORD'] : '')  !!}
            </div>
            <div id="mailgunMailSetup">
              {!! Former::text('mail[mailgun_domain]')->label('mailgun_domain')
                      ->value(isset($_ENV['MAILGUN_DOMAIN']) ? $_ENV['MAILGUN_DOMAIN'] : '') !!}
              {!! Former::text('mail[mailgun_secret]')->label('mailgun_private_key')
                      ->value(isset($_ENV['MAILGUN_SECRET']) ? $_ENV['MAILGUN_SECRET'] : '')  !!}
            </div>
              {!! Former::actions( Button::primary(trans('texts.send_test_email'))->small()->withAttributes(['onclick' => 'testMail()']), '&nbsp;&nbsp;<span id="mailTestResult"/>' ) !!}
          </div>
        </div>
    @endif

  <script type="text/javascript">

    var db_valid = false
    var mail_valid = false
    mailDriverChange();

    function testDatabase()
    {
      var data = $("form").serialize() + "&test=db";

      // Show Progress Text
      $('#dbTestResult').html('Working...').css('color', 'black');

      // Send / Test Information
      $.post( "{{ URL::to('/setup') }}", data, function( data ) {
        var color = 'red';
        if(data == 'Success'){
          color = 'green';
          db_valid = true;
        }
        $('#dbTestResult').html(data).css('color', color);
      });

      return db_valid;
    }

    function mailDriverChange() {
      if ($("select[name='mail[driver]'").val() == 'mailgun') {
        $("#standardMailSetup").hide();
        $("#standardMailSetup").children('select,input').prop('disabled',true);
        $("#mailgunMailSetup").show();
        $("#mailgunMailSetup").children('select,input').prop('disabled',false);

      } else {
        $("#standardMailSetup").show();
        $("#standardMailSetup").children('select,input').prop('disabled',false);

        $("#mailgunMailSetup").hide();
        $("#mailgunMailSetup").children('select,input').prop('disabled',true);

      }
    }

    function testMail()
    {
      var data = $("form").serialize() + "&test=mail";

      // Show Progress Text
      $('#mailTestResult').html('Working...').css('color', 'black');

      // Send / Test Information
      $.post( "{{ URL::to('/setup') }}", data, function( data ) {
        var color = 'red';
        if(data == 'Sent'){
          color = 'green';
          mail_valid = true;
        }
        $('#mailTestResult').html(data).css('color', color);
      });

      return mail_valid;
    }

    // Prevent the Enter Button from working
    $("form").bind("keypress", function (e) {
      if (e.keyCode == 13) {
        return false;
      }
    });

  </script>
